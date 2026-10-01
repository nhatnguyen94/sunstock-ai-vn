<?php

namespace Tests\Unit\CodeStyle;

use FilesystemIterator;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Project rule (AGENTS.md "Imports"): a class is imported with `use` at the top of the file and referred to by its
 * short name below. Writing a class out in full with a leading backslash (in code, in a doc comment or in Blade) is not
 * allowed. Two classes with the same short name are told apart with `use Foo\Bar as FooBar;` (and
 * `@use('Foo\Bar', 'FooBar')` in Blade).
 *
 * Pure token/regex scan: no Laravel app is booted.
 */
class NoInlineFullyQualifiedNamesTest extends TestCase
{
    private const PHP_ROOTS = ['app', 'routes', 'bootstrap', 'database', 'config', 'tests'];

    private const FIX_HINT = 'Import the class with `use` at the top of the file and use the short name (alias with `as` if two classes share a name; `@use(...)` in Blade).';

    private function root(): string
    {
        return dirname(__DIR__, 3);
    }

    /** @return list<string> absolute paths */
    private function files(array $roots, string $suffix): array
    {
        $found = [];
        foreach ($roots as $root) {
            $dir = $this->root().'/'.$root;
            if (! is_dir($dir)) {
                continue;
            }
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
            foreach ($it as $file) {
                if (str_ends_with($file->getPathname(), $suffix)) {
                    $found[] = $file->getPathname();
                }
            }
        }
        sort($found);

        return $found;
    }

    private function relative(string $path): string
    {
        return str_replace('\\', '/', substr($path, strlen($this->root()) + 1));
    }

    /**
     * Inline fully-qualified names in PHP code. Function calls (`\count(...)`) and constants (`\PHP_EOL`) are not classes
     * and are left alone; everything else that starts with a backslash is a class written out in the body.
     *
     * @return list<string> "path:line name"
     */
    private function inlineNamesInCode(string $path): array
    {
        $tokens = token_get_all(file_get_contents($path));
        $count = count($tokens);
        $found = [];

        for ($i = 0; $i < $count; $i++) {
            if (! is_array($tokens[$i]) || $tokens[$i][0] !== T_NAME_FULLY_QUALIFIED) {
                continue;
            }

            $name = $tokens[$i][1];
            $next = $i + 1;
            while ($next < $count && is_array($tokens[$next]) && in_array($tokens[$next][0], [T_WHITESPACE, T_COMMENT], true)) {
                $next++;
            }
            $prev = $i - 1;
            while ($prev >= 0 && is_array($tokens[$prev]) && in_array($tokens[$prev][0], [T_WHITESPACE, T_COMMENT], true)) {
                $prev--;
            }

            $isFunctionCall = ($tokens[$next] ?? null) === '(' && ! (is_array($tokens[$prev] ?? null) && $tokens[$prev][0] === T_NEW);
            $isConstant = preg_match('/^\\\\[A-Z_0-9]+$/', $name) === 1;
            if ($isFunctionCall || $isConstant) {
                continue;
            }

            $found[] = $this->relative($path).':'.$tokens[$i][2].' '.$name;
        }

        return $found;
    }

    /** @return list<string> */
    private function inlineNamesInComments(string $path): array
    {
        $found = [];
        foreach (token_get_all(file_get_contents($path)) as $token) {
            if (! is_array($token) || ! in_array($token[0], [T_DOC_COMMENT, T_COMMENT], true)) {
                continue;
            }
            if (preg_match_all('/(?<![A-Za-z0-9_\\\\])\\\\[A-Z][A-Za-z0-9_]*(?:\\\\[A-Z][A-Za-z0-9_]*)*/', $token[1], $matches)) {
                foreach ($matches[0] as $name) {
                    if (preg_match('/^\\\\[A-Z_0-9]+$/', $name) === 1) {
                        continue;   // a constant such as PHP_EOL, not a class
                    }
                    $found[] = $this->relative($path).':'.$token[2].' '.$name;
                }
            }
        }

        return $found;
    }

    #[Group('codeStyle')]
    public function test_php_code_imports_classes_instead_of_writing_their_full_name_inline(): void
    {
        $offenders = [];
        foreach ($this->files(self::PHP_ROOTS, '.php') as $path) {
            if (str_ends_with($path, '.blade.php')) {
                continue;
            }
            array_push($offenders, ...$this->inlineNamesInCode($path));
        }

        $this->assertSame([], $offenders, self::FIX_HINT."\n".implode("\n", $offenders));
    }

    #[Group('codeStyle')]
    public function test_doc_comments_use_imported_short_names_too(): void
    {
        $offenders = [];
        foreach ($this->files(self::PHP_ROOTS, '.php') as $path) {
            if (str_ends_with($path, '.blade.php')) {
                continue;
            }
            array_push($offenders, ...$this->inlineNamesInComments($path));
        }

        $this->assertSame([], $offenders, self::FIX_HINT."\n".implode("\n", $offenders));
    }

    #[Group('codeStyle')]
    public function test_blade_views_use_the_use_directive_instead_of_inline_class_names(): void
    {
        $offenders = [];
        foreach ($this->files(['resources/views'], '.blade.php') as $path) {
            foreach (explode("\n", str_replace("\r\n", "\n", file_get_contents($path))) as $number => $line) {
                if (preg_match_all('/(?<![A-Za-z0-9_\\\\\'"$])\\\\(?:[A-Z][A-Za-z0-9_]*\\\\)+[A-Z][A-Za-z0-9_]*/', $line, $matches)) {
                    foreach ($matches[0] as $name) {
                        $offenders[] = $this->relative($path).':'.($number + 1).' '.$name;
                    }
                }
            }
        }

        $this->assertSame([], $offenders, self::FIX_HINT."\n".implode("\n", $offenders));
    }

    #[Group('codeStyle')]
    public function test_the_scanner_itself_recognises_what_it_is_meant_to_catch_and_what_it_must_leave_alone(): void
    {
        $dir = sys_get_temp_dir().'/fqcn-'.uniqid();
        mkdir($dir);
        $bad = $dir.'/bad.php';
        $good = $dir.'/good.php';

        file_put_contents($bad, <<<'PHP'
<?php
namespace A;
use Foo\Bar;
class T {
    /** @var \Some\Thing $x */
    public function f() {
        $a = \App\Models\User::STATUS_ACTIVE;
        $b = new \RuntimeException('x');
        try {} catch (\Throwable $e) {}
        return app(\App\Frontend\Services\X::class);
    }
}
PHP);
        file_put_contents($good, <<<'PHP'
<?php
namespace A;
use App\Models\User;
use Foo\Bar as FooBar;
use RuntimeException;
class T {
    public function f() {
        $n = \count([1]) + \strlen('x');
        $s = '\App\Models\User';
        return [User::STATUS_ACTIVE, new RuntimeException('x'), \PHP_EOL, FooBar::class];
    }
}
PHP);

        try {
            $this->assertCount(4, $this->inlineNamesInCode($bad), 'User::, new RuntimeException, catch Throwable, X::class');
            $this->assertCount(1, $this->inlineNamesInComments($bad));
            $this->assertSame([], $this->inlineNamesInCode($good));
            $this->assertSame([], $this->inlineNamesInComments($good));
        } finally {
            @unlink($bad);
            @unlink($good);
            @rmdir($dir);
        }
    }
}
