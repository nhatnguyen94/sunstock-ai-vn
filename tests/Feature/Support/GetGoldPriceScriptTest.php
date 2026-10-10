<?php

namespace Tests\Feature\Support;

use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * py/get_gold_price.py with vnstock's two gold functions replaced by stand-ins (no network, no vnstock import). The point of these tests is
 * what the script asks the network for: BTMC went silent for weeks because vnstock's default address is http:// (port 80 stopped answering)
 * and vnstock calls requests without any timeout.
 */
class GetGoldPriceScriptTest extends TestCase
{
    /** Run `$tail` (python) after importing the script as `g` with fake vnstock functions; returns every JSON line it printed. */
    private function runGold(string $tail): array
    {
        $python = (string) config('services.python.path', 'python');
        $code = <<<'PY'
import json, sys, types
import pandas as pd
calls = {}
def sjc_gold_price(date=None):
    return pd.DataFrame([{'name': 'Vàng SJC 1L, 10L, 1KG', 'branch': 'Hồ Chí Minh', 'buy_price': '141,800,000', 'sell_price': '144,800,000'}])
def btmc_goldprice(url="http://api.btmc.vn/api/BTMCAPI/getpricebtmc?key=TESTKEY"):
    calls['url'] = url
    return pd.DataFrame([
        {'name': 'VÀNG MIẾNG SJC (Vàng SJC)', 'karat': '24k', 'gold_content': '999.9', 'buy_price': '13,970,000', 'sell_price': '14,480,000', 'world_price': '4190', 'time': '10/10/2026 17:01'},
        {'name': 'BẠC MIẾNG PHÚ QUÝ Ag 999 1 LƯỢNG', 'karat': '', 'gold_content': '', 'buy_price': '2,227,000', 'sell_price': '2,296,000', 'world_price': '', 'time': '10/10/2026 17:01'},
    ])
for name in ('vnstock', 'vnstock.explorer', 'vnstock.explorer.misc', 'vnstock.explorer.misc.gold_price'):
    sys.modules[name] = types.ModuleType(name)
sys.modules['vnstock.explorer.misc.gold_price'].sjc_gold_price = sjc_gold_price
sys.modules['vnstock.explorer.misc.gold_price'].btmc_goldprice = btmc_goldprice
sys.path.insert(0, 'py')
import get_gold_price as g
PY;
        $code .= "\n".$tail."\n";

        $out = [];
        $status = 0;
        exec('cd '.escapeshellarg(base_path()).' && '.escapeshellarg($python).' -c '.escapeshellarg($code).' 2>&1', $out, $status);
        $text = implode("\n", $out);

        if ($status !== 0 && (str_contains($text, 'ModuleNotFoundError') || str_contains($text, 'not found'))) {
            $this->markTestSkipped('Python (or pandas / requests) is not available here.');
        }
        $this->assertSame(0, $status, $text);

        return array_values(array_map(
            fn ($line) => json_decode(trim($line), true, 512, JSON_THROW_ON_ERROR),
            array_filter($out, fn ($line) => str_starts_with(trim($line), '{'))
        ));
    }

    #[Group('goldPrice')]
    public function test_btmc_is_asked_over_https_keeping_the_address_and_key_vnstock_ships(): void
    {
        [, $calls] = $this->runGold("g.main()\nprint(json.dumps(calls))");

        $this->assertSame('https://api.btmc.vn/api/BTMCAPI/getpricebtmc?key=TESTKEY', $calls['url'], 'only the scheme changes: the host, path and key come from vnstock\'s own default');
    }

    #[Group('goldPrice')]
    public function test_both_sources_and_the_world_price_come_back_in_the_stored_shape(): void
    {
        [$result] = $this->runGold('g.main()');

        $this->assertSame([], $result['errors']);
        $this->assertSame([], $result['warnings']);
        $this->assertSame(144_800_000, $result['sjc'][0]['sell']);
        $this->assertSame(4190.0, $result['world_usd_oz']);

        $gold = collect($result['btmc'])->firstWhere('metal', 'gold');
        $this->assertSame(139_700_000, $gold['buy'], 'BTMC quotes per chi: stored per luong (x10)');
        $this->assertSame(144_800_000, $gold['sell']);
        $this->assertSame('2026-10-10T10:01:00Z', $gold['quoted_at'], 'Vietnam time converted to UTC');
        $this->assertSame('silver', collect($result['btmc'])->firstWhere('metal', 'silver')['metal']);
    }

    #[Group('goldPrice')]
    public function test_every_vnstock_request_gets_a_timeout_but_an_explicit_one_is_kept(): void
    {
        [$seen] = $this->runGold(<<<'PY'
import requests
seen = {}
def recorder(name):
    def fake(*args, **kwargs):
        seen[name] = kwargs.get('timeout')
        return None
    return fake
requests.get = recorder('get')
requests.post = recorder('post')
requests.get('http://example.test/')
requests.post('http://example.test/', timeout=3)
g.install_http_timeout()
requests.get('http://example.test/')
requests.post('http://example.test/', data={})
requests.post('http://example.test/', timeout=3)
print(json.dumps({'default_get': seen['get'], 'default_post': seen['post'], 'kept': 3 if seen['post'] == 3 else seen['post'], 'limit': g.HTTP_TIMEOUT}))
PY);

        $this->assertSame(20, $seen['limit']);
        $this->assertSame(20, $seen['default_get'], 'a call without a timeout now has one');
        $this->assertSame(3, $seen['kept'], 'a timeout the caller chose is not overridden');
    }

    #[Group('goldPrice')]
    public function test_a_dead_btmc_still_returns_sjc_and_says_why(): void
    {
        [$result] = $this->runGold(<<<'PY'
def dead(url=''):
    raise RuntimeError('connection timed out')
sys.modules['vnstock.explorer.misc.gold_price'].btmc_goldprice = dead
g.main()
PY);

        $this->assertSame(144_800_000, $result['sjc'][0]['sell']);
        $this->assertSame([], $result['btmc']);
        $this->assertStringContainsString('connection timed out', $result['errors']['btmc']);
    }
}
