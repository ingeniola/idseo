<?php

declare(strict_types=1);

namespace App\Filament\Resources\Projects\RelationManagers;

use App\Audit\AuditLogger;
use App\DataForSeo\AiOptimization\SearchLlmMentions;
use App\DataForSeo\Exceptions\DataForSeoBudgetExceededException;
use App\Enums\AuditEvent;
use App\Models\LlmMention;
use App\Models\Project;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Fase 3, "Monitoreo de menciones en LLMs (GEO)" (sección 5 del SPEC).
 * Solo lectura: las menciones las trae SearchLlmMentions. Live e
 * interactiva (igual que Backlinks) — la sección 3.2 del SPEC prohíbe
 * disparar Live de forma automática o en bucle, así que no hay job
 * programado para esto.
 */
class LlmMentionsRelationManager extends RelationManager
{
    protected static string $relationship = 'llmMentions';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('llm_mentions.title');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('platform')
                    ->label(__('llm_mentions.fields.platform'))
                    ->formatStateUsing(fn (string $state) => __('llm_mentions.platforms.'.$state)),
                TextColumn::make('question')
                    ->label(__('llm_mentions.fields.question'))
                    ->limit(60)
                    ->placeholder('—'),
                TextColumn::make('answer')
                    ->label(__('llm_mentions.fields.answer'))
                    ->limit(80)
                    ->placeholder('—'),
                TextColumn::make('captured_at')
                    ->label(__('llm_mentions.fields.captured_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('captured_at', 'desc')
            ->headerActions([
                self::searchAction(),
            ])
            ->recordActions([
                self::viewAction(),
            ])
            ->toolbarActions([]);
    }

    private static function searchAction(): Action
    {
        return Action::make('searchMentions')
            ->label(__('llm_mentions.search.action'))
            ->requiresConfirmation()
            ->modalHeading(__('llm_mentions.search.modal_heading'))
            ->modalDescription(function () {
                $estimate = config('dataforseo.llm_mentions_search_live_cost_estimate');

                return filled($estimate)
                    ? __('llm_mentions.search.modal_description_with_estimate', ['cost' => '$'.number_format((float) $estimate, 4)])
                    : __('llm_mentions.search.modal_description_without_estimate');
            })
            ->form([
                Select::make('platform')
                    ->label(__('llm_mentions.search.platform'))
                    ->options([
                        'chat_gpt' => __('llm_mentions.platforms.chat_gpt'),
                        'google' => __('llm_mentions.platforms.google'),
                    ])
                    ->default('chat_gpt')
                    ->required(),
            ])
            ->modalSubmitActionLabel(__('llm_mentions.search.submit'))
            ->action(function (array $data, RelationManager $livewire): void {
                $rateLimitKey = 'search-llm-mentions:'.auth()->id();
                $maxAttempts = (int) config('cost_control.paid_action_rate_limit.max_attempts');
                $decaySeconds = (int) config('cost_control.paid_action_rate_limit.decay_seconds');

                if (RateLimiter::tooManyAttempts($rateLimitKey, $maxAttempts)) {
                    Notification::make()
                        ->title(__('llm_mentions.search.rate_limited', ['seconds' => RateLimiter::availableIn($rateLimitKey)]))
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
                    context: ['action' => 'search_llm_mentions', 'project_id' => $project->id, 'platform' => $data['platform']],
                );

                try {
                    $mentions = app(SearchLlmMentions::class)->execute($project, $data['platform']);
                } catch (DataForSeoBudgetExceededException) {
                    Notification::make()
                        ->title(__('llm_mentions.search.budget_exceeded'))
                        ->danger()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title(__('llm_mentions.search.success', ['count' => $mentions->count()]))
                    ->success()
                    ->send();
            });
    }

    private static function viewAction(): Action
    {
        return Action::make('viewMention')
            ->label(__('llm_mentions.view.action'))
            ->modalHeading(__('llm_mentions.view.modal_heading'))
            ->modalContent(fn (LlmMention $record) => view('filament.modals.llm-mention-detail', ['mention' => $record]))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('llm_mentions.view.close'));
    }
}
