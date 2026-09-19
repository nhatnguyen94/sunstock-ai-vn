<?php

namespace Tests\Unit\Models;

use App\Models\Fund;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

class FundTest extends TestCase
{
    #[Group('fundCatalog')]
    public function test_type_code_is_derived_from_fmarkets_vietnamese_label(): void
    {
        $this->assertSame(Fund::TYPE_STOCK, Fund::typeCodeFromLabel('Quỹ cổ phiếu'));
        $this->assertSame(Fund::TYPE_BOND, Fund::typeCodeFromLabel('Quỹ trái phiếu'));
        $this->assertSame(Fund::TYPE_BALANCED, Fund::typeCodeFromLabel('Quỹ cân bằng'));
        $this->assertSame(Fund::TYPE_MMF, Fund::typeCodeFromLabel('Quỹ MMF'));
        $this->assertSame(Fund::TYPE_OTHER, Fund::typeCodeFromLabel('Quỹ bất động sản'));
        $this->assertSame(Fund::TYPE_OTHER, Fund::typeCodeFromLabel(null));
    }

    #[Group('fundCatalog')]
    public function test_type_label_falls_back_for_unknown_codes(): void
    {
        $this->assertSame('Quỹ trái phiếu', (new Fund(['type_code' => 'BOND']))->type_label);
        $this->assertSame('Khác', (new Fund(['type_code' => 'WHATEVER']))->type_label);
    }
}
