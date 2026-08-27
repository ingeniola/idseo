<?php

declare(strict_types=1);

namespace App\Filament\Resources\Projects;

use App\Filament\Resources\Projects\Pages\CreateProject;
use App\Filament\Resources\Projects\Pages\EditProject;
use App\Filament\Resources\Projects\Pages\ListProjects;
use App\Filament\Resources\Projects\RelationManagers\BacklinksRelationManager;
use App\Filament\Resources\Projects\RelationManagers\CompetitorsRelationManager;
use App\Filament\Resources\Projects\RelationManagers\KeywordIdeasRelationManager;
use App\Filament\Resources\Projects\RelationManagers\KeywordsRelationManager;
use App\Filament\Resources\Projects\RelationManagers\ReferringDomainsRelationManager;
use App\Filament\Resources\Projects\RelationManagers\ReportsRelationManager;
use App\Filament\Resources\Projects\RelationManagers\SearchConsoleRelationManager;
use App\Filament\Resources\Projects\Schemas\ProjectForm;
use App\Filament\Resources\Projects\Tables\ProjectsTable;
use App\Models\Project;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ProjectResource extends Resource
{
    protected static ?string $model = Project::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    protected static ?int $navigationSort = 2;

    public static function getNavigationGroup(): ?string
    {
        return __('projects.navigation_group');
    }

    public static function getModelLabel(): string
    {
        return __('projects.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('projects.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return ProjectForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProjectsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            KeywordsRelationManager::class,
            ReportsRelationManager::class,
            CompetitorsRelationManager::class,
            KeywordIdeasRelationManager::class,
            SearchConsoleRelationManager::class,
            BacklinksRelationManager::class,
            ReferringDomainsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProjects::route('/'),
            'create' => CreateProject::route('/create'),
            'edit' => EditProject::route('/{record}/edit'),
        ];
    }
}
