<?php

declare(strict_types=1);

namespace App\Filament\Resources\Projects\Schemas;

use App\Enums\SearchEngine;
use App\Enums\TargetType;
use App\Enums\TrackingFrequency;
use App\Models\Language;
use App\Models\Location;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ProjectForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('client_id')
                    ->label(__('projects.fields.client_id'))
                    ->relationship('client', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('name')
                    ->label(__('projects.fields.name'))
                    ->required()
                    ->maxLength(255),
                TextInput::make('domain')
                    ->label(__('projects.fields.domain'))
                    ->required()
                    ->maxLength(255),
                TextInput::make('google_business_place_id')
                    ->label(__('projects.fields.google_business_place_id'))
                    ->helperText(__('projects.fields.google_business_place_id_help'))
                    ->maxLength(255),
                Select::make('target_type')
                    ->label(__('projects.fields.target_type'))
                    ->options(TargetType::class)
                    ->default(TargetType::Domain)
                    ->required(),
                Select::make('default_location_code')
                    ->label(__('projects.fields.default_location_code'))
                    ->helperText('Requiere haber corrido antes php artisan dataforseo:sync-locations.')
                    ->options(fn () => Location::query()->orderBy('location_name')->limit(50)->pluck('location_name', 'location_code'))
                    ->getSearchResultsUsing(fn (string $search) => Location::query()
                        ->where('location_name', 'like', "%{$search}%")
                        ->orderBy('location_name')
                        ->limit(50)
                        ->pluck('location_name', 'location_code'))
                    ->getOptionLabelUsing(fn ($value) => Location::query()->find($value)?->location_name)
                    ->searchable()
                    ->required(),
                Select::make('default_language_code')
                    ->label(__('projects.fields.default_language_code'))
                    ->helperText('Requiere haber corrido antes php artisan dataforseo:sync-locations. Solo se listan los idiomas válidos para keywords_data/google_ads (volumen, ideas de keywords) — algunos códigos de serp/google/languages, como "es-419", no sirven ahí y romperían el enriquecimiento de volumen.')
                    ->options(fn () => Language::query()->where('valid_for_google_ads_keywords', true)->orderBy('language_name')->pluck('language_name', 'language_code'))
                    ->searchable()
                    ->required(),
                Select::make('search_engine')
                    ->label(__('projects.fields.search_engine'))
                    ->options(SearchEngine::class)
                    ->default(SearchEngine::Google)
                    ->required(),
                Select::make('tracking_frequency')
                    ->label(__('projects.fields.tracking_frequency'))
                    ->options(TrackingFrequency::class)
                    ->default(TrackingFrequency::Weekly)
                    ->required(),
                Toggle::make('is_active')
                    ->label(__('projects.fields.is_active'))
                    ->default(true)
                    ->required(),
                Select::make('users')
                    ->label(__('projects.fields.users'))
                    ->relationship('users', 'name')
                    ->multiple()
                    ->searchable()
                    ->preload(),
            ]);
    }
}
