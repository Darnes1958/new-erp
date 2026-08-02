<?php

namespace App\Filament\Ins\Resources\DeductionBatches\Pages;

use App\Filament\Ins\Resources\DeductionBatches\DeductionBatchResource;
use App\Models\InstallmentBank;
use App\Models\PayrollBank;
use App\Support\InstallmentBankScope;
use App\Services\Installments\DeductionBatchService;
use App\Support\CompanySettings;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

class CreateDeductionBatch extends CreateRecord
{
    protected static string $resource = DeductionBatchResource::class;

    protected static ?string $title = 'حافظة جديدة';

    protected static bool $canCreateAnother = false;

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->schema([
                Select::make('payroll_bank_id')
                    ->label('المصرف')
                    ->options(fn (): array => PayrollBank::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->preload()
                    ->live()
                    ->required()
                    ->visible(fn (): bool => CompanySettings::installmentByPayrollBank())
                    ->afterStateUpdated(function (?int $state, Set $set): void {
                        $branch = $state ? InstallmentBankScope::branchForPayroll($state) : null;
                        $set('installment_bank_id', $branch?->id);
                    }),
                Select::make('installment_bank_id')
                    ->label('المصرف')
                    ->options(fn (): array => InstallmentBank::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->preload()
                    ->required()
                    ->visible(fn (): bool => ! CompanySettings::installmentByPayrollBank()),
                DatePicker::make('batch_date')
                    ->label('التاريخ')
                    ->default(now())
                    ->required(),
                Textarea::make('notes')
                    ->label('ملاحظات')
                    ->columnSpanFull(),
            ])->columns(2),
        ]);
    }

    protected function handleRecordCreation(array $data): Model
    {
        return app(DeductionBatchService::class)->create($data);
    }

    protected function getRedirectUrl(): string
    {
        return DeductionBatchResource::getUrl('enter-lines', ['record' => $this->getRecord()]);
    }
}
