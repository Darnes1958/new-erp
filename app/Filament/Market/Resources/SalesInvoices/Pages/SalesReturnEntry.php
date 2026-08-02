<?php

namespace App\Filament\Market\Resources\SalesInvoices\Pages;

use App\Filament\Market\Resources\SalesInvoices\SalesInvoiceResource;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceLine;
use App\Models\SalesReturn;
use App\Services\Inventory\SalesReturnService;
use App\Support\CompanySettings;
use App\Support\ErpNumber;
use App\Support\ProgrammingError;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Throwable;

class SalesReturnEntry extends Page implements HasSchemas, HasTable
{
    use InteractsWithRecord;
    use InteractsWithSchemas;
    use InteractsWithTable;

    protected static string $resource = SalesInvoiceResource::class;

    protected static ?string $navigationLabel = 'ترجيع مبيعات';

    protected string $view = 'filament.market.pages.return-entry';

    public SalesInvoice $invoice;

    public array $returnData = [];

    public float $maxQty = 1;

    public ?SalesInvoiceLine $selectedLine = null;

    public static function canAccess(array $parameters = []): bool
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

    public static function shouldRegisterNavigation(array $parameters = []): bool
    {
        return false;
    }

    public function getHeading(): string
    {
        return 'ترجيع فاتورة مبيعات رقم '.(string) $this->invoice->id;
    }

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        $this->invoice = $this->record->load('customer');

