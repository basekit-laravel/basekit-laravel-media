<?php

declare(strict_types=1);

namespace BasekitLaravel\BasekitLaravelMedia\Database\Factories;

use BasekitLaravel\BasekitLaravelMedia\Models\MediaFolder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MediaFolder>
 */
class MediaFolderFactory extends Factory
{
    protected $model = MediaFolder::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->words(2, true),
        ];
    }
}
