<?php
namespace App\Services;

class NewsService implements NewsServiceInterface
{
    protected $rssUrl = 'https://vnexpress.net/rss/kinh-doanh.rss';

    public function getLatestNews($limit = 8)
    {
        $news = [];
        $xmlString = $this->getRssContent($this->rssUrl);

        if (!$xmlString) return [];

        $rss = @simplexml_load_string($xmlString);
        foreach ($rss->channel->item as $item) {
            if (count($news) >= $limit) break;
            $news[] = [
                'title' => (string)$item->title,
                'link' => (string)$item->link,
                'pubDate' => date('d/m/Y H:i', strtotime((string)$item->pubDate)),
                'description' => (string)$item->description,
                'image' => isset($item->enclosure) && $item->enclosure->attributes() ? (string)$item->enclosure->attributes()->url : null,
            ];
        }

        return $news;
    }

    protected function getRssContent($url)
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; SunStockAI/1.0)');
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $data = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);
        if ($err) {
            return null;
        }
        return $data;
    }
}