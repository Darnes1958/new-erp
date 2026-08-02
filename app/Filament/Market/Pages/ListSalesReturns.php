<?php

namespace App\Filament\Market\Pages;

use App\Filament\Market\Support\MarketNavigationGroup;
use App\Models\SalesReturn;
use App\Services\Inventory\SalesReturnService;
use App\Support\ErpNumber;
use App\Support\ProgrammingError;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Throwable;

class ListSalesReturns extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationLabel = 'فواتير مرجعة';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrow-uturn-left';

    protected static string|\UnitEnum|null $navigationGroup = MarketNavigationGroup::SalesInvoices;

    protected static ?int $navigationSort = 6;

    protected static ?string $slug = 'sales-returns';

    protected string $view = 'filament.market.pages.list-table';

    protected static ?string $title = 'فواتير مرجعة';

    public static function canAccess(): bool
    {
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        if ($user->is_prog) {
            return true;
        }

        return $user->can('تعديل مبيعات');
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultNumberLocale(ErpNumber::locale())
            ->query(SalesReturn::query()->with(['salesInvoice.customer', 'item']))
            ->defaultSort('return_date', 'desc')
            ->columns([
                TextColumn::make('id')
                    ->label('الرقم')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('return_date')
                    ->label('التاريخ')
                    ->date()
                    ->sortable(),
                TextColumn::make('sales_invoice_id')
                    ->label('رقم فاتورة المبيعات')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('salesInvoice.customer.name')
                    ->label('الزبون')
                    ->searchable(),
                TextColumn::make('item.name')
                    ->label('الصنف')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('qty_primary')
                    ->label('الكمية')
                    ->numeric(3)
                    ->sortable(),
                TextColumn::make('unit_price_primary')
                    ->label('السعر')
                    ->numeric(3)
                    ->sortable(),
                TextColumn::make('line_total')
                    ->label('الإجمالي')
                    ->numeric(3)
                    ->sortable(),
            ])
            ->recordActions([
                Action::make('cancel')
                    ->label('إلغاء الترجيع')
                    ->icon('heroicon-m-trash')
                    ->requiresConfirmation()
                    ->visible(fn (SalesReturn $record): bool => $record->salesInvoice !== null)
                    ->action(function (SalesReturn $record): void {
                        try {
                            DB::connection($record->getConnectionName())->transaction(function () use ($record): void {
                                app(SalesReturnService::class)->cancelReturn($record);
                            });
                        } catch (Throwable $exception) {
                            ProgrammingError::notify($exception);

                            return;
                        }

                        Notification::make()->title('تم إلغاء الترجيع')->success()->send();
                    }),
            ]);
    }
}
