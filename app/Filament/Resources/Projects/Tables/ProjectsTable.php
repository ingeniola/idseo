<?php

declare(strict_types=1);

namespace App\Filament\Resources\Projects\Tables;

use App\Enums\SearchEngine;
use App\Enums\TrackingFrequency;
use App\Models\Client;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ProjectsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('client.name')
                    ->label(__('projects.fields.client_id'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->label(__('projects.fields.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('domain')
                    ->label(__('projects.fields.domain'))
                    ->searchable(),
                TextColumn::make('target_type')
                    ->label(__('projects.fields.target_type'))
                    ->badge(),
                TextColumn::make('keywords_count')
                    ->label(__('projects.fields.keywords_count'))
                    ->counts('keywords')
                    ->sortable(),
                TextColumn::make('search_engine')
                    ->label(__('projects.fields.search_engine'))
                    ->badge(),
                TextColumn::make('tracking_frequency')
                    ->label(__('projects.fields.tracking_frequency'))
                    ->badge(),
                IconColumn::make('is_active')
                    ->label(__('projects.fields.is_active'))
                    ->boolean(),
                TextColumn::make('created_at')
                    ->label(__('projects.fields.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('client_id')
                    ->label(__('projects.fields.client_id'))
                    ->options(fn () => Client::query()->orderBy('name')->pluck('name', 'id'))
                    ->searchable(),
                SelectFilter::make('search_engine')
                    ->label(__('projects.fields.search_engine'))
                    ->options(SearchEngine::class),
                SelectFilter::make('tracking_frequency')
                    ->label(__('projects.fields.tracking_frequency'))
                    ->options(TrackingFrequency::class),
                TernaryFilter::make('is_active')
                    ->label(__('projects.fields.is_active')),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