        $this->fillReturnForm();
    }

    protected function fillReturnForm(array $extra = []): void
    {
        $this->returnForm->fill(array_merge([
            'sales_invoice_id' => $this->invoice->id,
            'customer_name' => $this->invoice->customer?->name,
            'invoice_date' => $this->invoice->invoice_date,
            'grand_total' => ErpNumber::money($this->invoice->grand_total),
            'return_date' => now(),
            'qty_primary' => 1,
            'item_id' => null,
        ], $extra));
    }

    public function returnForm(Schema $schema): Schema
    {
        return $schema
            ->statePath('returnData')
            ->defaultNumberLocale(ErpNumber::locale())
            ->components([
                Section::make()->schema([
                    TextInput::make('sales_invoice_id')
                        ->hiddenLabel()
                        ->prefix('رقم الفاتورة')
                        ->columnSpan(2)
                        ->disabled(),
                    DatePicker::make('return_date')
                        ->hiddenLabel()
                        ->prefix('تاريخ الترجيع')
                        ->columnSpan(2)
                        ->required()
                        ->autofocus()
                        ->id('return_date'),
                    TextInput::make('customer_name')
                        ->hiddenLabel()
                        ->prefix('الزبون')
                        ->columnSpan(4)
                        ->disabled(),
                    TextInput::make('invoice_date')
                        ->hiddenLabel()
                        ->prefix('تاريخ الفاتورة')
                        ->columnSpan(2)
                        ->disabled(),
                    TextInput::make('grand_total')
                        ->hiddenLabel()
                        ->prefix('إجمالي الفاتورة')
                        ->columnSpan(2)
                        ->disabled(),
                    Select::make('item_id')
                        ->options(fn (): array => SalesInvoiceLine::query()
                            ->where('sales_invoice_id', $this->invoice->id)
                            ->whereNull('sales_return_id')
                            ->where('qty_primary', '>', 0)
                            ->with('item')
                            ->get()
                            ->mapWithKeys(fn (SalesInvoiceLine $line): array => [
                                (int) $line->item_id => $line->item?->name ?? (string) $line->item_id,
                            ])
                            ->all())
                        ->hiddenLabel()
                        ->prefix('الصنف')
                        ->columnSpan(4)
                        ->live()
                        ->required()
                        ->afterStateUpdated(function (?string $state): void {
                            if (! $state) {
                                $this->selectedLine = null;
                                $this->maxQty = 1;

                                return;
                            }

                            $this->selectedLine = SalesInvoiceLine::query()
                                ->where('sales_invoice_id', $this->invoice->id)
                                ->where('item_id', (int) $state)
                                ->whereNull('sales_return_id')
                                ->first();

                            $this->maxQty = (float) ($this->selectedLine?->qty_primary ?? 1);

                            $this->returnForm->fill(array_merge($this->returnData, [
                                'unit_price_primary' => $this->selectedLine?->unit_price_primary,
                                'qty_primary' => min((float) ($this->returnData['qty_primary'] ?? 1), $this->maxQty),
                            ]));

                            $this->dispatch('focus-field', field: 'qty_primary');
                        })
                        ->id('item_id'),
                    TextInput::make('qty_primary')
                        ->hiddenLabel()
                        ->prefix('الكمية')
                        ->columnSpan(2)
                        ->numeric()
                        ->required()
                        ->minValue(0.001)
                        ->maxValue(fn (): float => $this->maxQty)
                        ->live()
                        ->afterStateUpdated(function ($state, callable $set): void {
                            if ((float) $state > $this->maxQty) {
                                $set('qty_primary', $this->maxQty);
                            }
                        })
                        ->readOnly(fn (Get $get): bool => ! filled($get('item_id')))
                        ->id('qty_primary'),
                    Hidden::make('unit_price_primary'),
                    Actions::make([
                        Action::make('store')
                            ->label('تخزين')
                            ->icon('heroicon-m-plus')
                            ->button()
                            ->color('success')
                            ->visible(fn (Get $get): bool => filled($get('item_id')) && (float) ($get('qty_primary') ?? 0) > 0)
                            ->requiresConfirmation()
                            ->action(fn () => $this->storeReturn()),
                    ])->columnSpanFull(),
                ])->columns(4),
            ]);
    }

    public function storeReturn(): void
    {
        $this->returnForm->validate();

        $data = $this->returnForm->getState();
        $line = SalesInvoiceLine::query()
            ->where('sales_invoice_id', $this->invoice->id)
            ->where('item_id', (int) $data['item_id'])
            ->whereNull('sales_return_id')
            ->first();

        if (! $line) {
            Notification::make()->title('البند غير متاح للترجيع')->warning()->send();

            return;
        }

        try {
            DB::connection($line->getConnectionName())->transaction(function () use ($data, $line): void {
                app(SalesReturnService::class)->applyReturn(
                    $line,
                    (float) $data['qty_primary'],
                    0,
                    Carbon::parse($data['return_date']),
                );
            });
        } catch (Throwable $exception) {
            ProgrammingError::notify($exception);

            return;
        }

        $this->invoice->refresh();
        $this->fillReturnForm();
        $this->selectedLine = null;
        $this->maxQty = 1;
        $this->resetTable();

        Notification::make()->title('تم تخزين الترجيع بنجاح')->success()->send();
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultNumberLocale(ErpNumber::locale())
            ->query(fn () => SalesInvoiceLine::query()
                ->where('sales_invoice_id', $this->invoice->id)
                ->with(['item', 'salesReturn']))
            ->columns([
                TextColumn::make('item_id')->label('رقم الصنف')->sortable(),
                TextColumn::make('barcode')->label('الباركود')->searchable(),
                TextColumn::make('item.name')
                    ->label('اسم الصنف')
                    ->description(fn (SalesInvoiceLine $record): ?string => $record->sales_return_id
                        ? 'كمية مرجعة ('.ErpNumber::quantity($record->salesReturn?->qty_primary).') بتاريخ '.$record->salesReturn?->return_date?->format('Y-m-d')
                        : null)
                    ->color(fn (SalesInvoiceLine $record): string => $record->sales_return_id ? 'primary' : 'info')
                    ->sortable(),
                TextColumn::make('qty_primary')
                    ->label('الكمية')
                    ->numeric(3),
                TextColumn::make('qty_secondary')
                    ->label('صغري')
                    ->visible(CompanySettings::hasDualUnit())
                    ->formatStateUsing(fn ($state) => (float) $state == 0.0 ? '' : ErpNumber::quantity($state)),
                TextColumn::make('unit_price_primary')
                    ->label('سعر البيع')
                    ->numeric(3),
                TextColumn::make('line_total')
                    ->label('المجموع')
                    ->numeric(3),
            ])
            ->recordActions([
                Action::make('cancel_return')
                    ->icon('heroicon-m-trash')
                    ->iconButton()
                    ->color('primary')
                    ->tooltip('إلغاء الترجيع')
                    ->visible(fn (SalesInvoiceLine $record): bool => $record->sales_return_id !== null)
                    ->requiresConfirmation()
                    ->modalHeading('إلغاء الترجيع')
                    ->modalDescription('هل أنت متأكد من إلغاء ترجيع هذا البند؟')
                    ->action(function (SalesInvoiceLine $record): void {
                        $salesReturn = SalesReturn::query()->find($record->sales_return_id);

                        if (! $salesReturn) {
                            Notification::make()->title('سجل الترجيع غير موجود')->warning()->send();

                            return;
                        }

                        try {
                            DB::connection($record->getConnectionName())->transaction(function () use ($salesReturn): void {
                                app(SalesReturnService::class)->cancelReturn($salesReturn);
                            });
                        } catch (Throwable $exception) {
                            ProgrammingError::notify($exception);

                            return;
                        }

                        $this->invoice->refresh();
                        $this->fillReturnForm();
                        $this->resetTable();

                        Notification::make()->title('تم إلغاء الترجيع')->success()->send();
                    }),
            ])
            ->striped();
    }
}
