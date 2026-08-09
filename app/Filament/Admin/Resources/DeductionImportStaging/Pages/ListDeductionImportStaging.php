<?php

namespace App\Filament\Admin\Resources\DeductionImportStaging\Pages;

use App\Filament\Admin\Resources\DeductionImportStaging\DeductionImportStagingResource;
use App\Filament\Admin\Resources\DeductionImportStaging\Widgets\DeductionImportDateRangesWidget;
use App\Filament\Ins\Resources\DeductionBatches\DeductionBatchResource;
use App\Models\BankExcelImportSetting;
use App\Models\InstallmentBank;
use App\Models\PayrollBank;
use App\Services\Installments\DeductionBatchImportService;
use App\Support\CompanySettings;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ListDeductionImportStaging extends ListRecords
{
    protected static string $resource = DeductionImportStagingResource::class;

    protected function getHeaderActions(): array
    {
        $importService = app(DeductionBatchImportService::class);
        $hasSession = $importService->currentSession() !== null;

        return [
            Action::make('setup')
                ->label('إعداد الاستيراد')
                ->icon(Heroicon::OutlinedCog6Tooth)
                ->color('success')
                ->schema([
                    Radio::make('import_mode')
                        ->label('طريقة الاستيراد')
                        ->options([
                            'fixed' => 'أعمدة ثابتة (acc, name, ksm, ksm_date)',
                            'configured' => 'إعدادات مصرف (ExcelSetting)',
                        ])
                        ->default('fixed')
                        ->live()
                        ->required(),
                    Select::make('bank_excel_import_setting_id')
                        ->label('إعداد المصرف')
                        ->options(fn (): array => BankExcelImportSetting::query()->orderBy('name')->pluck('name', 'id')->all())
                        ->searchable()
                        ->preload()
                        ->required()
                        ->visible(fn (Get $get): bool => $get('import_mode') === 'configured'),
                    Select::make('payroll_bank_id')
                        ->label('الحساب التجميعي')
                        ->options(fn (): array => PayrollBank::query()->orderBy('name')->pluck('name', 'id')->all())
                        ->searchable()
                        ->preload()
                        ->live()
                        ->required()
                        ->visible(fn (Get $get): bool => $get('import_mode') === 'fixed'),
                    Select::make('installment_bank_id')
                        ->label('فرع المصرف')
                        ->options(fn (Get $get): array => InstallmentBank::query()
                            ->when($get('payroll_bank_id'), fn ($query, $payrollBankId) => $query->where('payroll_bank_id', $payrollBankId))
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all())
                        ->searchable()
                        ->preload()
                        ->required()
                        ->visible(fn (Get $get): bool => $get('import_mode') === 'fixed'
                            && ! CompanySettings::installmentByPayrollBank()),
                    TextInput::make('heading_row')
                        ->label('رقم سطر العنوان')
                        ->numeric()
                        ->default(10)
                        ->minValue(1)
                        ->required()
                        ->visible(fn (Get $get): bool => $get('import_mode') === 'fixed')
                        ->helperText('في النموذج الثابت، يجب أن تكون رؤوس الأعمدة: acc, name, ksm, ksm_date'),
                ])
                ->action(function (array $data) use ($importService): void {
                    $importService->beginSession($data);

                    Notification::make()
                        ->title('تم إعداد الاستيراد')
                        ->body('يمكنك الآن رفع ملف Excel.')
                        ->success()
                        ->send();
                }),
            Action::make('import')
                ->label('استيراد Excel')
                ->icon(Heroicon::OutlinedArrowUpTray)
                ->color('danger')
                ->disabled(! $hasSession)
                ->schema([
                    FileUpload::make('file')
                        ->label('ملف Excel')
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'application/vnd.ms-excel',
                            'text/csv',
                        ])
                        ->required()
                        ->disk('local')
                        ->directory('deduction-imports')
                        ->visibility('private'),
                ])
                ->action(function (array $data) use ($importService): void {
                    $path = Storage::disk('local')->path($data['file']);

                    try {
                        $count = $importService->importFile($path);
                    } catch (ValidationException $exception) {
                        Notification::make()
                            ->title('فشل الاستيراد')
                            ->body(collect($exception->errors())->flatten()->first())
                            ->danger()
                            ->send();

                        return;
                    } finally {
                        Storage::disk('local')->delete($data['file']);
                    }

                    Notification::make()
                        ->title('تم الاستيراد')
                        ->body("تم استيراد {$count} سطر.")
                        ->success()
                        ->send();
                }),
            Action::make('transfer')
                ->label('ربط بالعقود')
                ->icon(Heroicon::OutlinedLink)
                ->color('primary')
                ->disabled(! $hasSession)
                ->schema([
                    Textarea::make('notes')
                        ->label('ملاحظات الحافظة')
                        ->columnSpanFull(),
                ])
                ->requiresConfirmation()
                ->modalDescription('سيتم إنشاء حافظة جديدة وربط الأسطر المستوردة بالعقود.')
                ->action(function (array $data) use ($importService): void {
                    try {
                        $batch = $importService->transferToBatch($data['notes'] ?? null);
                    } catch (ValidationException $exception) {
                        Notification::make()
                            ->title('فشل الترحيل')
                            ->body(collect($exception->errors())->flatten()->first())
                            ->danger()
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title('تم الترحيل بنجاح')
                        ->success()
                        ->send();

                    $this->redirect(DeductionBatchResource::getUrl(
                        'enter-lines',
                        ['record' => $batch],
                        panel: 'ins',
                    ));
                }),
            Action::make('clear')
                ->label('مسح')
                ->icon(Heroicon::OutlinedTrash)
                ->color('gray')
                ->requiresConfirmation()
                ->visible($hasSession)
                ->action(function () use ($importService): void {
                    $importService->clearCurrentSession();

                    Notification::make()
                        ->title('تم مسح البيانات المؤقتة')
                        ->success()
                        ->send();
                }),
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            DeductionImportDateRangesWidget::class,
        ];
    }
}
