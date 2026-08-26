<?php

declare(strict_types=1);

namespace App\Filament\Resources\ReportTemplates\Schemas;

use App\Enums\ReportSection;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Schema;

class ReportTemplateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('report_templates.fields.name'))
                    ->required()
                    ->maxLength(255),
                Select::make('client_id')
                    ->label(__('report_templates.fields.client_id'))
                    ->helperText(__('report_templates.fields.client_id_helper'))
                    ->relationship('client', 'name')
                    ->searchable()
                    ->preload(),
                CheckboxList::make('sections')
                    ->label(__('report_templates.fields.sections'))
                    ->options(fn () => collect(ReportSection::cases())->mapWithKeys(fn (ReportSection $section) => [$section->value => $section->getLabel()]))
                    ->required()
                    ->columns(2),
                Fieldset::make('branding_overrides')
                    ->label(__('report_templates.fields.branding_overrides'))
                    ->schema([
                        FileUpload::make('branding_overrides.logo_path')
                            ->label(__('report_templates.fields.branding_logo_path'))
                            ->helperText(__('report_templates.fields.branding_logo_path_helper'))
                            ->image()
                            ->directory('report-template-logos')
                            ->visibility('public'),
                        ColorPicker::make('branding_overrides.primary_color')
                            ->label(__('report_templates.fields.branding_primary_color')),
                    ]),
            ]);
    }
}
