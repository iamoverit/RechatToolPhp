<?php
declare(strict_types=1);

namespace Rechat;

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;

const VERSION = '0.0.1beta';

class Rechat
{
    /**
     * @param int $videoId
     * @param string|null $nextCursor
     * @return \Generator
     */
    public static function downloadFile(int $videoId, ?string $nextCursor = null)
    {
        $baseUrl = "https://api.twitch.tv/v5/videos/{$videoId}/comments";
        $client = new Client();

        do {
            $url = $nextCursor == null ? "{$baseUrl}?content_offset_seconds=0" :
                "{$baseUrl}?cursor={$nextCursor}";
            $response = \GuzzleHttp\json_decode(
                $client->request(
                    'GET',
                    $url,
                    [
                        'headers' => [
                            'User-Agent' => 'RechatToolPhp/'.VERSION,
                            'Accept' => 'application/vnd.twitchtv.v5+json',
                            'Client-ID' => 'jzkbprff40iqj646a697cyrvl0zt2m6',
                        ],
                        'proxy' => getenv('PROXY'),
                    ]
                )->getBody()->getContents()
            );
            $nextCursor = (string)($response->_next ?? null);
            yield $response;

        } while ($nextCursor != null);
    }
}