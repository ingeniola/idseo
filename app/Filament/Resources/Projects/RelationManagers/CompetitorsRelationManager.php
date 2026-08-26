<?php

declare(strict_types=1);

namespace App\Filament\Resources\Projects\RelationManagers;

use App\Models\Project;
use App\Models\SerpCompetitor;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Fase 2: análisis de competidores derivado de los SERP snapshots
 * (sección 5 del SPEC). Solo lectura: los datos los calcula
 * CalculateSerpCompetitors, nadie los captura ni edita a mano — sin
 * formulario, sin acciones de crear/editar/borrar.
 *
 * CalculateSerpCompetitors guarda un snapshot por día (igual que
 * project_visibility_snapshots), así que sin filtrar esta tabla
 * acumularía el mismo dominio repetido una vez por cada día que corrió
 * el job. Se limita a la fecha calculada más reciente: lo que importa
 * aquí es "quién compite conmigo ahora mismo", no el histórico.
 */
class CompetitorsRelationManager extends RelationManager
{
    protected static string $relationship = 'serpCompetitors';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('serp_competitors.plural');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('domain')
            ->modifyQueryUsing(function (Builder $query) {
                /** @var Project $project */
                $project = $this->getOwnerRecord();

                $latestDate = SerpCompetitor::query()
                    ->where('project_id', $project->id)
                    ->max('calculated_at');

                return $query->where('calculated_at', $latestDate);
            })
            ->columns([
                TextColumn::make('domain')
                    ->label(__('serp_competitors.fields.domain'))
                    ->searchable(),
                TextColumn::make('keywords_overlap')
                    ->label(__('serp_competitors.fields.keywords_overlap'))
                    ->sortable(),
                TextColumn::make('avg_position')
                    ->label(__('serp_competitors.fields.avg_position'))
                    ->numeric(2)
                    ->sortable(),
                TextColumn::make('visibility_score')
                    ->label(__('serp_competitors.fields.visibility_score'))
                    ->numeric(2)
                    ->sortable(),
                TextColumn::make('calculated_at')
                    ->label(__('serp_competitors.fields.calculated_at'))
                    ->date()
                    ->sortable(),
            ])
            ->defaultSort('visibility_score', 'desc')
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
