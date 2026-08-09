<?php

namespace App\Filament\Market\Resources\FundTransfers\Schemas;

use App\Enums\FundTransferKind;
use App\Models\BankAccount;
use App\Models\CashBox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class FundTransferForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Radio::make('transfer_kind')
                ->label('نوع التحويل')
                ->inline()
                ->default(FundTransferKind::CashToCash->value)
                ->live()
                ->options(collect(FundTransferKind::cases())
                    ->mapWithKeys(fn (FundTransferKind $kind): array => [$kind->value => $kind->getLabel()])
                    ->all())
                ->afterStateUpdated(function (callable $set): void {
                    $set('from_cash_box_id', null);
                    $set('to_cash_box_id', null);
                    $set('from_bank_account_id', null);
                    $set('to_bank_account_id', null);
                })
                ->columnSpanFull(),
            Section::make()
                ->schema([
                    Select::make('from_cash_box_id')
                        ->label('من الخزينة')
                        ->options(fn (): array => CashBox::query()->orderBy('name')->pluck('name', 'id')->all())
                        ->searchable()
                        ->preload()
                        ->required(fn (Get $get): bool => self::kind($get)->usesFromCashBox())
                        ->visible(fn (Get $get): bool => self::kind($get)->usesFromCashBox()),
                    Select::make('from_bank_account_id')
                        ->label('من الحساب المصرفي')
                        ->options(fn (): array => BankAccount::query()->orderBy('name')->pluck('name', 'id')->all())
                        ->searchable()
                        ->preload()
                        ->required(fn (Get $get): bool => self::kind($get)->usesFromBankAccount())
                        ->visible(fn (Get $get): bool => self::kind($get)->usesFromBankAccount()),
                    Select::make('to_cash_box_id')
                        ->label('إلى الخزينة')
                        ->options(fn (): array => CashBox::query()->orderBy('name')->pluck('name', 'id')->all())
                        ->searchable()
                        ->preload()
                        ->required(fn (Get $get): bool => self::kind($get)->usesToCashBox())
                        ->visible(fn (Get $get): bool => self::kind($get)->usesToCashBox()),
                    Select::make('to_bank_account_id')
                        ->label('إلى الحساب المصرفي')
                        ->options(fn (): array => BankAccount::query()->orderBy('name')->pluck('name', 'id')->all())
                        ->searchable()
                        ->preload()
                        ->required(fn (Get $get): bool => self::kind($get)->usesToBankAccount())
                        ->visible(fn (Get $get): bool => self::kind($get)->usesToBankAccount()),
                    DatePicker::make('transfer_date')
                        ->label('التاريخ')
                        ->required()
                        ->default(now()),
                    TextInput::make('amount')
                        ->label('المبلغ')
                        ->required()
                        ->numeric()
                        ->minValue(0.001),
                    Textarea::make('notes')
                        ->label('ملاحظات')
                        ->columnSpanFull(),
                ])
                ->columns(2),
            Hidden::make('created_by')
                ->default(fn (): ?int => Auth::id()),
        ]);
    }

    protected static function kind(Get $get): FundTransferKind
    {
        $state = $get('transfer_kind');

        return FundTransferKind::from((int) (is_object($state) ? $state->value : $state));
    }
}
