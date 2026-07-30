<?php

namespace App\Services\Site;

use App\Models\YoutubeCredential;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * 24-hour-cached YouTube channel videos fetcher.
 *
 * Why the cache is mandatory:
 *  - YouTube Data API v3 default quota = 10,000 units / day per project
 *  - Each search.list call = 100 units → 100 page-views = quota exhausted
 *  - Pre-cache implementation: every public landing-page visit fired the
 *    API → an active coach with even modest traffic burnt all quota by 11 AM
 *
 * Cache key: youtube:videos:{channel_id}    TTL 86400s
 * Stale-on-failure: if the next refresh fails, we keep serving the old
 *                   list via a long-lived backup key (90 days).
 */
class YouTubeFetcher
{
    private const TTL      = 86400;           // 24 h fresh cache
    private const TTL_LONG = 86400 * 90;      // 90 d stale fallback

    /**
     * Returns array of: [ ['id' => …, 'title' => …, 'thumbnail' => …], … ]
     */
    public function latestVideos(string $channelId, ?int $coachId = null, int $max = 9): array
    {
        $freshKey = "youtube:videos:{$channelId}";
        $longKey  = "youtube:videos-long:{$channelId}";

        $cached = Cache::get($freshKey);
        if (is_array($cached)) {
            return $cached;
        }

        // Try to refresh
        $apiKey = $this->apiKeyForCoach($coachId);
        if (! $apiKey) {
            // No key → return long-lived backup if any, else empty
            return Cache::get($longKey, []);
        }

        try {
            $response = Http::timeout(5)->get('https://www.googleapis.com/youtube/v3/search', [
                'key'        => $apiKey,
                'channelId'  => $channelId,
                'part'       => 'snippet,id',
                'order'      => 'date',
                'type'       => 'video',
                'maxResults' => $max,
            ]);

            if (! $response->ok()) {
                Log::warning('youtube-fetch-failed', [
                    'channel' => $channelId,
                    'status'  => $response->status(),
                    'body'    => substr($response->body(), 0, 300),
                ]);
                return Cache::get($longKey, []);
            }

            $items = $response->json('items') ?? [];
            $videos = [];
            foreach ($items as $item) {
                $videoId = $item['id']['videoId'] ?? null;
                if (! $videoId) {
                    continue;
                }
                $videos[] = [
                    'id'        => $videoId,
                    'title'     => $item['snippet']['title'] ?? '',
                    'thumbnail' => $item['snippet']['thumbnails']['high']['url']
                                ?? $item['snippet']['thumbnails']['medium']['url']
                                ?? $item['snippet']['thumbnails']['default']['url']
                                ?? '',
                    'published' => $item['snippet']['publishedAt'] ?? null,
                ];
            }

            Cache::put($freshKey, $videos, self::TTL);
            Cache::put($longKey, $videos, self::TTL_LONG);
            return $videos;
        } catch (\Throwable $e) {
            Log::warning('youtube-fetch-exception', [
                'channel' => $channelId,
                'error'   => $e->getMessage(),
            ]);
            return Cache::get($longKey, []);
        }
    }

    /**
     * Resolution order:
     *   1. The coach's own row in youtube_credentials (if api_key set)
     *   2. The platform-wide YOUTUBE_API_KEY env var (config('services.youtube.api_key'))
     *   3. null (fetcher returns long-lived stale cache, else [])
     *
     * The coach-specific key always wins so heavy users can isolate their
     * quota; the platform fallback exists so a fresh coach can drop a
     * channel ID into the editor and have it Just Work without registering
     * a Google Cloud project first.
     */
    private function apiKeyForCoach(?int $coachId): ?string
    {
        if ($coachId) {
            $cred = YoutubeCredential::where('instructor_id', $coachId)->first();
            if ($cred && ! empty($cred->api_key)) {
                return $cred->api_key;
            }
        }

        $platformKey = config('services.youtube.api_key');
        return ! empty($platformKey) ? $platformKey : null;
    }

    /**
     * Operator helper: invalidate the cache for a coach (e.g. when they
     * click "Refresh YouTube" in the editor). Rate-limited at the
     * controller; this method itself is unconditional.
     */
    public function invalidate(string $channelId): void
    {
        Cache::forget("youtube:videos:{$channelId}");
    }
}
