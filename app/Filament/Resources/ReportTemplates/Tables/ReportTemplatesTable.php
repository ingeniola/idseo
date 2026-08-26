<?php

declare(strict_types=1);

namespace App\Filament\Resources\ReportTemplates\Tables;

use App\Enums\ReportSection;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ReportTemplatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('report_templates.fields.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('client.name')
                    ->label(__('report_templates.fields.client_id'))
                    ->placeholder(__('report_templates.global_template')),
                TextColumn::make('sections')
                    ->label(__('report_templates.fields.sections'))
                    ->badge()
                    ->formatStateUsing(fn (string $state) => ReportSection::from($state)->getLabel()),
                TextColumn::make('reports_count')
                    ->label(__('report_templates.fields.reports_count'))
                    ->counts('reports')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(__('report_templates.fields.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
