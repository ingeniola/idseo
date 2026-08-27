<?php

declare(strict_types=1);

namespace App\Filament\Resources\Projects\RelationManagers;

use App\Audit\AuditLogger;
use App\DataForSeo\Enums\DataForSeoTaskStatus;
use App\DataForSeo\Exceptions\DataForSeoBudgetExceededException;
use App\DataForSeo\KeywordData\EnrichKeywordVolumes;
use App\Enums\AuditEvent;
use App\Filament\Imports\KeywordImporter;
use App\Jobs\ScheduleRankTrackingTasks;
use App\Models\Keyword;
use App\Models\Language;
use App\Models\Location;
use App\Models\Project;
use App\Models\Ranking;
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
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\Indicator;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\RateLimiter;

class KeywordsRelationManager extends RelationManager
{
    protected static string $relationship = 'keywords';

    /**
     * Filament no llama a formatStateUsing()/description() cuando el
     * estado base de un TextColumn es blank (toma un atajo que solo
     * pinta el placeholder) — justo el caso que hay que distinguir
     * aquí ("sin datos todavía" vs. "en camino"). Un valor centinela
     * no-blank fuerza el camino de formato completo.
     */
    private const PROCESSING_STATE = '__rank_tracking_processing__';

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
            ->options(fn () => Language::query()->where('valid_for_google_ads_keywords', true)->orderBy('language_name')->pluck('language_name', 'language_code'))
            ->searchable()
            ->required();
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('keyword')
            ->modifyQueryUsing(fn (Builder $query) => $query->with('latestRanking')->withExists([
                // Indicador de "procesando" en la columna de posición
                // (sección de UX): sin esto, una keyword recién creada
                // o esperando el resultado de un task_post Standard se
                // ve idéntica a una que nunca se ha rastreado — nada
                // distingue "sin datos todavía" de "en camino".
                'dataForSeoTasks as has_pending_rank_tracking_task' => fn (Builder $query) => $query
                    ->where('endpoint', ScheduleRankTrackingTasks::ENDPOINT)
                    ->where('status', DataForSeoTaskStatus::Pending),
            ]))
            ->columns([
                TextColumn::make('keyword')
                    ->label(__('keywords.fields.keyword'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('latestRanking.position')
                    ->label(__('keywords.fields.current_position'))
                    ->numeric()
                    ->getStateUsing(fn (Keyword $record) => $record->latestRanking?->position
                        ?? ($record->has_pending_rank_tracking_task ? self::PROCESSING_STATE : null))
                    ->formatStateUsing(fn ($state) => $state === self::PROCESSING_STATE
                        ? __('keywords.fields.rank_tracking_processing')
                        : $state)
                    ->color(fn ($state) => $state === self::PROCESSING_STATE ? 'warning' : null)
                    ->placeholder('—')
                    ->sortable(query: fn (Builder $query, string $direction) => $query->orderBy(
                        Ranking::query()
                            ->select('position')
                            ->whereColumn('rankings.keyword_id', 'keywords.id')
                            ->orderByDesc('checked_at')
                            ->limit(1),
                        $direction,
                    )),
                TextColumn::make('position_change')
                    ->label(__('keywords.fields.position_change'))
                    ->getStateUsing(function (Keyword $record) {
                        $latest = $record->latestRanking;

                        if ($latest === null || $latest->position === null || $latest->previous_position === null) {
                            return null;
                        }

                        return $latest->previous_position - $latest->position;
                    })
                    ->formatStateUsing(fn (?int $state) => match (true) {
                        $state === null => '—',
                        $state > 0 => "▲ {$state}",
                        $state < 0 => '▼ '.abs($state),
                        default => __('keywords.movement.same'),
                    })
                    ->color(fn (?int $state) => match (true) {
                        $state === null => 'gray',
                        $state > 0 => 'success',
                        $state < 0 => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('latestRanking.url')
                    ->label(__('keywords.fields.ranking_url'))
                    ->url(fn (Keyword $record) => $record->latestRanking?->url, shouldOpenInNewTab: true)
                    ->limit(40)
                    ->placeholder('—'),
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
                TextColumn::make('latestRanking.serp_features')
                    ->label(__('keywords.fields.serp_features'))
                    ->badge()
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
                $this->tagsFilter(),
                self::positionRangeFilter(),
                self::movementFilter(),
            ])
            ->headerActions([
                CreateAction::make(),
                self::bulkPasteAction(),
                ImportAction::make()
                    ->importer(KeywordImporter::class)
                    ->options(fn () => ['project_id' => $this->getOwnerRecord()->getKey()]),
            ])
            ->recordActions([
                self::rankingHistoryAction(),
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

    private function tagsFilter(): SelectFilter
    {
        return SelectFilter::make('tags')
            ->label(__('keywords.filters.tags'))
            ->multiple()
            ->options(fn () => $this->tagFilterOptions())
            ->query(function (Builder $query, array $data) {
                $values = $data['values'] ?? [];

                if (blank($values)) {
                    return $query;
                }

                return $query->where(function (Builder $query) use ($values) {
                    foreach ($values as $tag) {
                        $query->orWhereJsonContains('tags', $tag);
                    }
                });
            });
    }

    /**
     * @return array<string, string>
     */
    private function tagFilterOptions(): array
    {
        /** @var Project $project */
        $project = $this->getOwnerRecord();

        return $project->keywords()
            ->whereNotNull('tags')
            ->pluck('tags')
            ->flatten()
            ->filter()
            ->unique()
            ->sort()
            ->mapWithKeys(fn (string $tag) => [$tag => $tag])
            ->all();
    }

    private static function positionRangeFilter(): Filter
    {
        return Filter::make('position_range')
            ->label(__('keywords.filters.position_range'))
            ->schema([
                TextInput::make('min')
                    ->label(__('keywords.filters.position_min'))
                    ->numeric()
                    ->minValue(1),
                TextInput::make('max')
                    ->label(__('keywords.filters.position_max'))
                    ->numeric()
                    ->minValue(1),
            ])
            ->query(fn (Builder $query, array $data) => $query
                ->when(
                    filled($data['min'] ?? null),
                    fn (Builder $query) => $query->whereHas('latestRanking', fn (Builder $query) => $query->where('position', '>=', $data['min'])),
                )
                ->when(
                    filled($data['max'] ?? null),
                    fn (Builder $query) => $query->whereHas('latestRanking', fn (Builder $query) => $query->where('position', '<=', $data['max'])),
                ))
            ->indicateUsing(function (array $data): array {
                $indicators = [];

                if (filled($data['min'] ?? null)) {
                    $indicators[] = Indicator::make(__('keywords.filters.position_min_indicator', ['value' => $data['min']]))->removeField('min');
                }

                if (filled($data['max'] ?? null)) {
                    $indicators[] = Indicator::make(__('keywords.filters.position_max_indicator', ['value' => $data['max']]))->removeField('max');
                }

                return $indicators;
            });
    }

    private static function movementFilter(): SelectFilter
    {
        return SelectFilter::make('movement')
            ->label(__('keywords.filters.movement'))
            ->options([
                'up' => __('keywords.movement.up'),
                'down' => __('keywords.movement.down'),
                'same' => __('keywords.movement.same'),
                'none' => __('keywords.movement.none'),
            ])
            ->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                'up' => $query->whereHas('latestRanking', fn (Builder $query) => $query->whereColumn('position', '<', 'previous_position')),
                'down' => $query->whereHas('latestRanking', fn (Builder $query) => $query->whereColumn('position', '>', 'previous_position')),
                'same' => $query->whereHas('latestRanking', fn (Builder $query) => $query->whereColumn('position', '=', 'previous_position')),
                'none' => $query->where(fn (Builder $query) => $query
                    ->whereDoesntHave('latestRanking')
                    ->orWhereHas('latestRanking', fn (Builder $query) => $query->whereNull('position'))),
                default => $query,
            });
    }

    private static function rankingHistoryAction(): Action
    {
        return Action::make('rankingHistory')
            ->label(__('keywords.ranking_history.action'))
            ->icon(Heroicon::OutlinedChartBar)
            ->color('gray')
            ->modalHeading(fn (Keyword $record) => __('keywords.ranking_history.modal_heading', ['keyword' => $record->keyword]))
            ->modalContent(fn (Keyword $record) => view('filament.modals.keyword-ranking-chart', ['keyword' => $record]))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('keywords.ranking_history.close'));
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
                $rateLimitKey = 'enrich-volume:'.auth()->id();
                $maxAttempts = (int) config('cost_control.paid_action_rate_limit.max_attempts');
                $decaySeconds = (int) config('cost_control.paid_action_rate_limit.decay_seconds');

                if (RateLimiter::tooManyAttempts($rateLimitKey, $maxAttempts)) {
                    Notification::make()
                        ->title(__('keywords.enrich.rate_limited', ['seconds' => RateLimiter::availableIn($rateLimitKey)]))
                        ->danger()
                        ->send();

                    return;
                }

                RateLimiter::hit($rateLimitKey, $decaySeconds);

                /** @var Collection<int, Keyword> $keywords */
                $keywords = $records;

                app(AuditLogger::class)->log(
                    AuditEvent::PaidActionTriggered,
                    user: auth()->user(),
                    context: ['action' => 'enrich_volume', 'keyword_count' => $keywords->count()],
                );

                try {
                    $result = app(EnrichKeywordVolumes::class)->execute($keywords);
                } catch (DataForSeoBudgetExceededException) {
                    Notification::make()
                        ->title(__('keywords.enrich.budget_exceeded'))
                        ->danger()
                        ->send();

                    return;
                }

                if ($result->failures !== []) {
                    Notification::make()
                        ->title(__('keywords.enrich.partial_failure', [
                            'updated' => $result->updated,
                            'requested' => $result->requested,
                            'errors' => implode(' / ', $result->failures),
                        ]))
                        ->danger()
                        ->persistent()
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
