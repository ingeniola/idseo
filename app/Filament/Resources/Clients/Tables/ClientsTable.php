<?php

declare(strict_types=1);

namespace App\Filament\Resources\Clients\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ClientsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('logo_path')
                    ->label(__('clients.fields.logo_path'))
                    ->circular(),
                TextColumn::make('name')
                    ->label(__('clients.fields.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('contact_email')
                    ->label(__('clients.fields.contact_email'))
                    ->searchable(),
                TextColumn::make('projects_count')
                    ->label(__('clients.fields.projects_count'))
                    ->counts('projects')
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label(__('clients.fields.is_active'))
                    ->boolean(),
                TextColumn::make('monthly_budget_cap')
                    ->label(__('clients.fields.monthly_budget_cap'))
                    ->money('usd')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(__('clients.fields.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label(__('clients.fields.is_active')),
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
