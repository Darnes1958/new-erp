<?php

namespace App\Filament\Ins\Resources\InstallmentSurpluses\Pages;

use App\Filament\Ins\Resources\InstallmentSurpluses\InstallmentSurplusResource;
use App\Services\Installments\InstallmentSurplusService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Section;

class EditInstallmentSurplus extends EditRecord
{
    protected static string $resource = InstallmentSurplusResource::class;

    public function form(\Filament\Schemas\Schema $schema): \Filament\Schemas\Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->schema([
                        DatePicker::make('surplus_date')
                            ->label('التاريخ')
                            ->required(),
                        TextInput::make('amount')
                            ->label('المبلغ')
                            ->numeric()
                            ->required(),
                    ]),
            ]);
    }

    protected function handleRecordUpdate(\Illuminate\Database\Eloquent\Model $record, array $data): \Illuminate\Database\Eloquent\Model
    {
        return app(InstallmentSurplusService::class)->update(
            $record,
            $data['surplus_date'],
            (float) $data['amount'],
        );
    }
}
