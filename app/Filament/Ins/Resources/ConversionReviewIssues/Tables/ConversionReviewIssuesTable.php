<?php

namespace App\Filament\Ins\Resources\ConversionReviewIssues\Tables;

use App\Models\ConversionReviewIssue;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ConversionReviewIssuesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('id')
            ->columns([
                TextColumn::make('severity')
                    ->label('الحدة')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'error' => 'يحتاج تصحيح',
                        'warning' => 'مراجعة',
                        default => 'ملاحظة',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'error' => 'danger',
                        'warning' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('issue_code')
                    ->label('النوع')
                    ->formatStateUsing(fn (string $state): string => ConversionReviewIssue::codeLabel($state))
                    ->wrap()
                    ->searchable(),
                TextColumn::make('party_name')
                    ->label('الاسم')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('customer_id')
                    ->label('الزبون')
                    ->sortable(),
                TextColumn::make('contract_id')
                    ->label('العقد')
                    ->sortable(),
                TextColumn::make('invoice_id')
                    ->label('الفاتورة'),
                TextColumn::make('message')
                    ->label('الوصف')
                    ->wrap()
                    ->limit(80),
                TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'reviewed' => 'اطّلعنا',
                        'corrected' => 'صُحّحت',
                        default => 'مفتوحة',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'corrected' => 'success',
                        'reviewed' => 'info',
                        default => 'warning',
                    }),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('الحالة')
                    ->options([
                        'open' => 'مفتوحة',
                        'reviewed' => 'اطّلعنا',
                        'corrected' => 'صُحّحت',
                    ])
                    ->default('open'),
                SelectFilter::make('severity')
                    ->label('الحدة')
                    ->options([
                        'error' => 'يحتاج تصحيح',
                        'warning' => 'مراجعة',
                        'info' => 'ملاحظة',
                    ]),
                SelectFilter::make('issue_code')
                    ->label('النوع')
                    ->options(fn (): array => ConversionReviewIssue::query()
                        ->select('issue_code')
                        ->distinct()
                        ->pluck('issue_code')
                        ->mapWithKeys(fn (string $code): array => [$code => ConversionReviewIssue::codeLabel($code)])
                        ->all()),
            ])
            ->recordActions([
                EditAction::make()->label('تصحيح'),
            ]);
    }
}
