<?php

declare(strict_types=1);

namespace BasekitLaravel\BasekitLaravelMedia\Database\Factories;

use BasekitLaravel\BasekitLaravelMedia\Models\Media;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Media>
 */
class MediaFactory extends Factory
{
    protected $model = Media::class;

    public function definition(): array
    {
        return [
            'disk' => Media::diskName(),
            'path' => 'media/'.Str::random(40).'.jpg',
            'name' => 'image.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 1024,
            'alt' => null,
        ];
    }
}
