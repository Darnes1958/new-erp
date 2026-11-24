<?php

namespace App\Filament\Ins\Resources\ConversionReviewIssues\Schemas;

use App\Models\ConversionReviewIssue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ConversionReviewIssueForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('الملاحظة')->schema([
                TextInput::make('issue_code')
                    ->label('النوع')
                    ->formatStateUsing(fn (?string $state): string => $state ? ConversionReviewIssue::codeLabel($state) : '')
                    ->disabled(),
                TextInput::make('message')
                    ->label('الوصف')
                    ->disabled()
                    ->columnSpanFull(),
                TextInput::make('party_name')
                    ->label('الاسم في المصدر')
                    ->disabled(),
                TextInput::make('customer_id')
                    ->label('رقم الزبون')
                    ->disabled(),
                TextInput::make('contract_id')
                    ->label('رقم العقد')
                    ->disabled(),
                TextInput::make('invoice_id')
                    ->label('رقم الفاتورة')
                    ->disabled(),
                Textarea::make('details')
                    ->label('التفاصيل')
                    ->disabled()
                    ->rows(6)
                    ->columnSpanFull(),
            ])->columns(2),
            Section::make('تصحيح المسئول')->schema([
                Select::make('status')
                    ->label('الحالة')
                    ->options([
                        'open' => 'مفتوحة',
                        'reviewed' => 'اطّلعنا عليها',
                        'corrected' => 'صُحّحت',
                    ])
                    ->required(),
                Textarea::make('manager_notes')
                    ->label('ملاحظات المسئول')
                    ->rows(3)
                    ->columnSpanFull(),
                TextInput::make('fix_installment_amount')
                    ->label('تصحيح مبلغ القسط')
                    ->numeric()
                    ->dehydrated(false)
                    ->visible(fn (?ConversionReviewIssue $record): bool => filled($record?->contract_id)),
                TextInput::make('fix_installment_count')
                    ->label('تصحيح عدد الأقساط')
                    ->numeric()
                    ->integer()
                    ->dehydrated(false)
                    ->visible(fn (?ConversionReviewIssue $record): bool => filled($record?->contract_id)),
                TextInput::make('fix_contract_total')
                    ->label('تصحيح إجمالي العقد')
                    ->numeric()
                    ->dehydrated(false)
                    ->visible(fn (?ConversionReviewIssue $record): bool => filled($record?->contract_id)),
            ])->columns(2),
        ]);
    }
}
