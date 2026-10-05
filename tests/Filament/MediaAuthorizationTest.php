<?php

declare(strict_types=1);

use BasekitLaravel\BasekitLaravelMedia\Filament\MediaPicker;
use BasekitLaravel\BasekitLaravelMedia\Filament\Pages\MediaLibrary;
use BasekitLaravel\BasekitLaravelMedia\Models\Media;
use BasekitLaravel\BasekitLaravelMedia\Support\MediaAccess;
use BasekitLaravel\BasekitLaravelMedia\Tests\TestSupport\Models\User;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\Access\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('media'));
});

/**
 * A gate standing in for the permission an installation would register.
 *
 * A gate named after a plain ability is asked about the user and nothing else, so
 * the answer is fixed here rather than worked out from arguments it never gets.
 */
function gateAnswering(bool $answer): Closure
{
    return static fn (): Response => $answer ? Response::allow() : Response::deny();
}

/**
 * The picker, as the form carrying it built it.
 */
function picker(): MediaPicker
{
    $component = pickerForm()
        ->instance()
        ->getSchema('form')
        ?->getComponent('cover');

    if (! $component instanceof MediaPicker) {
        throw new LogicException('The media picker is missing from the test form.');
    }

    return $component;
}

/**
 * The picker's upload action, as the form carrying the picker sees it.
 */
function pickerUpload(): Action
{
    $action = picker()->getAction('upload');

    expect($action)->toBeInstanceOf(Action::class);

    return $action;
}

/**
 * What the picker renders: the field and its buttons, as a form shows them.
 */
function pickerHtml(): string
{
    return picker()->toEmbeddedHtml();
}

it('lets anyone in when the application has said nothing about permissions', function (): void {
    expect(MediaLibrary::canAccess())->toBeTrue()
        ->and(MediaAccess::all())->toBe([
            'view' => true,
            'create' => true,
            'update' => true,
            'delete' => true,
        ]);
});

it('keeps a user out of the library when the application gates it', function (): void {
    config(['basekit-laravel-media.permissions.view' => 'media.view']);
    Gate::define('media.view', gateAnswering(false));

    expect(MediaLibrary::canAccess())->toBeFalse();
});

it('lets a user in once the gate the application named allows them', function (): void {
    config(['basekit-laravel-media.permissions.view' => 'media.view']);
    Gate::define('media.view', gateAnswering(true));

    expect(MediaLibrary::canAccess())->toBeTrue();
});

it('leaves the buttons for abilities the application has not granted', function (): void {
    config([
        'basekit-laravel-media.permissions.view' => 'media.view',
        'basekit-laravel-media.permissions.create' => 'media.create',
        'basekit-laravel-media.permissions.update' => 'media.update',
        'basekit-laravel-media.permissions.delete' => 'media.delete',
    ]);

    Gate::define('media.view', gateAnswering(true));
    Gate::define('media.create', gateAnswering(true));

    makeMedia();

    $html = (string) mediaPage()->html();

    expect($html)->toContain('New folder');
    expect($html)->not->toContain('Edit');
    expect($html)->not->toContain('Move');
});

it('asks again on the server, so a hidden button is not the only thing in the way', function (): void {
    config(['basekit-laravel-media.permissions.delete' => 'media.delete']);
    Gate::define('media.delete', gateAnswering(false));

    $media = makeMedia();

    mediaPage()->mountAction('delete', ['id' => $media->getKey()])->callMountedAction();

    expect(Media::withTrashed()->count())->toBe(1)
        ->and(Media::query()->count())->toBe(1);
});

it('refuses a deletion the application has not granted', function (): void {
    config(['basekit-laravel-media.permissions.delete' => 'media.delete']);
    Gate::define('media.delete', gateAnswering(false));

    expect(fn () => MediaAccess::authorize('delete'))->toThrow(AuthorizationException::class);
});

it('refuses an upload the application has not granted', function (): void {
    config(['basekit-laravel-media.permissions.create' => 'media.create']);
    Gate::define('media.create', gateAnswering(false));

    $media = makeMedia();

    mediaPage()->mountAction('upload')
        ->set('mountedActions.0.data.path', [UploadedFile::fake()->image('cover.jpg')])
        ->callMountedAction();

    expect(Media::query()->count())->toBe(1)
        ->and(Media::query()->whereKey($media->getKey())->exists())->toBeTrue();
});

it('asks the policy the application registered for a media item, not the permission', function (): void {
    // The permission says yes and the policy says no. The policy is the closer
    // answer — it knows which row it is being asked about — so it is the one that
    // counts, and the configured name is never consulted for a record.
    config(['basekit-laravel-media.permissions.delete' => null]);
    Gate::policy(Media::class, DenyEverythingPolicy::class);

    $media = makeMedia();

    expect(MediaAccess::allows('delete', $media))->toBeFalse()
        ->and(fn () => MediaAccess::authorize('delete', $media))
        ->toThrow(AuthorizationException::class);
});

it('takes a file away from a drag the application has not granted', function (): void {
    Gate::policy(Media::class, DenyEverythingPolicy::class);

    $folder = makeFolder('Fees');
    $media = makeMedia();

    mediaPage()->call('moveMedia', $media->getKey(), $folder->getKey());

    expect($media->fresh()?->folder_id)->toBeNull();
});

it('files a file by drag once the application grants it', function (): void {
    Gate::policy(Media::class, AllowEverythingPolicy::class);

    $folder = makeFolder('Fees');
    $media = makeMedia();

    mediaPage()->call('moveMedia', $media->getKey(), $folder->getKey());

    expect($media->fresh()?->folder_id)->toBe($folder->getKey());
});

it('leaves the upload in the picker hidden when the application has not granted it', function (): void {
    config(['basekit-laravel-media.permissions.create' => 'media.create']);
    Gate::define('media.create', gateAnswering(false));

    expect(pickerUpload()->isVisible())->toBeFalse();
});

it('shows the upload in the picker once the application grants it', function (): void {
    config(['basekit-laravel-media.permissions.create' => 'media.create']);
    Gate::define('media.create', gateAnswering(true));

    expect(pickerUpload()->isVisible())->toBeTrue();
});

it('leaves the upload button out of the rendered picker rather than only marking it hidden', function (): void {
    config(['basekit-laravel-media.permissions.create' => 'media.create']);
    Gate::define('media.create', gateAnswering(false));

    // The action is marked hidden, and the field's own markup asks again before
    // drawing it: the markup is compiled separately from the class that renders
    // it, so it has to be handed the answer rather than going looking for it.
    expect(pickerHtml())->not->toContain('mountAction(\'upload\'');

    Gate::define('media.create', gateAnswering(true));

    expect(pickerHtml())->toContain('mountAction(\'upload\'');
});

class DenyEverythingPolicy
{
    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, Media $media): bool
    {
        return false;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Media $media): bool
    {
        return false;
    }

    public function delete(User $user, Media $media): bool
    {
        return false;
    }
}

class AllowEverythingPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Media $media): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Media $media): bool
    {
        return true;
    }

    public function delete(User $user, Media $media): bool
    {
        return true;
    }
}
