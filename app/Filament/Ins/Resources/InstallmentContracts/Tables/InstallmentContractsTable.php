<?php

namespace App\Filament\Ins\Resources\InstallmentContracts\Tables;

use App\Filament\Ins\Pages\EditInstallmentContract;
use App\Filament\Ins\Resources\InstallmentContracts\InstallmentContractResource;
use App\Models\InstallmentContract;
use App\Services\Installments\InstallmentContractService;
use App\Support\CompanySettings;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\IconSize;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class InstallmentContractsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('الرقم')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('customer.name')
                    ->label('الزبون')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('installmentBank.name')
                    ->label('المصرف')
                    ->sortable(),
                TextColumn::make('contract_total')
                    ->label('قيمة العقد')
                    ->numeric(3)
                    ->sortable(),
                TextColumn::make('installment_amount')
                    ->label('القسط')
                    ->numeric(3),
                TextColumn::make('balance')
                    ->label('الباقي')
                    ->numeric(3)
                    ->sortable(),
                TextColumn::make('installments_remaining')
                    ->label('متبقي')
                    ->sortable(),
                TextColumn::make('next_installment_date')
                    ->label('قسط قادم')
                    ->date()
                    ->sortable(),
            ])
            ->defaultSort('id', 'desc')
            ->striped()
            ->filters([
                SelectFilter::make('installment_bank_id')
                    ->label('المصرف')
                    ->relationship('installmentBank', 'name'),
            ])
            ->recordActions([
                ViewAction::make()->iconButton(),
                Action::make('edit')
                    ->iconButton()
                    ->icon('heroicon-m-pencil')
                    ->color('info')
                    ->iconSize(IconSize::Small)
                    ->visible(fn (): bool => Auth::user()?->can('تعديل عقود') || Auth::user()?->is_prog)
                    ->url(fn (InstallmentContract $record): string => CompanySettings::linkSalesToInstallments()
                        ? EditInstallmentContract::getUrl(['record' => $record->getKey()])
                        : InstallmentContractResource::getUrl('edit', ['record' => $record])),
                Action::make('cancel')
                    ->label('الغاء')
                    ->icon('heroicon-m-trash')
                    ->iconButton()
                    ->color('danger')
                    ->iconSize(IconSize::Small)
                    ->requiresConfirmation()
                    ->visible(fn (): bool => Auth::user()?->can('الغاء عقود') || Auth::user()?->is_prog)
                    ->action(fn (InstallmentContract $record) => app(InstallmentContractService::class)->cancel($record)),
            ]);
    }
}
