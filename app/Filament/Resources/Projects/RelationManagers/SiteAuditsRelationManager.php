<?php

declare(strict_types=1);

namespace App\Filament\Resources\Projects\RelationManagers;

use App\Audit\AuditLogger;
use App\DataForSeo\Exceptions\DataForSeoBudgetExceededException;
use App\DataForSeo\OnPage\TriggerSiteAudit;
use App\Enums\AuditEvent;
use App\Models\AuditIssue;
use App\Models\Project;
use App\Models\SiteAudit;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Fase 3, "Auditoría técnica on-page" (sección 5 del SPEC). Solo
 * lectura: los resultados los trae TriggerSiteAudit +
 * ProcessOnPageAuditPostback (asíncrono, vía webhook), nadie los
 * captura a mano. A diferencia de Backlinks (Live, síncrono), esta
 * auditoría es Standard/asíncrona — la tabla se actualiza sola cuando
 * el postback llega, no hay nada que esperar en pantalla.
 */
class SiteAuditsRelationManager extends RelationManager
{
    protected static string $relationship = 'siteAudits';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('site_audits.title');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('status')
                    ->label(__('site_audits.fields.status'))
                    ->badge(),
                TextColumn::make('started_at')
                    ->label(__('site_audits.fields.started_at'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('completed_at')
                    ->label(__('site_audits.fields.completed_at'))
                    ->dateTime()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('pages_crawled')
                    ->label(__('site_audits.fields.pages_crawled'))
                    ->numeric()
                    ->placeholder('—'),
                TextColumn::make('onpage_score')
                    ->label(__('site_audits.fields.onpage_score'))
                    ->numeric(2)
                    ->placeholder('—'),
                TextColumn::make('cost')
                    ->label(__('site_audits.fields.cost'))
                    ->money('USD')
                    ->placeholder('—'),
                TextColumn::make('issues_count')
                    ->label(__('site_audits.issues.action'))
                    ->counts('issues')
                    ->numeric(),
            ])
            ->defaultSort('started_at', 'desc')
            ->headerActions([
                self::triggerAction(),
            ])
            ->recordActions([
                self::issuesAction(),
            ])
            ->toolbarActions([]);
    }

    private static function triggerAction(): Action
    {
        return Action::make('triggerAudit')
            ->label(__('site_audits.trigger.action'))
            ->requiresConfirmation()
            ->modalHeading(__('site_audits.trigger.modal_heading'))
            ->modalDescription(function () {
                $estimate = config('dataforseo.onpage_audit_cost_per_page_estimate');
                $maxPages = config('dataforseo.onpage_audit_default_max_crawl_pages');

                return filled($estimate)
                    ? __('site_audits.trigger.modal_description_with_estimate', ['cost' => '$'.number_format((float) $estimate, 4), 'max_pages' => $maxPages])
                    : __('site_audits.trigger.modal_description_without_estimate', ['max_pages' => $maxPages]);
            })
            ->form([
                TextInput::make('max_crawl_pages')
                    ->label(__('site_audits.trigger.max_crawl_pages'))
                    ->numeric()
                    ->minValue(1)
                    ->default(fn () => config('dataforseo.onpage_audit_default_max_crawl_pages'))
                    ->required(),
            ])
            ->modalSubmitActionLabel(__('site_audits.trigger.submit'))
            ->action(function (array $data, RelationManager $livewire): void {
                $rateLimitKey = 'trigger-site-audit:'.auth()->id();
                $maxAttempts = (int) config('cost_control.paid_action_rate_limit.max_attempts');
                $decaySeconds = (int) config('cost_control.paid_action_rate_limit.decay_seconds');

                if (RateLimiter::tooManyAttempts($rateLimitKey, $maxAttempts)) {
                    Notification::make()
                        ->title(__('site_audits.trigger.rate_limited', ['seconds' => RateLimiter::availableIn($rateLimitKey)]))
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
                    context: ['action' => 'trigger_site_audit', 'project_id' => $project->id, 'max_crawl_pages' => (int) $data['max_crawl_pages']],
                );

                try {
                    app(TriggerSiteAudit::class)->execute($project, (int) $data['max_crawl_pages']);
                } catch (DataForSeoBudgetExceededException) {
                    Notification::make()
                        ->title(__('site_audits.trigger.budget_exceeded'))
                        ->danger()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title(__('site_audits.trigger.success'))
                    ->success()
                    ->send();
            });
    }

    private static function issuesAction(): Action
    {
        return Action::make('viewIssues')
            ->label(__('site_audits.issues.action'))
            ->modalHeading(__('site_audits.issues.modal_heading'))
            ->modalContent(fn (SiteAudit $record) => view('filament.modals.site-audit-issues', [
                'issues' => AuditIssue::query()->where('audit_id', $record->id)->orderByDesc('severity')->get(),
            ]))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('site_audits.issues.close'));
    }
}
