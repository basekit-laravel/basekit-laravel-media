<?php

declare(strict_types=1);

namespace BasekitLaravel\BasekitLaravelMedia\Tests;

use BasekitLaravel\BasekitLaravelMedia\Tests\TestSupport\FilamentProviders;

/**
 * The admin suite runs against the framework only. Filament registers its
 * providers through package discovery, which Testbench does not run, so the
 * admin tests opt in to the same providers an application gets.
 */
abstract class FilamentTestCase extends TestCase
{
    /**
     * @return array<int, class-string>
     */
    #[\Override]
    protected function getPackageProviders($app): array
    {
        return [
            ...parent::getPackageProviders($app),
            ...FilamentProviders::all(),
            TestSupport\MediaPanelProvider::class,
        ];
    }
}
