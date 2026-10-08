<?php

namespace App\Frontend\Services;

use App\Frontend\Interfaces\CompanyProfileRepositoryInterface;
use Illuminate\Support\Facades\Cache;

/**
 * The "Sự kiện" tab of the home page: dividends / bonus shares / shareholder meetings whose key date is coming up, taken from the company
 * profiles the site has cached (`CompanyProfileService::upcomingEventsFor`). Honest about its reach: only companies with a stored profile are
 * covered — the nightly profile sync warms the most traded stocks first — and a signed-in visitor's own watchlist and portfolio symbols are
 * added (and queued for a profile when they have none), flagged so the page can say "of yours".
 *
 * Read-only: it never calls Python itself (missing profiles are only queued, deduplicated, by upcomingEventsFor), and the shared list is cached.
 */
class HomeEventsService
{
    public const HORIZON_DAYS = 60;

    public const LIMIT = 12;

    /** At most this many of a visitor's own symbols are looked up (each without a stored profile queues one background job). */
    private const MINE_MAX = 15;

    public function __construct(
        private readonly CompanyProfileService $profiles,
        private readonly CompanyProfileRepositoryInterface $repo
    ) {}

    /**
     * @param  string[]  $mySymbols  watchlist + portfolio symbols of the signed-in visitor (empty for a guest)
     * @return array{events: array<int, array<string, mixed>>, covered: int}
     */
    public function forHome(array $mySymbols = []): array
    {
        $shared = Cache::remember('home:events:v1:'.now()->format('Y-m-d'), 1800, function () {
            $symbols = $this->repo->allSymbols();

            return ['covered' => count($symbols), 'events' => $this->profiles->upcomingEventsFor($symbols, 60)];
        });

        $mine = array_values(array_unique(array_slice($mySymbols, 0, self::MINE_MAX)));
        $own = $mine === [] ? [] : $this->profiles->upcomingEventsFor($mine, 30);

        $events = [];
        foreach (array_merge($shared['events'], $own) as $e) {
            $row = $this->present($e, in_array($e['symbol'], $mine, true));
            if ($row !== null) {
                $events[$row['symbol'].'|'.($e['code'] ?? '').'|'.$row['date']] = $row;
            }
        }

        $horizon = now()->addDays(self::HORIZON_DAYS)->toDateString();
        $events = array_filter($events, fn (array $r) => $r['date'] <= $horizon);
        usort($events, fn (array $a, array $b) => strcmp($a['date'], $b['date']) ?: ($b['mine'] <=> $a['mine']));

        return ['events' => array_slice($events, 0, self::LIMIT), 'covered' => $shared['covered']];
    }

    /** @return array{symbol: string, date: string, kind: string, label: string, title: string, mine: bool}|null */
    private function present(array $e, bool $mine): ?array
    {
        $date = (string) ($e['key_date'] ?? '');
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return null;
        }
        $meeting = ($e['category'] ?? '') === 'SHAREHOLDER_MEETING';

        return [
            'symbol' => (string) $e['symbol'],
            'date' => $date,
            'kind' => $meeting ? 'meeting' : 'dividend',
            'label' => $meeting ? 'Đại hội cổ đông' : (isset($e['exright_date']) ? 'Giao dịch không hưởng quyền' : 'Chốt quyền'),
            'title' => trim((string) ($e['title'] ?? $e['name'] ?? '')),
            'mine' => $mine,
        ];
    }
}
