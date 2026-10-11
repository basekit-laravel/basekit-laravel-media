<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

it('keeps its stylesheets in files rather than inlining them in a view', function (): void {
    $inlined = [];

    foreach (File::allFiles(__DIR__.'/../../resources/views') as $view) {
        if (str_contains($view->getContents(), '<style')) {
            $inlined[] = $view->getRelativePathname();
        }
    }

    // The screen's CSS is registered as a Filament asset so it ships in one
    // place and can be cached with the rest of the panel's styles. A `<style>`
    // block put back into a view would quietly opt that view out.
    expect($inlined)->toBe([]);
});

it('ships the stylesheets built rather than as sources', function (): void {
    $dist = __DIR__.'/../../resources/css/dist';
    $src = __DIR__.'/../../resources/css/src';

    // One built file per entry: a new entry under src/ without its built
    // counterpart would ship as nothing and nobody would notice.
    foreach (File::files($src) as $entry) {
        expect(File::exists($dist.'/'.$entry->getFilename()))->toBeTrue();
    }

    // Only dist ships, so it has to be what `npm run css:dist` produces. An
    // `@import` left in a built file means the sources were never inlined.
    foreach (File::allFiles($dist) as $built) {
        expect($built->getContents())->not->toContain('@import');
    }
});
