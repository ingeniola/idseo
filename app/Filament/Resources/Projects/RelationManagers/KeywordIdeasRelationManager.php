<?php

declare(strict_types=1);

namespace App\Filament\Resources\Projects\RelationManagers;

use App\Audit\AuditLogger;
use App\DataForSeo\Exceptions\DataForSeoBudgetExceededException;
use App\DataForSeo\Labs\SearchKeywordIdeas;
use App\Enums\AuditEvent;
use App\Enums\SearchIntent;
use App\Models\KeywordIdea;
use App\Models\Project;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Fase 2, "Investigación de keywords" (sección 5.2 del SPEC): buscar
 * ideas a partir de una keyword semilla (SearchKeywordIdeas, Live,
 * dataforseo_labs) y promover las interesantes a `keywords` para
 * seguimiento (sección 4: "is_selected permite promover ideas a
 * keywords"). No hay formulario de edición ni creación manual de una
 * idea: solo se generan vía la búsqueda.
 */
class KeywordIdeasRelationManager extends RelationManager
{
    protected static string $relationship = 'keywordIdeas';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('keyword_research.plural');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('keyword')
            ->columns([
                TextColumn::make('keyword')
                    ->label(__('keyword_research.fields.keyword'))
                    ->searchable(),
                TextColumn::make('search_volume')
                    ->label(__('keyword_research.fields.search_volume'))
                    ->numeric()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('cpc')
                    ->label(__('keyword_research.fields.cpc'))
                    ->money('USD')
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('competition')
                    ->label(__('keyword_research.fields.competition'))
                    ->numeric(2)
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('difficulty')
                    ->label(__('keyword_research.fields.difficulty'))
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('intent')
                    ->label(__('keyword_research.fields.intent'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => $state === null ? null : (SearchIntent::tryFrom($state)?->getLabel() ?? $state))
                    ->placeholder('—'),
                TextColumn::make('session.seed_keyword')
                    ->label(__('keyword_research.fields.seed_keyword')),
                IconColumn::make('is_selected')
                    ->label(__('keyword_research.fields.is_selected'))
                    ->boolean(),
            ])
            ->defaultSort('search_volume', 'desc')
            ->headerActions([
                self::searchIdeasAction(),
            ])
            ->toolbarActions([
                self::promoteBulkAction(),
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    private static function searchIdeasAction(): Action
    {
        return Action::make('searchIdeas')
            ->label(__('keyword_research.search.action'))
            ->requiresConfirmation()
            ->modalHeading(__('keyword_research.search.modal_heading'))
            ->modalDescription(function () {
                $estimate = config('dataforseo.keyword_ideas_live_cost_estimate');

                return filled($estimate)
                    ? __('keyword_research.search.modal_description_with_estimate', ['cost' => '$'.number_format((float) $estimate, 4)])
                    : __('keyword_research.search.modal_description_without_estimate');
            })
            ->form([
                TextInput::make('seed_keyword')
                    ->label(__('keyword_research.fields.seed_keyword'))
                    ->required()
                    ->maxLength(255),
            ])
            ->modalSubmitActionLabel(__('keyword_research.search.submit'))
            ->action(function (array $data, RelationManager $livewire): void {
                $rateLimitKey = 'keyword-research:'.auth()->id();
                $maxAttempts = (int) config('cost_control.paid_action_rate_limit.max_attempts');
                $decaySeconds = (int) config('cost_control.paid_action_rate_limit.decay_seconds');

                if (RateLimiter::tooManyAttempts($rateLimitKey, $maxAttempts)) {
                    Notification::make()
                        ->title(__('keyword_research.search.rate_limited', ['seconds' => RateLimiter::availableIn($rateLimitKey)]))
                        ->danger()
                        ->send();

                    return;
                }

                RateLimiter::hit($rateLimitKey, $decaySeconds);

                /** @var Project $project */
                $project = $livewire->getOwnerRecord();

                app(AuditLogger::class)->log(
                    AuditEvent::PaidActionTriggered,
                    user: auth()->user(),
                    context: ['action' => 'keyword_research', 'seed_keyword' => $data['seed_keyword']],
                );

                try {
                    $session = app(SearchKeywordIdeas::class)->execute($project, auth()->user(), $data['seed_keyword']);
                } catch (DataForSeoBudgetExceededException) {
                    Notification::make()
                        ->title(__('keyword_research.search.budget_exceeded'))
                        ->danger()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title(__('keyword_research.search.success', ['count' => $session->keywordIdeas()->count()]))
                    ->success()
                    ->send();
            });
    }

    private static function promoteBulkAction(): BulkAction
    {
        return BulkAction::make('promote')
            ->label(__('keyword_research.promote.action'))
            ->requiresConfirmation()
            ->modalDescription(__('keyword_research.promote.modal_description'))
            ->action(function (Collection $records, RelationManager $livewire): void {
                /** @var Project $project */
                $project = $livewire->getOwnerRecord();

                /** @var Collection<int, KeywordIdea> $ideas */
                $ideas = $records;

                $promoted = 0;

                foreach ($ideas as $idea) {
                    $keyword = $project->keywords()->firstOrCreate([
                        'keyword' => $idea->keyword,
                        'location_code' => $project->default_location_code,
                        'language_code' => $project->default_language_code,
                    ], [
                        'search_volume' => $idea->search_volume,
                        'cpc' => $idea->cpc,
                        'competition' => $idea->competition,
                        'volume_updated_at' => now(),
                        'is_active' => true,
                    ]);

                    if ($keyword->wasRecentlyCreated) {
                        $promoted++;
                    }

                    $idea->update(['is_selected' => true]);
                }

                Notification::make()
                    ->title(__('keyword_research.promote.success', ['promoted' => $promoted, 'requested' => $ideas->count()]))
                    ->success()
                    ->send();
            })
            ->deselectRecordsAfterCompletion();
    }
}
