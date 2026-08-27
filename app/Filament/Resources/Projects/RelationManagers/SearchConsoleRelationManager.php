<?php

declare(strict_types=1);

namespace App\Filament\Resources\Projects\RelationManagers;

use App\Models\Project;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Fase 2: integración con Google Search Console (sección 5 del SPEC).
 * Solo lectura: los datos los trae SyncSearchConsoleData, nadie los
 * captura a mano — sin formulario, sin crear/editar/borrar filas de
 * métrica. Conectar SÍ necesita salir de la app (OAuth, ruta plana de
 * Laravel — ConnectSearchConsoleController); desconectar no, así que
 * es una Action normal que borra la conexión directamente.
 */
class SearchConsoleRelationManager extends RelationManager
{
    protected static string $relationship = 'searchConsoleMetrics';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('search_console.plural');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('date')
                    ->label(__('search_console.fields.date'))
                    ->date()
                    ->sortable(),
                TextColumn::make('query')
                    ->label(__('search_console.fields.query'))
                    ->searchable(),
                TextColumn::make('clicks')
                    ->label(__('search_console.fields.clicks'))
                    ->sortable(),
                TextColumn::make('impressions')
                    ->label(__('search_console.fields.impressions'))
                    ->sortable(),
                TextColumn::make('ctr')
                    ->label(__('search_console.fields.ctr'))
                    ->formatStateUsing(fn ($state) => number_format((float) $state * 100, 2).'%')
                    ->sortable(),
                TextColumn::make('position')
                    ->label(__('search_console.fields.position'))
                    ->numeric(2)
                    ->sortable(),
            ])
            ->defaultSort('date', 'desc')
            ->emptyStateHeading(__('search_console.empty.heading'))
            ->emptyStateDescription(fn () => $this->isConnected() ? __('search_console.empty.description_connected') : __('search_console.empty.description_disconnected'))
            ->headerActions([
                self::connectAction(),
                self::disconnectAction(),
            ])
            ->recordActions([])
            ->toolbarActions([]);
    }

    private function isConnected(): bool
    {
        /** @var Project $project */
        $project = $this->getOwnerRecord();

        return $project->searchConsoleConnection !== null;
    }

    private static function connectAction(): Action
    {
        return Action::make('connect')
            ->label(__('search_console.connect.action'))
            ->icon(Heroicon::OutlinedLink)
            ->url(fn (RelationManager $livewire) => route('gsc.connect', self::ownerProject($livewire)))
            ->visible(fn (RelationManager $livewire) => self::ownerProject($livewire)->searchConsoleConnection === null);
    }

    private static function disconnectAction(): Action
    {
        return Action::make('disconnect')
            ->label(fn (RelationManager $livewire) => __('search_console.disconnect.action', ['site' => self::ownerProject($livewire)->searchConsoleConnection?->site_url]))
            ->icon(Heroicon::OutlinedLinkSlash)
            ->color('danger')
            ->requiresConfirmation()
            ->visible(fn (RelationManager $livewire) => self::ownerProject($livewire)->searchConsoleConnection !== null)
            ->action(function (RelationManager $livewire): void {
                $project = self::ownerProject($livewire);
                $project->searchConsoleConnection?->delete();

                // Sin esto, el resto del render de esta misma request
                // (los ->visible()/->label() de connect/disconnect) sigue
                // viendo la relación tal como estaba cacheada en memoria
                // antes del delete(), y los botones no reflejan que ya
                // se desconectó hasta recargar la página a mano.
                $project->unsetRelation('searchConsoleConnection');

                Notification::make()
                    ->title(__('search_console.disconnect.success'))
                    ->success()
                    ->send();
            });
    }

    private static function ownerProject(RelationManager $livewire): Project
    {
        /** @var Project $project */
        $project = $livewire->getOwnerRecord();

        return $project;
    }
}
