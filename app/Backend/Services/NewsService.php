<?php

namespace App\Backend\Services;

use App\Backend\Interfaces\NewsRepositoryInterface;
use App\Backend\Interfaces\NewsServiceInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class NewsService implements NewsServiceInterface
{
    private const SOURCES = [
        ['name' => 'VnExpress', 'url' => 'https://vnexpress.net/rss/kinh-doanh.rss',    'category_id' => 1],
        ['name' => 'VnExpress', 'url' => 'https://vnexpress.net/rss/chung-khoan.rss',   'category_id' => 2],
        ['name' => 'CafeF',     'url' => 'https://cafef.vn/thi-truong-chung-khoan.rss', 'category_id' => 3],
        ['name' => 'CafeF',     'url' => 'https://cafef.vn/doanh-nghiep.rss',           'category_id' => 4],
        ['name' => 'Dan Tri',   'url' => 'https://dantri.com.vn/rss/kinh-doanh.rss',   'category_id' => 1],
    ];

    public function __construct(protected NewsRepositoryInterface $newsRepository) {}

    public function listNews(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return $this->newsRepository->paginate($filters, $perPage);
    }

    public function getSources(): array
    {
        return $this->newsRepository->getSources();
    }

    public function getCategories(): Collection
    {
        return $this->newsRepository->getCategories();
    }

    public function syncFromAllSources(): array
    {
        $totalSynced = 0;
        $errors      = [];
        $syncedAt    = now()->toDateTimeString();

        foreach (self::SOURCES as $source) {
            try {
                $items = $this->fetchAndParse($source['url'], $source['name'], $source['category_id'], $syncedAt);
                $totalSynced += $this->newsRepository->insertNew($items);
            } catch (\Throwable $e) {
                $errors[] = "[{$source['name']}] {$source['url']}: {$e->getMessage()}";
            }
        }

        return ['synced' => $totalSynced, 'errors' => $errors];
    }

    private function fetchAndParse(string $url, string $sourceName, int $categoryId, string $syncedAt): array
    {
        $xml = $this->fetchRss($url);
        $rss = @simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOCDATA);
        if (!$rss || !isset($rss->channel->item)) return [];

        $items = [];
        foreach ($rss->channel->item as $item) {
            $itemUrl = trim((string) $item->link);
            if (empty($itemUrl)) continue;
            $ts    = ($p = (string)$item->pubDate) ? (strtotime($p) ?: time()) : time();
            $title = trim(strip_tags((string)$item->title));
            $desc  = trim(strip_tags((string)$item->description));
            if (empty($title)) continue;
            $items[] = [
                'title'        => mb_substr($title, 0, 500),
                'description'  => mb_substr($desc, 0, 2000) ?: null,
                'url'          => mb_substr($itemUrl, 0, 1000),
                'url_hash'     => md5($itemUrl),
                'source'       => $sourceName,
                'image_url'    => ($img = $this->extractImage($item)) ? mb_substr($img, 0, 1000) : null,
                'category_id'  => $categoryId,
                'published_at' => date('Y-m-d H:i:s', $ts),
                'synced_at'    => $syncedAt,
                'created_at'   => $syncedAt,
                'updated_at'   => $syncedAt,
            ];
        }
        return $items;
    }

    private function fetchRss(string $url): string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER  => true,
            CURLOPT_USERAGENT       => 'Mozilla/5.0 (compatible; SunStockBot/1.0)',
            CURLOPT_TIMEOUT         => 15,
            CURLOPT_FOLLOWLOCATION  => true,
            CURLOPT_MAXREDIRS       => 3,
            CURLOPT_SSL_VERIFYPEER  => true,
            CURLOPT_PROTOCOLS       => CURLPROTO_HTTPS | CURLPROTO_HTTP,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS | CURLPROTO_HTTP,
        ]);
        $data  = curl_exec($ch);
        $error = curl_error($ch);
        $code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($error || $code < 200 || $code >= 300 || empty($data)) {
            throw new \RuntimeException("HTTP {$code}" . ($error ? ": {$error}" : ''));
        }
        return $data;
    }

    private function extractImage(\SimpleXMLElement $item): ?string
    {
        if (isset($item->enclosure)) {
            $a = $item->enclosure->attributes();
            if ($a && isset($a['url'])) return (string)$a['url'];
        }
        $media = $item->children('media', true);
        if (isset($media->content)) {
            $a = $media->content->attributes();
            if ($a && isset($a['url'])) return (string)$a['url'];
        }
        if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/', (string)$item->description, $m)) return $m[1];
        return null;
    }
}