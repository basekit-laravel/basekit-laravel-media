<?php

declare(strict_types=1);

namespace BasekitLaravel\BasekitLaravelMedia\Support;

use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Database\Eloquent\Model;

/**
 * Whether the signed-in user may use the media admin, and how that question is
 * answered.
 *
 * A policy the application registered for `Media` or `MediaFolder` is asked first,
 * so the package never has to know an application's permission scheme. Otherwise
 * the gate name under `permissions` is asked, and a `null` name allows: the
 * permissive default, so installing the package never locks an existing panel out
 * of its own media library.
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
     * The abilities the admin screen gates, for a view that renders a button per
     * ability rather than one button that is right or wrong for everybody.
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
     * The server-side half of a gate: the button being hidden is a convenience, so
     * the action that runs behind it asks again.
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

        $user = Filament::auth()->user();

        return $user === null ? $gate : $gate->forUser($user);
    }
}
