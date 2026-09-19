<?php

namespace Tests\Unit\Support;

use App\Support\Mojibake;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

class MojibakeTest extends TestCase
{
    #[Group('stockSearch')]
    public function test_repairs_cp437_mojibake_back_to_vietnamese(): void
    {
        $this->assertSame('Công ty Cổ phần Xây dựng Số 5', Mojibake::repairCp437('C├┤ng ty Cß╗ò phß║ºn X├óy dß╗▒ng Sß╗æ 5'));
    }

    #[Group('stockSearch')]
    public function test_text_that_already_reads_correctly_is_left_alone(): void
    {
        foreach (['CTCP Đầu tư và Xây dựng Vina2', 'FPT Corporation', '', null] as $good) {
            $this->assertFalse(Mojibake::looksBroken($good));
            $this->assertNull(Mojibake::repairCp437($good));
        }
    }
}
