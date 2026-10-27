<?php

namespace App\Support;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * The browsers an account is logged in on: the rows of the database session store that carry its
 * id. A row is named in forms by a hash of its id, never by the id, which is the session's secret.
 */
final class UserSessions
{
    /**
     * @return list<array{key: string, device: string, ip: ?string, lastActive: CarbonImmutable, current: bool}>
     */
    public static function forUser(User $user, string $currentId): array
    {
        $sessions = [];
        $rows = DB::table('sessions')
            ->where('user_id', $user->id)
            ->orderByDesc('last_activity')
            ->get(['id', 'ip_address', 'user_agent', 'last_activity']);
        foreach ($rows as $row) {
            $sessions[] = [
                'key' => self::key((string) $row->id),
                'device' => Device::describe(is_string($row->user_agent) ? $row->user_agent : null),
                'ip' => is_string($row->ip_address) ? $row->ip_address : null,
                'lastActive' => CarbonImmutable::createFromTimestamp((int) $row->last_activity),
                'current' => hash_equals((string) $row->id, $currentId),
            ];
        }

        return $sessions;
    }

    /**
     * Logs out the browser whose key is given, unless it's this one. True when one was found.
     */
    public static function end(User $user, string $key, string $currentId): bool
    {
        foreach (DB::table('sessions')->where('user_id', $user->id)->pluck('id') as $id) {
            if (hash_equals(self::key($id), $key) && ! hash_equals($id, $currentId)) {
                DB::table('sessions')->where('id', $id)->delete();

                return true;
            }
        }

        return false;
    }

    /**
     * Logs out every browser of the account except $keepId (null: all of them).
     */
    public static function endOthers(User $user, ?string $keepId): int
    {
        return DB::table('sessions')
            ->where('user_id', $user->id)
            ->when($keepId !== null, fn ($query) => $query->where('id', '!=', $keepId))
            ->delete();
    }

    private static function key(string $id): string
    {
        return hash('sha256', $id);
    }
}
