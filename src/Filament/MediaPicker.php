<?php

declare(strict_types=1);

namespace BasekitLaravel\BasekitLaravelMedia\Filament;

use BasekitLaravel\BasekitLaravelMedia\Filament\Concerns\ConfiguresMediaUpload;
use BasekitLaravel\BasekitLaravelMedia\Models\Media;
use BasekitLaravel\BasekitLaravelMedia\Support\MediaAccess;
use BasekitLaravel\BasekitLaravelMedia\Support\MediaWriter;
use Filament\Actions\Action;
use Filament\Forms\Components\ModalTableSelect;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\View\ComponentAttributeBag as FilamentComponentAttributeBag;

/**
 * A single-file picker for the media library, and a drop-in replacement for a URL
 * field: the form state works on media IDs while the editor picks, and the stored
 * value is the file's web URL, so consuming models keep the string they publish.
 *
 *     MediaPicker::make('featured_image')->columnSpan(2)
 *
 * Values that are URLs rather than IDs pass through untouched, so data placed
 * there before this field existed is never lost.
 */
class MediaPicker extends ModalTableSelect
{
    use ConfiguresMediaUpload;

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->placeholder(__('basekit-laravel-media::basekit-laravel-media.picker_placeholder'))
            ->tableConfiguration(MediaTableConfiguration::class)
            ->getOptionLabelUsing(function (MediaPicker $component): string {
                $media = $component->getSelectedRecord();

                if ($media instanceof Media) {
                    return (string) $media->name;
                }

                return (string) ($component->getState() ?? '');
            })
            ->getSelectedRecordUsing(fn (MediaPicker $component, mixed $state): ?Media => is_numeric($state) ? Media::find((int) $state) : null)
            ->afterStateHydrated(function (MediaPicker $component, mixed $state): void {
                $component->state(self::resolveKey($state));
            })
            ->dehydrateStateUsing(fn (mixed $state): mixed => self::resolveStoredValue($state))
            ->registerActions([$this->uploadAction()])
            ->selectAction(fn (Action $action, MediaPicker $component): Action => $action->extraModalFooterActions(
                fn (): array => [$component->getAction('upload')],
            ));
    }

    private function uploadAction(): Action
    {
        return Action::make('upload')
            ->label(__('basekit-laravel-media::basekit-laravel-media.upload'))
            ->icon('heroicon-o-arrow-up-tray')
            ->button()
            ->visible(fn (): bool => MediaAccess::allows('create'))
            ->modalHeading(__('basekit-laravel-media::basekit-laravel-media.upload'))
            ->schema([
                $this->mediaUpload(),
                TextInput::make('alt')
                    ->label(__('basekit-laravel-media::basekit-laravel-media.alt'))
                    ->maxLength(255),
            ])
            ->action(function (array $data, MediaPicker $component): void {
                MediaAccess::authorize('create');

                $media = app(MediaWriter::class)->create($data);

                $component->state((string) $media->getKey())->callAfterStateUpdated();

                Notification::make()
                    ->title(__('basekit-laravel-media::basekit-laravel-media.upload_success'))
                    ->success()
                    ->send();
            });
    }

    /**
     * The parent embedded view renders a single "Select" action and never its affix
     * actions, so the upload would be invisible. Render it as a labeled button
     * beside Select instead.
     */
    public function toEmbeddedHtml(): string
    {
        if ($this->isMultiple()) {
            return parent::toEmbeddedHtml();
        }

        $extraAttributes = $this->getExtraAttributes();
        $id = $this->getId();
        $isDisabled = $this->isDisabled();
        $hasBadges = $this->hasBadges();
        $badgeColor = $this->getBadgeColor();
        $optionLabel = $this->getOptionLabel();
        $placeholder = $this->getPlaceholder();

        $attributes = (new FilamentComponentAttributeBag)
            ->merge([
                'aria-labelledby' => "{$id}-label",
                'id' => $id,
                'role' => 'group',
            ], escape: false)
            ->merge($extraAttributes, escape: false)
            ->class([
                'fi-fo-modal-table-select',
                'fi-fo-modal-table-select-disabled' => $isDisabled,
            ]);

        // Asked here rather than inside the markup below: that markup is compiled on its
        // own and knows nothing about the imports of this class.
        $canUpload = MediaAccess::allows('create');

        ob_start(); ?>

        <div <?= $attributes->toHtml() ?>>
            <?php if (filled($optionLabel)) { ?>
                <div>
                    <?php if ($hasBadges) { ?>
                        <?= $this->generateBadgeHtml($optionLabel, $badgeColor) ?>
                    <?php } else { ?>
                        <?= e($optionLabel) ?>
                    <?php } ?>
                </div>
            <?php } elseif (filled($placeholder)) { ?>
                <div class="fi-fo-modal-table-select-placeholder">
                    <?= e($placeholder) ?>
                </div>
            <?php } ?>

            <?php if (! $isDisabled) { ?>
                <div class="fi-fo-media-picker-actions">
                    <?php if ($canUpload) { ?>
                        <?= $this->getAction('upload')->toHtml() ?>
                    <?php } ?>

                    <?= $this->getAction('select')->toHtml() ?>
                </div>
            <?php } ?>
        </div>

        <?php return $this->wrapEmbeddedHtml(ob_get_clean(), labelTag: 'div');
    }

    /**
     * There is no model relationship behind this field, so the relationship hooks
     * `ModalTableSelect` wires up would crash on an empty selection.
     */
    public function fillStateFromRelationship(): void {}

    /**
     * There is no model relationship behind this field, so there is nothing to
     * write through a related record when the form is saved.
     */
    public function saveStateToRelationship(): void {}

    /**
     * The media ID the field works on while editing. A stored URL of a known item
     * resolves back to its ID; anything else is kept verbatim so it is never lost.
     *
     * @param  mixed  $value  the stored value (web URL or media ID)
     */
    public static function resolveKey(mixed $value): mixed
    {
        if (blank($value)) {
            return null;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        return Media::findByStorageValue((string) $value)->id ?? $value;
    }

    /**
     * The stored value of the field: the web URL of the chosen item. Non-numeric
     * state is already a stored value and passes through.
     *
     * @param  mixed  $value  the form state (media ID, web URL or blank)
     */
    public static function resolveStoredValue(mixed $value): mixed
    {
        if (blank($value)) {
            return null;
        }

        if (! is_numeric($value)) {
            return (string) $value;
        }

        return Media::find((int) $value)?->storageUrl() ?? (string) $value;
    }
}
