<?php

declare(strict_types=1);

namespace App\Filament\Resources\Projects\RelationManagers;

use App\DataForSeo\Exceptions\DataForSeoBudgetExceededException;
use App\DataForSeo\KeywordData\EnrichKeywordVolumes;
use App\Filament\Imports\KeywordImporter;
use App\Models\Keyword;
use App\Models\Language;
use App\Models\Location;
use App\Models\Project;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ImportAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class KeywordsRelationManager extends RelationManager
{
    protected static string $relationship = 'keywords';

    public function form(Schema $schema): Schema
    {
        return $schema->components(self::keywordFormComponents());
    }

    /**
     * @return array<int, Component>
     */
    private static function keywordFormComponents(): array
    {
        return [
            TextInput::make('keyword')
                ->label(__('keywords.fields.keyword'))
                ->required()
                ->maxLength(255),
            self::locationSelect(),
            self::languageSelect(),
            TagsInput::make('tags')
                ->label(__('keywords.fields.tags')),
            Toggle::make('is_active')
                ->label(__('keywords.fields.is_active'))
                ->default(true)
                ->required(),
        ];
    }

    private static function locationSelect(): Select
    {
        return Select::make('location_code')
            ->label(__('keywords.fields.location_code'))
            ->options(fn () => Location::query()->orderBy('location_name')->limit(50)->pluck('location_name', 'location_code'))
            ->getSearchResultsUsing(fn (string $search) => Location::query()
                ->where('location_name', 'like', "%{$search}%")
                ->orderBy('location_name')
                ->limit(50)
                ->pluck('location_name', 'location_code'))
            ->getOptionLabelUsing(fn ($value) => Location::query()->find($value)?->location_name)
            ->searchable()
            ->required();
    }

    private static function languageSelect(): Select
    {
        return Select::make('language_code')
            ->label(__('keywords.fields.language_code'))
            ->options(fn () => Language::query()->orderBy('language_name')->pluck('language_name', 'language_code'))
            ->searchable()
            ->required();
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('keyword')
            ->columns([
                TextColumn::make('keyword')
                    ->label(__('keywords.fields.keyword'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('location_code')
                    ->label(__('keywords.fields.location_code'))
                    ->formatStateUsing(function (?int $state) {
                        if ($state === null) {
                            return null;
                        }

                        return Location::query()->find($state)->location_name ?? $state;
                    })
                    ->sortable(),
                TextColumn::make('language_code')
                    ->label(__('keywords.fields.language_code')),
                TextColumn::make('tags')
                    ->label(__('keywords.fields.tags'))
                    ->badge(),
                TextColumn::make('search_volume')
                    ->label(__('keywords.fields.search_volume'))
                    ->numeric()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('cpc')
                    ->label(__('keywords.fields.cpc'))
                    ->money('usd')
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('volume_updated_at')
                    ->label(__('keywords.fields.volume_updated_at'))
                    ->dateTime()
                    ->since()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_active')
                    ->label(__('keywords.fields.is_active'))
                    ->boolean(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make(),
                self::bulkPasteAction(),
                ImportAction::make()
                    ->importer(KeywordImporter::class)
                    ->options(fn () => ['project_id' => $this->getOwnerRecord()->getKey()]),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                self::enrichVolumeBulkAction(),
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    private static function bulkPasteAction(): Action
    {
        return Action::make('bulkPaste')
            ->label(__('keywords.bulk_paste.action'))
            ->modalHeading(__('keywords.bulk_paste.modal_heading'))
            ->form([
                Textarea::make('keywords_raw')
                    ->label(__('keywords.bulk_paste.keywords_raw'))
                    ->helperText(__('keywords.bulk_paste.keywords_raw_helper'))
                    ->rows(8)
                    ->required(),
                self::locationSelect(),
                self::languageSelect(),
                TagsInput::make('tags')
                    ->label(__('keywords.fields.tags')),
            ])
            ->modalSubmitActionLabel(__('keywords.bulk_paste.submit'))
            ->action(function (array $data, RelationManager $livewire): void {
                $lines = collect(preg_split('/\r\n|\r|\n/', (string) $data['keywords_raw']))
                    ->map(fn (string $line) => trim($line))
                    ->filter()
                    ->unique()
                    ->values();

                $created = 0;

                /** @var Project $project */
                $project = $livewire->getOwnerRecord();

                foreach ($lines as $line) {
                    $keyword = $project->keywords()->firstOrCreate([
                        'keyword' => $line,
                        'location_code' => $data['location_code'],
                        'language_code' => $data['language_code'],
                    ], [
                        'tags' => $data['tags'] ?? [],
                        'is_active' => true,
                    ]);

                    if ($keyword->wasRecentlyCreated) {
                        $created++;
                    }
                }

                Notification::make()
                    ->title("{$created} de {$lines->count()} keyword(s) agregadas (el resto ya existía).")
                    ->success()
                    ->send();
            });
    }

    private static function enrichVolumeBulkAction(): BulkAction
    {
        return BulkAction::make('enrichVolume')
            ->label(__('keywords.enrich.action'))
            ->requiresConfirmation()
            ->modalHeading(__('keywords.enrich.modal_heading'))
            ->modalDescription(function (Collection $records) {
                $estimate = config('dataforseo.search_volume_live_cost_estimate');

                return filled($estimate)
                    ? __('keywords.enrich.modal_description_with_estimate', ['count' => $records->count(), 'cost' => '$'.number_format((float) $estimate, 4)])
                    : __('keywords.enrich.modal_description_without_estimate', ['count' => $records->count()]);
            })
            ->modalSubmitActionLabel(__('keywords.enrich.submit'))
            ->action(function (Collection $records) {
                /** @var Collection<int, Keyword> $keywords */
                $keywords = $records;

                try {
                    $result = app(EnrichKeywordVolumes::class)->execute($keywords);
                } catch (DataForSeoBudgetExceededException) {
                    Notification::make()
                        ->title(__('keywords.enrich.budget_exceeded'))
                        ->danger()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title(__('keywords.enrich.success', ['updated' => $result->updated, 'requested' => $result->requested]))
                    ->success()
                    ->send();
            })
            ->deselectRecordsAfterCompletion();
    }
}
