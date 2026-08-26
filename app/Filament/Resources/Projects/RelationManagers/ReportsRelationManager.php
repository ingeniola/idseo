<?php

declare(strict_types=1);

namespace App\Filament\Resources\Projects\RelationManagers;

use App\Enums\ReportStatus;
use App\Jobs\GenerateReport;
use App\Jobs\SendReportEmail;
use App\Models\Project;
use App\Models\Report;
use App\Models\ReportTemplate;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;

/**
 * Generación manual de reportes (sección 5.4 del SPEC); la generación
 * programada mensual vive en GenerateScheduledReports (Fase 1, paso
 * 8/10). No hay formulario de edición: un Report es una instantánea
 * de un período ya calculado, no algo que se edite a mano.
 */
class ReportsRelationManager extends RelationManager
{
    protected static string $relationship = 'reports';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('period_start')
                    ->label(__('reports.fields.period_start'))
                    ->date()
                    ->sortable(),
                TextColumn::make('period_end')
                    ->label(__('reports.fields.period_end'))
                    ->date()
                    ->sortable(),
                TextColumn::make('template.name')
                    ->label(__('reports.fields.template_id')),
                TextColumn::make('status')
                    ->label(__('reports.fields.status'))
                    ->badge(),
                TextColumn::make('generatedBy.name')
                    ->label(__('reports.fields.generated_by'))
                    ->placeholder('—'),
                TextColumn::make('sent_at')
                    ->label(__('reports.fields.sent_at'))
                    ->dateTime()
                    ->placeholder(__('reports.not_sent')),
            ])
            ->defaultSort('period_start', 'desc')
            ->headerActions([
                self::generateReportAction(),
            ])
            ->recordActions([
                self::downloadAction(),
                self::sendAction(),
                self::retryAction(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    private function generateReportAction(): Action
    {
        return Action::make('generateReport')
            ->label(__('reports.generate.action'))
            ->schema([
                Select::make('template_id')
                    ->label(__('reports.fields.template_id'))
                    ->options(function () {
                        /** @var Project $project */
                        $project = $this->getOwnerRecord();

                        return ReportTemplate::query()
                            ->where('client_id', $project->client_id)
                            ->orWhereNull('client_id')
                            ->pluck('name', 'id');
                    })
                    ->required()
                    ->searchable(),
                DatePicker::make('period_start')
                    ->label(__('reports.fields.period_start'))
                    ->default(fn () => Carbon::now()->subMonthNoOverflow()->startOfMonth())
                    ->required(),
                DatePicker::make('period_end')
                    ->label(__('reports.fields.period_end'))
                    ->default(fn () => Carbon::now()->subMonthNoOverflow()->endOfMonth())
                    ->required()
                    ->afterOrEqual('period_start'),
            ])
            ->action(function (array $data): void {
                /** @var Project $project */
                $project = $this->getOwnerRecord();

                $report = Report::query()->create([
                    'project_id' => $project->id,
                    'template_id' => $data['template_id'],
                    'period_start' => $data['period_start'],
                    'period_end' => $data['period_end'],
                    'status' => ReportStatus::Pending,
                    'generated_by' => auth()->id(),
                ]);

                GenerateReport::dispatch($report->id);

                Notification::make()
                    ->title(__('reports.generate.dispatched'))
                    ->success()
                    ->send();
            });
    }

    private function downloadAction(): Action
    {
        return Action::make('download')
            ->label(__('reports.actions.download'))
            ->url(fn (Report $record) => route('reports.download', $record))
            ->openUrlInNewTab()
            ->visible(fn (Report $record) => $record->status === ReportStatus::Completed);
    }

    private function sendAction(): Action
    {
        return Action::make('send')
            ->label(__('reports.actions.send'))
            ->requiresConfirmation()
            ->visible(fn (Report $record) => $record->status === ReportStatus::Completed)
            ->action(function (Report $record): void {
                SendReportEmail::dispatch($record->id);

                Notification::make()
                    ->title(__('reports.send.dispatched'))
                    ->success()
                    ->send();
            });
    }

    private function retryAction(): Action
    {
        return Action::make('retry')
            ->label(__('reports.actions.retry'))
            ->requiresConfirmation()
            ->visible(fn (Report $record) => $record->status === ReportStatus::Failed)
            ->action(function (Report $record): void {
                GenerateReport::dispatch($record->id);

                Notification::make()
                    ->title(__('reports.generate.dispatched'))
                    ->success()
                    ->send();
            });
    }
}
