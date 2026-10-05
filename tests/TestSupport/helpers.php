<?php

declare(strict_types=1);

use BasekitLaravel\BasekitLaravelMedia\Filament\Pages\MediaLibrary;
use BasekitLaravel\BasekitLaravelMedia\Models\Media;
use BasekitLaravel\BasekitLaravelMedia\Models\MediaFolder;
use BasekitLaravel\BasekitLaravelMedia\Tests\TestSupport\Livewire\MediaPickerForm;
use BasekitLaravel\BasekitLaravelMedia\Tests\TestSupport\Models\User;
use Filament\Actions\Action;
use Illuminate\Contracts\View\View;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

if (! function_exists('mediaPage')) {
    /**
     * The media library page, mounted and ready to be asked questions.
     *
     * The helper is here so the tests reach the page's own actions with the page
     * typed, rather than through the untyped facade handle.
     *
     * @param  array<string, mixed>  $params
     * @return Testable<MediaLibrary>
     */
    function mediaPage(array $params = []): Testable
    {
        return Livewire::test(MediaLibrary::class, $params);
    }
}

if (! function_exists('pickerForm')) {
    /**
     * A form carrying nothing but the media picker, mounted and ready.
     *
     * The picker offers its upload as a form action, so the tests reach it
     * through the form it sits in rather than through a field built on its own,
     * which has nowhere to hang the action.
     *
     * @param  array<string, mixed>  $params
     * @return Testable<MediaPickerForm>
     */
    function pickerForm(array $params = []): Testable
    {
        return Livewire::test(MediaPickerForm::class, $params);
    }
}

if (! function_exists('makeMedia')) {
    /**
     * A media item with a real file on the configured testing disk.
     *
     * @param  array<string, mixed>  $attributes
     */
    function makeMedia(array $attributes = []): Media
    {
        $path = Storage::disk(Media::diskName())->putFile(
            'media',
            UploadedFile::fake()->image('photo.jpg', 64, 64),
        );

        return Media::factory()->create(array_merge(['path' => $path], $attributes));
    }
}

if (! function_exists('makeFolder')) {
    /**
     * A media folder, optionally nested.
     *
     * @param  array<string, mixed>  $attributes
     */
    function makeFolder(string $name = 'Fees', array $attributes = []): MediaFolder
    {
        return MediaFolder::factory()->create(array_merge(['name' => $name], $attributes));
    }
}

if (! function_exists('mountedActionArguments')) {
    /**
     * The arguments each rendered button passes to `mountAction()` for an action.
     *
     * The tests call `mountAction()` directly, which is why a button that renders
     * without its arguments — a tile that opens someone else's file, or nothing at
     * all — passes every behaviour test it should fail. Reading the arguments back
     * out of the markup is what holds the buttons to the same actions.
     *
     * Filament hands the arguments to `mountAction()` as
     * `JSON.parse('{...}')`, with the quotes of the JSON escaped as the unicode
     * escape `u0022` so that they survive the HTML attribute.
     *
     * @return array<int, array<string, mixed>>
     */
    function mountedActionArguments(string $html, string $action): array
    {
        preg_match_all(
            '/wire:click="mountAction\(\''.$action.'\', JSON\.parse\(\'(.*?)\'\)/',
            $html,
            $matches,
        );

        return array_map(
            static function (string $arguments): array {
                /** @var array<string, mixed> $decoded */
                $decoded = json_decode(str_replace('\u0022', '"', $arguments), true) ?? [];

                return $decoded;
            },
            $matches[1] ?? [],
        );
    }
}

if (! function_exists('mountedAction')) {
    /**
     * The action a component has mounted, with the arguments it was mounted with.
     *
     * The modal of a mounted action is served through its own Livewire partial
     * rather than being part of the page markup, so asserting on what the modal
     * says means asking the mounted action itself.
     *
     * @param  Testable<MediaLibrary>  $component
     */
    function mountedAction(Testable $component): Action
    {
        $mounted = $component->instance()->getMountedActions();

        $action = $mounted ? reset($mounted) : null;

        expect($action)->toBeInstanceOf(Action::class);

        return $action;
    }
}

if (! function_exists('actionModalContent')) {
    /**
     * The rendered content of an action's modal, above its form.
     *
     * The modal is served through its own Livewire partial rather than being part
     * of the page markup, so what a modal says about a file is asked of the action
     * itself, with the arguments the tile would have passed it.
     *
     * @param  Testable<MediaLibrary>  $component
     * @param  array<string, mixed>  $arguments
     */
    function actionModalContent(Testable $component, string $action, array $arguments = []): string
    {
        $instance = $component->instance();

        expect(method_exists($instance, $action.'Action'))->toBeTrue();

        /** @var Action $built */
        $built = ($instance->{$action.'Action'})($arguments);

        $content = $built->getModalContent();

        if ($content === null) {
            return '';
        }

        return $content instanceof View
            ? $content->render()
            : $content->toHtml();
    }
}

if (! function_exists('breadcrumbs')) {
    /**
     * The rendered breadcrumb trail, as its labels and the targets it links to.
     *
     * @return array{labels: array<int, string>, links: array<int, string>}
     */
    function breadcrumbs(string $html): array
    {
        preg_match('/<nav[^>]*fi-breadcrumbs[^>]*>(.*?)<\/nav>/s', $html, $trail);
        $inner = $trail[1];

        preg_match_all('/class="fi-breadcrumbs-item-label"[^>]*>([^<]*)/', $inner, $labels);
        preg_match_all('/href="([^"]*)"/', $inner, $links);

        return [
            'labels' => array_map(
                static fn (string $label): string => trim(strip_tags($label)),
                $labels[1],
            ),
            'links' => $links[1],
        ];
    }
}

if (! function_exists('breadcrumbLabels')) {
    /**
     * The labels of the rendered breadcrumb trail, oldest first.
     *
     * @return array<int, string>
     */
    function breadcrumbLabels(string $html): array
    {
        return breadcrumbs($html)['labels'];
    }
}

if (! function_exists('breadcrumbLinks')) {
    /**
     * The targets the rendered breadcrumb trail links to, oldest first.
     *
     * @return array<int, string>
     */
    function breadcrumbLinks(string $html): array
    {
        return breadcrumbs($html)['links'];
    }
}

if (! function_exists('makeUser')) {
    /**
     * A user that can enter the panel.
     *
     * Filament asks the guard's user model for `canAccessPanel()` and refuses
     * anyone whose model does not implement `FilamentUser` outside the `local`
     * environment, so the tests need a real set of authentication columns rather
     * than a name-only row.
     *
     * @param  array<string, mixed>  $attributes
     */
    function makeUser(string $email = 'editor@example.com', array $attributes = []): User
    {
        return User::query()->create(array_merge([
            'name' => 'Editor',
            'email' => $email,
            'password' => 'password',
            'email_verified_at' => now(),
        ], $attributes));
    }
}
