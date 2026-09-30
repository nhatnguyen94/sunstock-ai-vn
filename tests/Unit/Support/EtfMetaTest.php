<?php

namespace Tests\Unit\Support;

use App\Models\Etf;
use App\Support\EtfMeta;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

class EtfMetaTest extends TestCase
{
    /** Registered names exactly as KBS returns them, with what the pages must show. */
    public static function realNames(): array
    {
        return [
            'DCVFM VN30'         => ['Quỹ ETF DCVFMVN30', 'DCVFM', 'VN30', 'etf'],
            'DCVFM midcap'       => ['Chứng chỉ Quỹ ETF DCVFMVNMIDCAP', 'DCVFM', 'VNMidCap', 'etf'],
            'DCVFM diamond'      => ['Quỹ ETF DCVFMVN DIAMOND', 'DCVFM', 'VNDiamond', 'etf'],
            'SSIAM VNX50'        => ['Quỹ ETF SSIAM VNX50', 'SSIAM', 'VNX50', 'etf'],
            'SSIAM fin lead'     => ['Quỹ ETF SSIAM VNFIN LEAD', 'SSIAM', 'VNFinLead', 'etf'],
            'Kim fin select'     => ['Chứng chỉ Quỹ ETF Kim Growth VNFINSELECT', 'Kim Growth', 'VNFinSelect', 'etf'],
            'Kim diamond'        => ['Chứng chỉ Quỹ ETF KIM GROWTH VN DIAMOND', 'Kim Growth', 'VNDiamond', 'etf'],
            'MAFM diamond'       => ['Chứng chỉ Quỹ ETF MAFM VNDIAMOND', 'MAFM', 'VNDiamond', 'etf'],
            'FPT Capital VNX50'  => ['Chứng chỉ Quỹ ETF FPT CAPITAL VNX50', 'FPT Capital', 'VNX50', 'etf'],
            'Techcom VNX50'      => ['Chứng chỉ Quỹ ETF TECHCOM CAPITAL VNX50', 'Techcom Capital', 'VNX50', 'etf'],
            'VinaCapital growth' => ['Quỹ ETF VINACAPITAL VN50 GROWTH', 'VinaCapital', 'VN50 Growth', 'etf'],
            'VinaCapital VN100'  => ['Quỹ ETF VINACAPITAL VN100', 'VinaCapital', 'VN100', 'etf'],
            'IPA VN100'          => ['Quỹ ETF IPA PARTNER VN100', 'IPA Partner', 'VN100', 'etf'],
            'PHFM shine'         => ['Quỹ ETF PHFM VNSHINE', 'PHFM', 'VNShine', 'etf'],
            'BVF diamond'        => ['Chứng chỉ Quỹ ETF BVFVN DIAMOND', 'BVF', 'VNDiamond', 'etf'],
            'ABF diamond'        => ['Chứng chỉ Quỹ ETF ABFVN DIAMOND', 'ABF', 'VNDiamond', 'etf'],
            'VFC diamond'        => ['Chứng chỉ quỹ ETF VFCVN DIAMOND', 'VFC', 'VNDiamond', 'etf'],
            'closed-end fund'    => ['Quỹ đầu tư tăng trưởng Thiên Việt 5', null, null, 'closed'],
            'REIT'               => ['Quỹ đầu tư Bất động sản Techcom Việt Nam', null, null, 'closed'],
        ];
    }

    #[Group('etf')]
    #[DataProvider('realNames')]
    public function test_manager_index_and_kind_are_read_from_the_registered_name(string $name, ?string $manager, ?string $index, string $kind): void
    {
        $this->assertSame($manager, EtfMeta::manager($name));
        $this->assertSame($index, EtfMeta::trackedIndex($name));
        $this->assertSame($kind, EtfMeta::kind($name));
    }

    #[Group('etf')]
    public function test_missing_or_unrecognised_names_give_null_instead_of_a_guess(): void
    {
        $this->assertNull(EtfMeta::manager(null));
        $this->assertNull(EtfMeta::manager(''));
        $this->assertNull(EtfMeta::trackedIndex('Quỹ ETF Something New'));
        $this->assertNull(EtfMeta::indexNote(null));
        $this->assertNull(EtfMeta::shortName(null));
        // No "ETF" word -> not an ETF; a fund with no name at all is treated the same way
        $this->assertSame(Etf::KIND_CLOSED, EtfMeta::kind(null));
    }

    #[Group('etf')]
    public function test_short_name_drops_the_legal_prefix_only(): void
    {
        $this->assertSame('SSIAM VN30', EtfMeta::shortName('Quỹ ETF SSIAM VN30'));
        $this->assertSame('KIM GROWTH VN30', EtfMeta::shortName('Chứng chỉ Quỹ ETF KIM GROWTH VN30'));
        $this->assertSame('tăng trưởng Thiên Việt 5', EtfMeta::shortName('Quỹ đầu tư tăng trưởng Thiên Việt 5'));
        $this->assertSame('Something', EtfMeta::shortName('Something'));
    }

    #[Group('etf')]
    public function test_index_notes_exist_only_for_indices_we_are_sure_about(): void
    {
        $this->assertStringContainsString('30 cổ phiếu', EtfMeta::indexNote('VN30'));
        $this->assertNull(EtfMeta::indexNote('VNMITech'));
        $this->assertNull(EtfMeta::indexNote('Unknown'));
    }
}
