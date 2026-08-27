<?php

declare(strict_types=1);

namespace App\Filament\Resources\Projects\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Fase 3, "Backlinks: ... dominios referentes ..." (sección 5 del
 * SPEC). Solo lectura, misma fuente que la pestaña "Backlinks"
 * (SyncBacklinkProfile) — sin acciones propias para no duplicar el
 * botón de sincronizar en dos pestañas.
 */
class ReferringDomainsRelationManager extends RelationManager
{
    protected static string $relationship = 'referringDomains';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('backlinks.referring_domains_title');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('domain')
                    ->label(__('backlinks.fields.domain'))
                    ->searchable(),
                TextColumn::make('backlinks_count')
                    ->label(__('backlinks.fields.backlinks_count'))
                    ->sortable(),
                TextColumn::make('domain_rank')
                    ->label(__('backlinks.fields.domain_rank'))
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('first_seen')
                    ->label(__('backlinks.fields.first_seen'))
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
            ])
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
