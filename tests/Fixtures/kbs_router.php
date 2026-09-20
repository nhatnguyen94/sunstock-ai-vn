<?php
// Router script for `php -S` in tests/Feature/Support/GetStockScriptTest.php: a tiny fake of KBS's history endpoint.
// It answers like the real one: newest bar first, stock prices in VND, index levels in points, "t" = "Y-m-d 07:00".

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
header('Content-Type: application/json');

if (preg_match('#/stocks/FPT/data_day$#', $path)) {
    echo json_encode(['symbol' => 'FPT', 'data_day' => [
        ['t' => '2026-09-18 07:00', 'o' => 74500, 'h' => 74800, 'l' => 71700, 'c' => 71700, 'v' => 15500700],
        ['t' => '2026-09-17 07:00', 'o' => 73300, 'h' => 74300, 'l' => 72600, 'c' => 74300, 'v' => 6626700],
        ['t' => '2026-09-01 07:00', 'o' => 70000, 'h' => 71000, 'l' => 69500, 'c' => 70500, 'v' => 1000000],
    ]]);
} elseif (preg_match('#/index/VNINDEX/data_day$#', $path)) {
    echo json_encode(['symbol' => 'VNINDEX', 'data_day' => [
        ['t' => '2026-09-18 07:00', 'o' => 1832.38, 'h' => 1841.2, 'l' => 1790.63, 'c' => 1815.66, 'v' => 881177232],
    ]]);
} elseif (preg_match('#/stocks/QUIET/data_day$#', $path)) {
    echo json_encode(['symbol' => 'QUIET', 'data_day' => []]);
} else {
    http_response_code(404);
    echo json_encode(['error' => 'not found']);
}
