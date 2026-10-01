<?php

declare(strict_types=1);

namespace BasekitLaravel\BasekitLaravelMedia\Filament;

use BasekitLaravel\BasekitLaravelMedia\Models\Media;
use BasekitLaravel\BasekitLaravelMedia\Support\MediaWriter;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\ModalTableSelect;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\View\ComponentAttributeBag as FilamentComponentAttributeBag;

/**
 * A single-file picker for the media library. It is a drop-in replacement for
 * a URL field: the form state works on media IDs while the editor picks, and
 * the stored value is the file's web URL, so consuming models keep the string
 * they already publish. Legacy values that are URLs rather than IDs are shown
 * as-is and saved back unchanged. The picker carries a visible upload action,
 * both on the field itself and inside the media-selection modal, so an editor
 * can add a new media item without leaving the form:
 *
 *     MediaPicker::make('featured_image')->columnSpan(2)
 */
class MediaPicker extends ModalTableSelect
{
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
            ->modalHeading(__('basekit-laravel-media::basekit-laravel-media.upload'))
            ->schema([
                FileUpload::make('path')
                    ->label(__('basekit-laravel-media::basekit-laravel-media.file'))
                    ->disk(Media::diskName())
                    ->directory(Media::uploadDirectory())
                    ->image()
                    ->acceptedFileTypes(['image/*'])
                    ->storeFileNamesIn('name')
                    ->required(),
                TextInput::make('alt')
                    ->label(__('basekit-laravel-media::basekit-laravel-media.alt'))
                    ->maxLength(255),
            ])
            ->action(function (array $data, MediaPicker $component): void {
                $media = app(MediaWriter::class)->create($data);

                $component->state((string) $media->getKey())->callAfterStateUpdated();

                Notification::make()
                    ->title(__('basekit-laravel-media::basekit-laravel-media.upload_success'))
                    ->success()
                    ->send();
            });
    }

    /**
     * The parent embedded view renders a single "Select" action, so the upload
     * would be invisible. Render it as a labeled button beside the Select
     * action instead.
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
                    <?= $this->getAction('upload')->toHtml() ?>
                    <?= $this->getAction('select')->toHtml() ?>
                </div>
            <?php } ?>
        </div>

        <?php return $this->wrapEmbeddedHtml(ob_get_clean(), labelTag: 'div');
    }

    /**
     * The picker is not backed by a model relationship, so the relationship
     * helpers the table select wires itself with would crash on an empty
     * selection when there is no relationship to resolve.
     */
    public function fillStateFromRelationship(): void {}

    /**
     * The picker is not backed by a model relationship, so there is nothing to
     * write through the related record when the form is saved.
     */
    public function saveStateToRelationship(): void {}

    /**
     * The media ID the field works on while editing. A stored URL of an
     * existing media item resolves back to its ID; anything else is kept
     * verbatim so it is never lost on save.
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
     * The stored value of the field: the web URL of the chosen media item.
     * Non-numeric state is already a stored value and passes through.
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
