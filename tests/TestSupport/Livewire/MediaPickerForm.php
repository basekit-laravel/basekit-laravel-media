<?php

declare(strict_types=1);

namespace BasekitLaravel\BasekitLaravelMedia\Tests\TestSupport\Livewire;

use BasekitLaravel\BasekitLaravelMedia\Filament\MediaPicker;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Livewire\Component;

/**
 * A bare form carrying nothing but the media picker.
 *
 * The picker offers its upload as a form action, so asking whether that action is
 * on offer needs the field to sit in a form: built on its own, the field has
 * nowhere to hang it.
 */
class MediaPickerForm extends Component implements HasSchemas
{
    use InteractsWithForms;

    /**
     * @var array<string, mixed>
     */
    public array $data = [];

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                MediaPicker::make('cover'),
            ])
            ->statePath('data');
    }

    public function render(): string
    {
        return '<div></div>';
    }
}
