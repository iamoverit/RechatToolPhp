<?php

namespace Rechat;

use Carbon\Carbon;
use Carbon\CarbonInterval;
use GuzzleHttp\Exception\GuzzleException;
use MongoDB\Driver\Exception\BulkWriteException;
use NewTwitchApi\HelixGuzzleClient;
use NewTwitchApi\NewTwitchApi;

require __DIR__.'/../vendor/autoload.php';

class Main
{
    public static function run($vodId)
    {
        $mongoClient = new \MongoDB\Client(
            'mongodb://mongo/test?retryWrites=true&w=majority'
        );

        $videoCursor = $mongoClient->test
            ->selectCollection('videoCursors')
            ->findOne(['_id' => $vodId]);

        $rechatContentGenerator = Rechat::downloadFile($vodId, $videoCursor->nextCursor ?? null);
        foreach ($rechatContentGenerator as $content) {
            try {
                if (!empty($content->comments)) {
                    $mongoClient->test
                        ->selectCollection($vodId)
                        ->insertMany($content->comments, ['ordered' => false]);
                }
            } catch (BulkWriteException $bulkWriteException) {
                // skip
            }
            if (isset($content->_next)) {
                $mongoClient->test
                    ->selectCollection('videoCursors')
                    ->updateOne(
                        ['_id' => $vodId,],
                        ['$set' => ['nextCursor' => $content->_next,],],
                        ['upsert' => true]
                    );
            }
        }

    }

    public static function searchUserInChannel(
        string $channelName = 'tayga_play',
        ?int $last = null,
        ?array $searchUserNames = [],
        ?array $searchMessages = []
    ): string {
        $mongoClient = new \MongoDB\Client('mongodb://mongo/test?retryWrites=true&w=majority');
        // TODO: extract this parameters to .env
        $clientId = getenv('TWITCH_APP_CLIENT_ID');
        $clientSecret = getenv('TWITCH_APP_CLIENT_SECRET');
        $helixGuzzleClient = new HelixGuzzleClient($clientId, ['proxy' => getenv('PROXY')]);

        // Instantiate NewTwitchApi. Can be done in a service layer and injected as well.
        $newTwitchApi = new NewTwitchApi($helixGuzzleClient, $clientId, $clientSecret);
        try {
            $userResponse = \GuzzleHttp\json_decode(
                $newTwitchApi->getUsersApi()->getUserByUsername($channelName)->getBody()->getContents()
            );

            $tmpfname = tempnam(__DIR__."/../tmp", "rechat");
            $handle = fopen($tmpfname, "w");
            $videosResponseData = [];
            foreach (getVideosResponse($newTwitchApi, $userResponse, $last) as $videosResponse) {
                $videosResponseData = array_merge($videosResponseData, $videosResponse->data);
            }
            if ($last !== null) {
                $videosResponseData = array_slice($videosResponseData, 0, $last);
            }

            $searchCondition = self::getSearchCondition($searchUserNames, $searchMessages);

            $aggregatePipeline = self::getAggregatePipline($searchCondition);

            foreach ($videosResponseData as $vod) {
                $videoCursors = $mongoClient->test->selectCollection('videoCursors');
                if (!($videoCursors->findOne(['_id' => $vod->id])->isCompleted ?? false)) {
                    Main::run($vod->id);
                }

                $vodCreatedAt = Carbon::parse($vod->created_at)->add(parseVodDurationInterval($vod->duration));
                $isCompleted = ($vodCreatedAt->diffInMinutes(Carbon::now()) > 5);

                $videoCursors->updateOne(
                    ['_id' => $vod->id,],
                    ['$set' => ['isCompleted' => $isCompleted,],],
                    ['upsert' => true]
                );

                $commenterNameLength = $mongoClient->test->selectCollection($vod->id)->aggregate($aggregatePipeline);
                $commenterNameLengthMax = 0;
                foreach ($commenterNameLength as $item) {
                    $commenterNameLengthMax = max($commenterNameLengthMax, (int)$item->length);
                }

                $chatRows = $mongoClient->test->selectCollection($vod->id)->find($searchCondition);
                foreach ($chatRows as $chatRow) {
                    fputs(
                        $handle,
                        $vod->id.': '
                        .Carbon::createFromTimeString(substr($chatRow->created_at, 0, 15))
                            ->isoFormat('DD/MM/YY HH:mm').': '
                        .str_pad($chatRow->commenter->name, $commenterNameLengthMax, ' ').': '
                        .$chatRow->message->body.PHP_EOL
                    );
                }
            }
            fclose($handle);

            return \GuzzleHttp\json_encode(
                [
                    'channelName' => $channelName,
                    'searchUserName' => $searchUserNames,
                    'searchMessages' => $searchMessages,
                    'tmpfilename' => $tmpfname,
                ]
            );

        } catch (GuzzleException $e) {
            // Handle error appropriately for your application
            fputs(STDERR, "{$e->getMessage()}");
            fputs(STDERR, "{$e->getTraceAsString()}");
            throw $e;
        }
    }

