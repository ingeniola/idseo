<?php

declare(strict_types=1);

namespace App\Filament\Imports;

use App\Models\Keyword;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;

class KeywordImporter extends Importer
{
    protected static ?string $model = Keyword::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('keyword')
                ->requiredMapping()
                ->rules(['required', 'string', 'max:255']),
            ImportColumn::make('location_code')
                ->requiredMapping()
                ->numeric()
                ->rules(['required', 'integer']),
            ImportColumn::make('language_code')
                ->requiredMapping()
                ->rules(['required', 'string', 'max:10']),
            ImportColumn::make('tags')
                ->rules(['nullable', 'array'])
                ->castStateUsing(fn (?string $state): array => filled($state)
                    ? array_values(array_filter(array_map('trim', explode(',', $state))))
                    : []),
        ];
    }

    public function resolveRecord(): Keyword
    {
        return Keyword::query()->firstOrNew([
            'project_id' => $this->options['project_id'],
            'keyword' => $this->data['keyword'],
            'location_code' => $this->data['location_code'],
            'language_code' => $this->data['language_code'],
        ]);
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Se procesaron '.number_format($import->successful_rows).' de '.number_format($import->total_rows).' keyword(s).';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' '.number_format($failedRowsCount).' fila(s) fallaron.';
        }

        return $body;
    }
}
