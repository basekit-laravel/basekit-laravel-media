<?php

declare(strict_types=1);

namespace BasekitLaravel\BasekitLaravelMedia\Support;

use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Whether the signed-in user may use the media admin. A policy the application
 * registered for `Media` or `MediaFolder` wins; otherwise the gate name under
 * `permissions` is asked, and `null` allows so the default is permissive.
 */
final class MediaAccess
{
    /**
     * @param  'view'|'create'|'update'|'delete'  $ability
     */
    public static function allows(string $ability, ?Model $record = null): bool
    {
        $gate = self::gate();

        if ($record instanceof Model && $gate->getPolicyFor($record::class) !== null) {
            return $gate->allows($ability, $record);
        }

        $permission = config("basekit-laravel-media.permissions.{$ability}");

        return $permission === null || $gate->allows($permission);
    }

    /**
     * The abilities the admin screen gates, for a view that draws one button each.
     *
     * @return array<string, bool>
     */
    public static function all(?Model $record = null): array
    {
        $abilities = [];

        foreach (['view', 'create', 'update', 'delete'] as $ability) {
            $abilities[$ability] = self::allows($ability, $record);
        }

        return $abilities;
    }

    /**
     * Asks again on the server, because hiding a button is only a convenience.
     *
     * @param  'view'|'create'|'update'|'delete'  $ability
     *
     * @throws AuthorizationException
     */
    public static function authorize(string $ability, ?Model $record = null): void
    {
        if (self::allows($ability, $record)) {
            return;
        }

        throw new AuthorizationException("This action is unauthorized: {$ability}.");
    }

    private static function gate(): Gate
    {
        $gate = app(Gate::class);

        $user = self::currentUser();

        return $user === null ? $gate : $gate->forUser($user);
    }

    /**
     * The panel's guard inside Filament and the default guard outside it, since
     * this class is asked from `Support/` and has to answer without Filament too.
     */
    private static function currentUser(): ?Authenticatable
    {
        if (! class_exists(Filament::class)) {
            return Auth::user();
        }

        return Filament::auth()->user();
    }
}
