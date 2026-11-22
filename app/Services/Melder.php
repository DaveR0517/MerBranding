<?php

namespace App\Services;

use App\Models\MessageTemplate;
use App\Models\User;
use App\Notifications\PlatformMelding;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

/** Verstuurt meldingen volgens de berichtsjablonen. */
class Melder
{
    /**
     * @param  iterable<User>|User|null  $users
     * @param  array<string, mixed>  $vars
     */
    public function stuur(string $key, iterable|User|null $users, array $vars, string $url): void
    {
        $template = MessageTemplate::where('key', $key)->first();
        if (! $template) {
            Log::warning("Berichtsjabloon {$key} ontbreekt");

            return;
        }

        $users = collect($users instanceof User ? [$users] : ($users ?? []))->filter()->unique('id');

        foreach ($users as $user) {
            Notification::send($user, new PlatformMelding($template, ['voornaam' => Str::before($user->name, ' ')] + array_map('strval', $vars), $url));
        }
    }

    /** Teamleden met een bepaald recht, plus Super Admins. */
    public function team(string $permission): Collection
    {
        $viaPermission = Permission::where('name', $permission)->exists() ? User::permission($permission)->get() : collect();

        return $viaPermission->merge(User::role('Super Admin')->get())->unique('id');
    }
}
