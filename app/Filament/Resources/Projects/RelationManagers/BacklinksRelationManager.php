<?php

declare(strict_types=1);

namespace App\Filament\Resources\Projects\RelationManagers;

use App\Audit\AuditLogger;
use App\DataForSeo\Backlinks\CompareCompetitorBacklinks;
use App\DataForSeo\Backlinks\SyncBacklinkProfile;
use App\DataForSeo\Exceptions\DataForSeoBudgetExceededException;
use App\Enums\AuditEvent;
use App\Models\Project;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Fase 3, "Backlinks: perfil, ... enlaces perdidos, comparativa contra
 * competidores" (sección 5 del SPEC). Solo lectura: los datos los trae
 * SyncBacklinkProfile/CompareCompetitorBacklinks, nadie los captura a
 * mano. Ambas acciones son Live (interactivas, con confirmación de
 * costo) — la sección 3.2 del SPEC prohíbe disparar Live de forma
 * automática, así que no hay job programado para esto, a diferencia
 * del rank tracking.
 */
class BacklinksRelationManager extends RelationManager
{
    protected static string $relationship = 'backlinks';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('source_domain')
                    ->label(__('backlinks.fields.source_domain'))
                    ->searchable(),
                TextColumn::make('target_url')
                    ->label(__('backlinks.fields.target_url'))
                    ->limit(50)
                    ->searchable(),
                TextColumn::make('anchor')
                    ->label(__('backlinks.fields.anchor'))
                    ->limit(40)
                    ->placeholder('—'),
                IconColumn::make('dofollow')
                    ->label(__('backlinks.fields.dofollow'))
                    ->boolean(),
                TextColumn::make('domain_rank')
                    ->label(__('backlinks.fields.domain_rank'))
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('first_seen')
                    ->label(__('backlinks.fields.first_seen'))
                    ->date()
                    ->sortable(),
                TextColumn::make('last_seen')
                    ->label(__('backlinks.fields.last_seen'))
                    ->date()
                    ->sortable(),
                IconColumn::make('is_lost')
                    ->label(__('backlinks.fields.is_lost'))
                    ->boolean()
                    ->trueIcon(Heroicon::OutlinedXCircle)
                    ->trueColor('danger')
                    ->falseIcon(Heroicon::OutlinedCheckCircle)
                    ->falseColor('success'),
            ])
            ->defaultSort('domain_rank', 'desc')
            ->filters([
                TernaryFilter::make('is_lost')
                    ->label(__('backlinks.fields.is_lost')),
                TernaryFilter::make('dofollow')
                    ->label(__('backlinks.fields.dofollow')),
            ])
            ->headerActions([
                self::syncAction(),
                self::compareAction(),
            ])
            ->recordActions([])
            ->toolbarActions([]);
    }

    private static function syncAction(): Action
    {
        return Action::make('syncBacklinks')
            ->label(__('backlinks.sync.action'))
            ->requiresConfirmation()
            ->modalHeading(__('backlinks.sync.modal_heading'))
            ->modalDescription(function () {
                $estimate = config('dataforseo.backlink_profile_live_cost_estimate');

                return filled($estimate)
                    ? __('backlinks.sync.modal_description_with_estimate', ['cost' => '$'.number_format((float) $estimate, 4)])
                    : __('backlinks.sync.modal_description_without_estimate');
            })
            ->modalSubmitActionLabel(__('backlinks.sync.submit'))
            ->action(function (RelationManager $livewire): void {
                $rateLimitKey = 'sync-backlinks:'.auth()->id();
                $maxAttempts = (int) config('cost_control.paid_action_rate_limit.max_attempts');
                $decaySeconds = (int) config('cost_control.paid_action_rate_limit.decay_seconds');

                if (RateLimiter::tooManyAttempts($rateLimitKey, $maxAttempts)) {
                    Notification::make()
                        ->title(__('backlinks.sync.rate_limited', ['seconds' => RateLimiter::availableIn($rateLimitKey)]))
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
                    context: ['action' => 'sync_backlinks', 'project_id' => $project->id],
                );

                try {
                    app(SyncBacklinkProfile::class)->execute($project);
                } catch (DataForSeoBudgetExceededException) {
                    Notification::make()
                        ->title(__('backlinks.sync.budget_exceeded'))
                        ->danger()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title(__('backlinks.sync.success'))
                    ->success()
                    ->send();
            });
    }

    private static function compareAction(): Action
    {
        return Action::make('compareBacklinks')
            ->label(__('backlinks.compare.action'))
            ->requiresConfirmation()
            ->modalHeading(__('backlinks.compare.modal_heading'))
            ->modalDescription(function () {
                $estimate = config('dataforseo.backlink_comparison_live_cost_estimate');

                return filled($estimate)
                    ? __('backlinks.compare.modal_description_with_estimate', ['cost' => '$'.number_format((float) $estimate, 4)])
                    : __('backlinks.compare.modal_description_without_estimate');
            })
            ->form([
                TextInput::make('domain')
                    ->label(__('backlinks.fields.competitor_domain'))
                    ->required()
                    ->maxLength(255),
            ])
            ->modalSubmitActionLabel(__('backlinks.compare.submit'))
            ->action(function (array $data, RelationManager $livewire): void {
                $rateLimitKey = 'compare-backlinks:'.auth()->id();
                $maxAttempts = (int) config('cost_control.paid_action_rate_limit.max_attempts');
                $decaySeconds = (int) config('cost_control.paid_action_rate_limit.decay_seconds');

                if (RateLimiter::tooManyAttempts($rateLimitKey, $maxAttempts)) {
                    Notification::make()
                        ->title(__('backlinks.compare.rate_limited', ['seconds' => RateLimiter::availableIn($rateLimitKey)]))
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
                    context: ['action' => 'compare_backlinks', 'project_id' => $project->id, 'competitor_domain' => $data['domain']],
                );

                try {
                    $summary = app(CompareCompetitorBacklinks::class)->execute($project, $data['domain']);
                } catch (DataForSeoBudgetExceededException) {
                    Notification::make()
                        ->title(__('backlinks.compare.budget_exceeded'))
                        ->danger()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title(__('backlinks.compare.success', [
                        'domain' => $summary->domain,
                        'backlinks' => number_format($summary->total_backlinks),
                        'referring_domains' => number_format($summary->referring_domains),
                        'rank' => $summary->domain_rank ?? '—',
                    ]))
                    ->success()
                    ->send();
            });
    }
}
