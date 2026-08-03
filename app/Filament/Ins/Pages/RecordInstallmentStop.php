<?php

namespace App\Filament\Ins\Pages;

use App\Services\Installments\InstallmentStopService;
use Carbon\Carbon;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

class RecordInstallmentStop extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static ?string $navigationLabel = 'إيقاف خصم';

    protected static ?string $slug = 'record-installment-stop';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-no-symbol';

    protected static string|\UnitEnum|null $navigationGroup = 'خصومات ومدفوعات';

    protected static ?int $navigationSort = 7;

    protected string $view = 'filament.ins.pages.record-installment-stop';

    protected ?string $heading = '';

    public ?string $stop_date = null;

    public static function getNavigationBadge(): ?string
    {
        return (string) app(InstallmentStopService::class)->eligibleCount();
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user?->is_prog || $user?->can('ادخال عقود') || $user?->can('تعديل عقود');
    }

    public function mount(): void
    {
        $this->stop_date = now()->toDateString();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                DatePicker::make('stop_date')
                    ->label('تاريخ الإيقاف')
                    ->required()
                    ->inlineLabel(),
            ])
            ->columns(4);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => app(InstallmentStopService::class)->eligibleContractsQuery())
            ->emptyStateHeading('لا توجد عقود منتهية لإيقافها')
            ->columns([
                TextColumn::make('id')
                    ->label('رقم العقد')
                    ->sortable(),
                TextColumn::make('customer.name')
                    ->label('الاسم')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('bank_account_number')
                    ->label('رقم الحساب')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('balance')
                    ->label('الرصيد')
                    ->numeric(3),
            ])
            ->toolbarActions([
                BulkAction::make('stop')
                    ->label('إيقاف')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->deselectRecordsAfterCompletion()
                    ->action(function (Collection $records): void {
                        $count = app(InstallmentStopService::class)->stopMany(
                            $records,
                            Carbon::parse($this->stop_date ?? now()->toDateString()),
                        );

                        Notification::make()
                            ->title("تم إيقاف {$count} عقد")
                            ->success()
                            ->send();

                        $this->resetTable();
                    }),
            ])
            ->striped();
    }
}
