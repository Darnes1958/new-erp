<?php

namespace App\Livewire\Ins;

use App\Models\DeductionBatchLine;
use App\Models\InstallmentContract;
use App\Models\PayrollBank;
use App\Models\Workplace;
use App\Services\Installments\DeductionBatchService;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class CorrectWrongBatchLineContractPicker extends Component implements HasActions, HasForms, HasTable
{
    use InteractsWithActions;
    use InteractsWithForms;
    use InteractsWithTable;

    public int $lineId;

    public string $accountNumber = '';

    public ?int $payrollBankId = null;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                InstallmentContract::query()
                    ->with(['customer', 'installmentBank', 'workplace', 'payrollBank'])
            )
            ->searchable()
            ->persistSearchInSession(false)
            ->persistFiltersInSession(false)
            ->searchPlaceholder('بحث بالرقم / الزبون / الحساب / الجهة')
            ->columns([
                TextColumn::make('id')
                    ->label('العقد')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('customer.name')
                    ->label('الزبون')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('bank_account_number')
                    ->label('حساب العقد')
                    ->searchable()
                    ->copyable(),
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
                TextColumn::make('workplace.name')
                    ->label('الجهة')
                    ->searchable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('payroll_bank_id')
                    ->label('المصرف التجميعي')
                    ->options(fn (): array => PayrollBank::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->preload()
                    ->default($this->payrollBankId),
                SelectFilter::make('workplace_id')
                    ->label('الجهة')
                    ->options(fn (): array => Workplace::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->preload(),
            ])
            ->defaultSort('id', 'desc')
            ->paginated([10, 25, 50])
            ->recordActions([
                Action::make('pick')
                    ->label('اختيار')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->action(fn (InstallmentContract $record) => $this->pick($record)),
            ])
            ->recordUrl(null)
            ->striped()
            ->emptyStateHeading('لا توجد عقود مطابقة للبحث');
    }

    public function pick(InstallmentContract $contract): void
    {
        $line = DeductionBatchLine::query()->findOrFail($this->lineId);

        try {
            $count = app(DeductionBatchService::class)->correctWrongAccountLines($line, $contract);
        } catch (ValidationException $exception) {
            Notification::make()
                ->title(collect($exception->errors())->flatten()->first() ?: 'تعذر التصحيح')
                ->danger()
                ->send();

            return;
        }

        $this->dispatch('wrong-batch-line-corrected', count: $count);
    }

    public function render()
    {
        return view('livewire.ins.correct-wrong-batch-line-contract-picker');
    }
}
