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
