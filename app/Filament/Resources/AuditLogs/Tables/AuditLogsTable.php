<?php

declare(strict_types=1);

namespace App\Filament\Resources\AuditLogs\Tables;

use App\Enums\AuditEvent;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AuditLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('audit_logs.fields.created_at'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('event')
                    ->label(__('audit_logs.fields.event'))
                    ->badge(),
                TextColumn::make('user.name')
                    ->label(__('audit_logs.fields.user'))
                    ->placeholder('—'),
                TextColumn::make('email')
                    ->label(__('audit_logs.fields.email'))
                    ->placeholder('—'),
                TextColumn::make('ip_address')
                    ->label(__('audit_logs.fields.ip_address'))
                    ->placeholder('—'),
                TextColumn::make('context')
                    ->label(__('audit_logs.fields.context'))
                    ->formatStateUsing(fn (?array $state) => $state !== null ? json_encode($state) : null)
                    ->placeholder('—')
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('event')
                    ->label(__('audit_logs.fields.event'))
                    ->options(fn () => collect(AuditEvent::cases())->mapWithKeys(fn (AuditEvent $event) => [$event->value => $event->getLabel()])),
            ]);
    }
}
