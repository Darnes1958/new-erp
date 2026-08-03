<?php

namespace App\Filament\Ins\Resources\InstallmentSurpluses\Pages;

use App\Filament\Ins\Resources\InstallmentSurpluses\InstallmentSurplusResource;
use App\Models\InstallmentContract;
use App\Models\InstallmentContractArchive;
use App\Services\Installments\InstallmentSurplusService;
use App\Support\InstallmentContractMorphSelect;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Section;
use Illuminate\Database\Eloquent\Model;

class CreateInstallmentSurplus extends CreateRecord
{
    protected static string $resource = InstallmentSurplusResource::class;

    protected static ?string $title = '';

    protected static bool $canCreateAnother = false;

    protected string $view = 'filament.ins.pages.create-installment-surplus';

    public function mount(): void
    {
        parent::mount();

        $this->restorePreviousContractableType();
    }

    public function form(\Filament\Schemas\Schema $schema): \Filament\Schemas\Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->schema([
                        InstallmentContractMorphSelect::make('contractable')
                            ->modifyKeySelectUsing(fn (Select $select): Select => $select
                                ->live()
                                ->afterStateUpdated(function (?string $state): void {
                                    if (filled($state)) {
                                        $this->focusField('amount');
                                    }
                                })),
                        DatePicker::make('surplus_date')
                            ->label('التاريخ')
                            ->default(now())
                            ->required(),
                        TextInput::make('amount')
                            ->label('المبلغ')
                            ->id('amount')
                            ->numeric()
                            ->required(),
                    ]),
            ]);
    }

    protected function handleRecordCreation(array $data): Model
    {
        return app(InstallmentSurplusService::class)->createManual(
            $this->resolveContractable($data),
            $data['surplus_date'],
            (float) $data['amount'],
        );
    }

    protected function resolveContractable(array $data): InstallmentContract|InstallmentContractArchive
    {
        $type = $data['contractable_type'] ?? InstallmentContract::class;
        $id = (int) ($data['contractable_id'] ?? 0);

        if ($type === InstallmentContractArchive::class || $type === 'installment_contract_archive') {
            return InstallmentContractArchive::query()->findOrFail($id);
        }

        return InstallmentContract::query()->findOrFail($id);
    }

    protected function afterCreate(): void
    {
        $contractableType = $this->form->getRawState()['contractable_type'] ?? null;

        if (filled($contractableType)) {
            session()->flash('installment_surplus_create.contractable_type', $contractableType);
        }
    }

    protected function restorePreviousContractableType(): void
    {
        $contractableType = session()->pull('installment_surplus_create.contractable_type');

        if (blank($contractableType)) {
            return;
        }

        $this->form->fill([
            'contractable_type' => $contractableType,
            'contractable_id' => null,
        ]);
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('create');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'تم إضافة الفائض';
    }

    public function focusField(string $field): void
    {
        $this->dispatch('focus-field', field: $field);
    }
}