    /**
     * @param array|null $searchUserNames
     * @param array|null $searchMessages
     * @return array
     */
    protected static function getSearchCondition(?array $searchUserNames, ?array $searchMessages): array
    {
        $userSearchConditions = [];
        foreach ($searchUserNames as $searchUserName) {
            $userSearchConditions[] = ['commenter.name' => strtolower($searchUserName)];
        }

        $messageSearchConditions = [];
        foreach ($searchMessages as $searchMessage) {
            $messageSearchConditions[] = [
                'message.body' => [
                    '$regex' => '.*'.$searchMessage.'.*',
                    '$options' => 'is',
                ],
            ];
        }

        $searchCondition = [];
        if (count($userSearchConditions) > 0) {
            $userSearchCondition = ['$or' => $userSearchConditions];
            $searchCondition = $userSearchCondition;
        }

        if (count($messageSearchConditions) > 0) {
            $messageSearchCondition = ['$or' => $messageSearchConditions];
            $searchCondition = $messageSearchCondition;
        }

        if (isset($userSearchCondition) && isset($messageSearchCondition)) {
            $searchCondition = ['$and' => [$userSearchCondition, $messageSearchCondition]];
        }

        return $searchCondition;
    }

    /**
     * @param array $searchCondition
     * @return array
     */
    protected static function getAggregatePipline(array $searchCondition): array
    {
        $aggregatePipeline = [];
        if ($searchCondition != []) {
            $aggregatePipeline [] = ['$match' => $searchCondition];
        }
        $aggregatePipeline [] = [
            '$group' => [
                '_id' => 'null',
                'length' => ['$max' => ['$strLenCP' => '$commenter.name']],
            ],
        ];

        return $aggregatePipeline;
    }
}

function parseVodDurationInterval(string $duration): CarbonInterval
{
    preg_match('/(\d*?)h?(\d*?)m?(\d*?)s/', $duration, $matches);
    array_reverse($matches);
    $ci = new CarbonInterval(0);
    $ci->seconds(array_pop($matches))
        ->minutes(array_pop($matches))
        ->hours(array_pop($matches));

    return $ci;
}

function getVideosResponse(NewTwitchApi $newTwitchApi, $userResponse, ?int $limit)
{
    $nextCursor = null;
    $count = 0;
    do {
        $videosResponse = \GuzzleHttp\json_decode(
            $newTwitchApi->getVideosApi()->getVideos(
                [],
                $userResponse->data[0]->id,
                null,
                null,
                null,
                $nextCursor,
                )->getBody()->getContents()
        );
        $count += count($videosResponse->data);
        $nextCursor = (string)($videosResponse->pagination->cursor ?? null);
        yield $videosResponse;

    } while (($nextCursor != null) && ($limit !== null) && ($count <= $limit));
}