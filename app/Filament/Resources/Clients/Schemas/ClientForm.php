<?php

declare(strict_types=1);

namespace App\Filament\Resources\Clients\Schemas;

use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class ClientForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('clients.fields.name'))
                    ->required()
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (string $operation, $state, callable $set) {
                        if ($operation === 'create') {
                            $set('slug', Str::slug($state));
                        }
                    }),
                TextInput::make('slug')
                    ->label(__('clients.fields.slug'))
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                FileUpload::make('logo_path')
                    ->label(__('clients.fields.logo_path'))
                    ->image()
                    ->directory('client-logos')
                    ->visibility('public'),
                ColorPicker::make('primary_color')
                    ->label(__('clients.fields.primary_color')),
                TextInput::make('contact_email')
                    ->label(__('clients.fields.contact_email'))
                    ->email()
                    ->maxLength(255),
                Toggle::make('is_active')
                    ->label(__('clients.fields.is_active'))
                    ->default(true)
                    ->required(),
                TextInput::make('monthly_budget_cap')
                    ->label(__('clients.fields.monthly_budget_cap'))
                    ->numeric()
                    ->prefix('$'),
            ]);
    }
}
