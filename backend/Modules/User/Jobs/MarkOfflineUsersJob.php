<?php

namespace Modules\User\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Redis;
use Modules\User\Models\ExtData;

class MarkOfflineUsersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 60;
    public int $tries = 2;
    public int $backoff = 10;

    public function handle(): void
    {
        $key = 'users:last_seen';
        $cutoff = now()->timestamp - 60;
        $batchSize = 200;
        $maxLoops = 100;
        $loop = 0;

        while (true) {
            $loop++;
            if ($loop > $maxLoops) {
                break;
            }

            $offlineUsers = Redis::zrangebyscore($key, '-inf', (string) $cutoff, [
                'withscores' => true,
                'limit' => [0, $batchSize],
            ]);

            if (empty($offlineUsers)) {
                break;
            }

            $offlineCandidateIds = array_map('intval', array_keys($offlineUsers));
            $latestPresenceByUser = ExtData::whereIn('f_id', $offlineCandidateIds)
                ->whereIn('kind', ['offline', 'online'])
                ->orderByDesc('id')
                ->get(['f_id', 'kind'])
                ->unique('f_id')
                ->keyBy('f_id');

            $data = [];
            $processedCount = 0;

            foreach ($offlineUsers as $userId => $lastSeen) {
                $userIdInt = (int) $userId;
                $latestPresence = $latestPresenceByUser->get($userIdInt);
                if ($latestPresence && $latestPresence->kind === 'offline') {
                    continue;
                }

                $removed = (int) Redis::eval(
                    <<<'LUA'
                    local key = KEYS[1]
                    local member = ARGV[1]
                    local expected = tonumber(ARGV[2])
                    local current = redis.call('ZSCORE', key, member)
                    if (not current) then
                    return 0
                    end
                    if (tonumber(current) ~= expected) then
                    return 0
                    end
                    return redis.call('ZREM', key, member)
                    LUA,
                    1,
                    $key,
                    (string) $userId,
                    (string) ((int) $lastSeen)
                );

                // If score changed, user became active again; skip offline record.
                if ($removed !== 1) {
                    continue;
                }

                $data[] = [
                    'kind' => 'offline',
                    'f_id' => $userIdInt,
                    'title' => 'کاربر افلاین شد',
                    'datas' => json_encode([
                        'last_seen' => date('Y-m-d H:i:s', $lastSeen),
                        'last_seen_shamsi' => jdate($lastSeen)->format('Y-m-d H:i:s'),
                    ]),
                    'status' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                $processedCount++;
            }

            if (!empty($data)) {
                ExtData::insert($data);
                unset($data);
            }

            if (count($offlineUsers) < $batchSize) {
                break;
            }

            // Safety: prevent re-looping forever when no member is processable.
            if ($processedCount === 0) {
                break;
            }
        }



        // Get online users
        $onlineUsers = Redis::zrangebyscore($key, (string) ($cutoff + 1), '+inf', [
            'withscores' => true,
            'limit' => [0, $batchSize],
        ]);
        $ids = array_map('intval', array_keys($onlineUsers));
        if (!empty($ids)) {
            $latestPresenceByUser = ExtData::whereIn('f_id', $ids)
                ->whereIn('kind', ['offline', 'online'])
                ->orderByDesc('id')
                ->get(['f_id', 'kind'])
                ->unique('f_id');
            $users = $latestPresenceByUser
                ->filter(fn($row) => $row->kind === 'offline');

            if (!$users->isEmpty()) {
                $lastSeen = now()->timestamp;
                foreach ($users as $user) {
                    $data[] = [
                        'kind' => 'online',
                        'f_id' => (int) $user->f_id,
                        'title' => 'کاربر دوباره آنلاین شد',
                        'datas' => json_encode([
                            'last_seen' => date('Y-m-d H:i:s', $lastSeen),
                            'last_seen_shamsi' => jdate($lastSeen)->format('Y/m/d'),
                        ]),
                        'status' => 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }
        }

        if (!empty($data)) {
            ExtData::insert($data);
            unset($data);
        }
    }
}
