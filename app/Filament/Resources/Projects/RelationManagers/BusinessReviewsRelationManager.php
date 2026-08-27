<?php

declare(strict_types=1);

namespace App\Filament\Resources\Projects\RelationManagers;

use App\Audit\AuditLogger;
use App\DataForSeo\BusinessData\SyncBusinessReviews;
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
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Fase 3, "Monitoreo de reseñas y Google Business Profile" (sección 5
 * del SPEC). Solo lectura: las reseñas las trae SyncBusinessReviews +
 * ProcessBusinessReviewsPostback (asíncrono, vía webhook), nadie las
 * captura a mano. Standard/a demanda igual que Auditoría técnica
 * on-page — no hay mandato del SPEC de sincronizar reseñas
 * periódicamente y DataForSEO cobra por cada 10 reseñas traídas.
 */
class BusinessReviewsRelationManager extends RelationManager
{
    protected static string $relationship = 'businessReviews';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('business_reviews.title');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('reviewer_name')
                    ->label(__('business_reviews.fields.reviewer_name'))
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('rating')
                    ->label(__('business_reviews.fields.rating'))
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('review_text')
                    ->label(__('business_reviews.fields.review_text'))
                    ->limit(80)
                    ->placeholder('—'),
                TextColumn::make('published_at')
                    ->label(__('business_reviews.fields.published_at'))
                    ->dateTime()
                    ->sortable()
                    ->placeholder('—'),
                IconColumn::make('owner_answer')
                    ->label(__('business_reviews.fields.owner_answer'))
                    ->boolean()
                    ->trueIcon(Heroicon::OutlinedCheckCircle)
                    ->trueColor('success')
                    ->falseIcon(Heroicon::OutlinedMinusCircle)
                    ->falseColor('gray')
                    ->getStateUsing(fn ($record) => $record->hasOwnerAnswer()),
                IconColumn::make('is_local_guide')
                    ->label(__('business_reviews.fields.is_local_guide'))
                    ->boolean(),
            ])
            ->defaultSort('published_at', 'desc')
            ->headerActions([
                self::syncAction(),
            ])
            ->recordActions([])
            ->toolbarActions([]);
    }

    private static function syncAction(): Action
    {
        return Action::make('syncReviews')
            ->label(__('business_reviews.sync.action'))
            ->requiresConfirmation()
            ->modalHeading(__('business_reviews.sync.modal_heading'))
            ->modalDescription(function () {
                $estimate = config('dataforseo.business_reviews_cost_per_10_estimate');
                $depth = config('dataforseo.business_reviews_default_depth');

                return filled($estimate)
                    ? __('business_reviews.sync.modal_description_with_estimate', ['cost' => '$'.number_format((float) $estimate, 4), 'depth' => $depth])
                    : __('business_reviews.sync.modal_description_without_estimate', ['depth' => $depth]);
            })
            ->form([
                TextInput::make('depth')
                    ->label(__('business_reviews.sync.depth'))
                    ->numeric()
                    ->minValue(10)
                    ->step(10)
                    ->default(fn () => config('dataforseo.business_reviews_default_depth'))
                    ->required(),
            ])
            ->modalSubmitActionLabel(__('business_reviews.sync.submit'))
            ->action(function (array $data, RelationManager $livewire): void {
                /** @var Project $project */
                $project = $livewire->getOwnerRecord();

                if (blank($project->google_business_place_id)) {
                    Notification::make()
                        ->title(__('business_reviews.sync.missing_place_id'))
                        ->danger()
                        ->send();

                    return;
                }

                $rateLimitKey = 'sync-reviews:'.auth()->id();
                $maxAttempts = (int) config('cost_control.paid_action_rate_limit.max_attempts');
                $decaySeconds = (int) config('cost_control.paid_action_rate_limit.decay_seconds');

                if (RateLimiter::tooManyAttempts($rateLimitKey, $maxAttempts)) {
                    Notification::make()
                        ->title(__('business_reviews.sync.rate_limited', ['seconds' => RateLimiter::availableIn($rateLimitKey)]))
                        ->danger()
                        ->send();

                    return;
                }

                RateLimiter::hit($rateLimitKey, $decaySeconds);

                app(AuditLogger::class)->log(
                    AuditEvent::PaidActionTriggered,
                    user: auth()->user(),
                    context: ['action' => 'sync_business_reviews', 'project_id' => $project->id, 'depth' => (int) $data['depth']],
                );

                try {
                    app(SyncBusinessReviews::class)->execute($project, (int) $data['depth']);
                } catch (DataForSeoBudgetExceededException) {
                    Notification::make()
                        ->title(__('business_reviews.sync.budget_exceeded'))
                        ->danger()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title(__('business_reviews.sync.success'))
                    ->success()
                    ->send();
            });
    }
}
