<?php

namespace Tests\Unit\CodeStyle;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * The status codes and stock messages of the application live in App\Support\TransformerResponse. This test fails when a controller, middleware,
 * service or route builds its own response with a bare number or a raw helper again — add what is missing to that class instead.
 */
class NoScatteredResponsesTest extends TestCase
{
    /** Where the single source of truth lives: it may of course use the raw helpers itself. */
    private const OWNER = 'app/Support/TransformerResponse.php';

    /** pattern => what to use instead */
    private const FORBIDDEN = [
        '/\bresponse\(\)->json\(/' => 'TransformerResponse::success() / failed() / json()',
        '/(?<![>:\w])abort(_if|_unless)?\(/' => 'TransformerResponse::abortWith() / abortIf() / abortUnless()',
        '/->with\(\s*\'(success|error|warning|info)\'/' => 'TransformerResponse::back*() / redirect*()',
        '/\'status\'\s*=>\s*[45]\d\d\b/' => "a TransformerResponse::HTTP_* constant for 'status'",
        '/\bresponse\([^;]*,\s*[45]\d\d\)/' => 'a TransformerResponse::HTTP_* constant as the response status',
        '/\bfail\([^;]*,\s*[45]\d\d\)/' => 'a TransformerResponse::HTTP_* constant as the failure status',
    ];

    /** @return array<string, string> relative path => contents */
    private function sources(): array
    {
        $root = dirname(__DIR__, 3);
        $found = [];
        $found['bootstrap/app.php'] = (string) file_get_contents($root.'/bootstrap/app.php');   // the exception renderers live here
        foreach (['app', 'routes'] as $dir) {
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$dir, RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }
                $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
                if ($relative !== self::OWNER) {
                    $found[$relative] = (string) file_get_contents($file->getPathname());
                }
            }
        }

        return $found;
    }

    /** Code without comments and without the contents of string literals' surroundings is overkill here: comments are dropped, the rest is kept. */
    private function code(string $source): string
    {
        $out = '';
        foreach (token_get_all($source) as $token) {
            if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                $out .= str_repeat("\n", substr_count($token[1], "\n"));

                continue;
            }
            $out .= is_array($token) ? $token[1] : $token;
        }

        return $out;
    }

    /** @return array<int, string> */
    private function violations(array $sources): array
    {
        $bad = [];
        foreach ($sources as $path => $source) {
            foreach (explode("\n", $this->code($source)) as $i => $line) {
                foreach (self::FORBIDDEN as $pattern => $use) {
                    if (preg_match($pattern, $line)) {
                        $bad[] = $path.':'.($i + 1).'  '.trim($line).'   → use '.$use;
                    }
                }
            }
        }

        return $bad;
    }

    #[Group('transformerResponse')]
    #[Group('codeStyle')]
    public function test_no_controller_middleware_service_or_route_builds_its_own_status_or_flash(): void
    {
        $this->assertSame([], $this->violations($this->sources()), "Use App\\Support\\TransformerResponse (add a constant or helper there if one is missing):\n");
    }

    #[Group('transformerResponse')]
    #[Group('codeStyle')]
    public function test_the_scanner_catches_each_kind_of_violation_and_lets_clean_code_through(): void
    {
        $bad = [
            "return response()->json(['a' => 1], 404);",
            'abort(404);',
            '$x ?? abort_if(true, 403);',
            "return back()->with('success', 'x');",
            "return ['ok' => false, 'status' => 422];",
            "return response('x', 403);",
            "return \$this->fail('x', 404);",
        ];
        foreach ($bad as $line) {
            $this->assertNotSame([], $this->violations(['t.php' => "<?php\n{$line}\n"]), "should be caught: {$line}");
        }

        $clean = [
            'return TransformerResponse::notFound();',
            'TransformerResponse::abortWith(TransformerResponse::HTTP_NOT_FOUND);',
            "return TransformerResponse::backSuccess('x');",
            "return ['ok' => false, 'status' => TransformerResponse::HTTP_UNPROCESSABLE_ENTITY];",
            '$a = mb_substr($s, 0, 500);',
            '// abort(404) and response()->json( inside a comment are fine',
        ];
        foreach ($clean as $line) {
            $this->assertSame([], $this->violations(['t.php' => "<?php\n{$line}\n"]), "should pass: {$line}");
        }
    }
}
