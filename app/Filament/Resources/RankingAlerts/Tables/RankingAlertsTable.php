<?php

declare(strict_types=1);

namespace App\Filament\Resources\RankingAlerts\Tables;

use App\Enums\AlertType;
use App\Models\Project;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class RankingAlertsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('triggered_at')
                    ->label(__('ranking_alerts.fields.triggered_at'))
                    ->date()
                    ->sortable(),
                TextColumn::make('project.name')
                    ->label(__('ranking_alerts.fields.project'))
                    ->sortable(),
                TextColumn::make('keyword.keyword')
                    ->label(__('ranking_alerts.fields.keyword'))
                    ->searchable(),
                TextColumn::make('type')
                    ->label(__('ranking_alerts.fields.type'))
                    ->badge(),
                TextColumn::make('previous_position')
                    ->label(__('ranking_alerts.fields.previous_position'))
                    ->placeholder('—'),
                TextColumn::make('current_position')
                    ->label(__('ranking_alerts.fields.current_position'))
                    ->placeholder('—'),
                IconColumn::make('notified_at')
                    ->label(__('ranking_alerts.fields.notified'))
                    ->boolean()
                    ->getStateUsing(fn ($record) => $record->notified_at !== null),
            ])
            ->defaultSort('triggered_at', 'desc')
            ->filters([
                SelectFilter::make('project_id')
                    ->label(__('ranking_alerts.fields.project'))
                    ->options(fn () => Project::query()->pluck('name', 'id')),
                SelectFilter::make('type')
                    ->label(__('ranking_alerts.fields.type'))
                    ->options(fn () => collect(AlertType::cases())->mapWithKeys(fn (AlertType $type) => [$type->value => $type->getLabel()])),
            ]);
    }
}
