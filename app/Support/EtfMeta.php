<?php

namespace App\Support;

use App\Models\Etf;

/**
 * Pure helpers that turn an ETF's registered name into what the pages show: who runs it, which index it tracks and
 * whether it is an ETF at all. The feed carries only symbol + name (no fee, no benchmark), so this is parsed, not
 * looked up — anything unrecognised stays null and the page shows "—" instead of guessing.
 */
class EtfMeta
{
    /** Label shown to users => regex on the upper-cased name. Longer / more specific patterns first. */
    private const MANAGERS = [
        'Techcom Capital' => '/TECHCOM CAPITAL/',
        'FPT Capital'     => '/FPT CAPITAL/',
        'VinaCapital'     => '/VINACAPITAL/',
        'Kim Growth'      => '/KIM GROWTH/',
        'IPA Partner'     => '/IPA PARTNER/',
        'DCVFM'           => '/DCVFM/',
        'SSIAM'           => '/SSIAM/',
        'MAFM'            => '/MAFM/',
        'PHFM'            => '/PHFM/',
        'BVF'             => '/BVFVN/',
        'ABF'             => '/ABFVN/',
        'VFC'             => '/VFCVN/',
    ];

    /** Index label => regex on the upper-cased name. */
    private const INDICES = [
        'VNFinSelect' => '/VNFIN ?SELECT/',
        'VNFinLead'   => '/VNFIN ?LEAD/',
        'VNMidCap'    => '/VNMIDCAP/',
        'VNMITech'    => '/VNMITECH/',
        'VNShine'     => '/VNSHINE/',
        'VN50 Growth' => '/VN50 GROWTH/',
        'VNX50'       => '/VNX50/',
        'VN100'       => '/VN100/',
        'VN30'        => '/VN30/',
        'VNDiamond'   => '/VN ?DIAMOND/',
    ];

    /** Short plain-language notes for the indices we are confident about (others are shown without one). */
    private const INDEX_NOTES = [
        'VN30'        => '30 cổ phiếu vốn hóa lớn và thanh khoản cao nhất sàn HOSE.',
        'VN100'       => '100 cổ phiếu lớn nhất HOSE: VN30 cộng nhóm vốn hóa trung bình.',
        'VNMidCap'    => 'Nhóm cổ phiếu vốn hóa trung bình, ngay sau nhóm VN30.',
        'VNX50'       => 'Nhóm 50 cổ phiếu lớn và thanh khoản tốt của HOSE.',
        'VNDiamond'   => 'Cổ phiếu chọn theo tiêu chí chất lượng và còn room cho khối ngoại.',
        'VNFinLead'   => 'Nhóm cổ phiếu tài chính dẫn đầu (ngân hàng, chứng khoán, bảo hiểm).',
        'VNFinSelect' => 'Nhóm cổ phiếu tài chính được chọn lọc.',
    ];

    public static function kind(?string $name): string
    {
        return $name !== null && preg_match('/\bETF\b/iu', $name) ? Etf::KIND_ETF : Etf::KIND_CLOSED;
    }

    public static function manager(?string $name): ?string
    {
        return self::match(self::MANAGERS, $name);
    }

    public static function trackedIndex(?string $name): ?string
    {
        return self::match(self::INDICES, $name);
    }

    public static function indexNote(?string $index): ?string
    {
        return $index !== null ? (self::INDEX_NOTES[$index] ?? null) : null;
    }

    /** "Quỹ ETF SSIAM VN30" -> "SSIAM VN30": drops the legal boilerplate in front of the brand. */
    public static function shortName(?string $name): ?string
    {
        if ($name === null) {
            return null;
        }
        $short = preg_replace('/^(chứng chỉ )?quỹ (đầu tư )?(etf )?/iu', '', trim($name));

        return $short !== '' ? $short : $name;
    }

    /** @param array<string, string> $patterns */
    private static function match(array $patterns, ?string $name): ?string
    {
        if ($name === null || $name === '') {
            return null;
        }
        $upper = mb_strtoupper($name);

        foreach ($patterns as $label => $regex) {
            if (preg_match($regex, $upper)) {
                return $label;
            }
        }

        return null;
    }
}
