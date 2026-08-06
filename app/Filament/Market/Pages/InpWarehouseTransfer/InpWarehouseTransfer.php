<?php

namespace App\Filament\Market\Pages\InpWarehouseTransfer;

use App\Filament\Market\Pages\InpWarehouseTransfer\Schemas\TransferHeaderForm;
use App\Filament\Market\Pages\InpWarehouseTransfer\Schemas\TransferLineForm;
use App\Filament\Market\Support\MarketNavigationGroup;
use App\Models\Item;
use App\Models\ItemBarcode;
use App\Services\Inventory\WarehouseTransferService;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;
use RuntimeException;

class InpWarehouseTransfer extends Page implements HasSchemas, HasTable
{
    use InteractsWithSchemas;
    use InteractsWithTable;

    protected static ?string $navigationLabel = 'إذن نقل جديد';

    protected static bool $shouldRegisterNavigation = false;

    protected static string|\UnitEnum|null $navigationGroup = MarketNavigationGroup::WarehousesItems;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?string $slug = 'inp-warehouse-transfer';

    protected string $view = 'filament.market.pages.inp-warehouse-transfer';

    protected ?string $heading = '';

    public ?int $warehouseFromId = null;

    public ?int $warehouseToId = null;

    public ?string $warehouseFromName = null;

    /** @var array<string, mixed> */
    public array $headerData = [];

    /** @var array<string, mixed> */
    public array $lineData = [];

    /** @var array<int, array<string, mixed>> */
    public array $tableData = [];

    public static function canAccess(): bool
    {
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        if ($user->is_prog) {
            return true;
        }

        return $user->can('نقل أصناف');
    }

    public function mount(): void
    {
        $this->headerData = [
            'transfer_date' => now()->toDateString(),
            'created_by' => Auth::id(),
        ];

        $this->headerForm->fill($this->headerData);
        $this->lineForm->fill([]);
    }

    public function headerForm(Schema $schema): Schema
    {
        return TransferHeaderForm::configure($schema, $this);
    }

    public function lineForm(Schema $schema): Schema
    {
        return TransferLineForm::configure($schema, $this);
    }

    public function checkBarcode(?string $barcode): void
    {
        if (! filled($barcode)) {
            return;
        }

        $itemBarcode = ItemBarcode::query()
            ->where('barcode', $barcode)
            ->orWhere('id', $barcode)
            ->first();

        if ($itemBarcode) {
            $this->gotoQuantity((int) $itemBarcode->item_id, (string) ($itemBarcode->barcode ?? $barcode));

            return;
        }

        $item = Item::query()->where('barcode', $barcode)->first();

        if ($item) {
            $this->gotoQuantity((int) $item->id, (string) $item->barcode);
        }
    }

    public function checkItem(mixed $itemId): void
    {
        if ($itemId === null || $itemId === '') {
            return;
        }

        $item = Item::query()->find($itemId);

        if ($item) {
            $this->gotoQuantity((int) $item->id, (string) $item->barcode);
        }
    }

    public function gotoQuantity(int $itemId, string $barcode): void
    {
        if (! $this->warehouseFromId) {
            Notification::make()
                ->title('يجب اختيار المخزن أو الصالة المراد النقل منها')
                ->danger()
                ->send();

            return;
        }

        $stock = app(WarehouseTransferService::class)->warehouseStockQty($itemId, $this->warehouseFromId);

        if ($stock <= 0) {
            return;
        }

        $existingIndex = collect($this->tableData)->search(
            fn (array $row): bool => (int) ($row['item_id'] ?? 0) === $itemId,
        );

        $existingQty = $existingIndex !== false
            ? ($this->tableData[$existingIndex]['qty_primary'] ?? '')
            : '';

        $this->lineData = [
            'barcode' => $barcode,
            'item_id' => $itemId,
            'qty_primary' => $existingQty,
            'stock_display' => $stock,
        ];

        $this->lineForm->fill($this->lineData);
        $this->dispatch('focus-field', field: 'quantity');
    }

    public function checkQuantity(mixed $quantity): void
    {
        $itemId = $this->lineData['item_id'] ?? null;

        if (! $itemId) {
            Notification::make()->title('يجب اختيار الصنف')->danger()->send();
            $this->dispatch('focus-field', field: 'barcode');

            return;
        }

        $qty = (float) $quantity;
        $stock = (float) ($this->lineData['stock_display'] ?? 0);

        if ($qty <= 0) {
            Notification::make()->title('يجب اختيار الكمية')->danger()->send();
            $this->dispatch('focus-field', field: 'quantity');

            return;
        }

        if ($qty > $stock + 0.0001) {
            Notification::make()->title('الرصيد لا يسمح بهذه الكمية')->danger()->send();

            return;
        }

        $this->putRecordToTable((int) $itemId, (string) ($this->lineData['barcode'] ?? ''), $qty);
        $this->lineData = [];
        $this->lineForm->fill([]);
        $this->resetTable();
        $this->dispatch('focus-field', field: 'barcode');
    }

    private function putRecordToTable(int $itemId, string $barcode, float $quantity): void
    {
        $item = Item::query()->find($itemId);
        $existingIndex = collect($this->tableData)->search(
            fn (array $row): bool => (int) ($row['item_id'] ?? 0) === $itemId,
        );

        $record = [
            'item_id' => $itemId,
            'barcode' => $barcode,
            'name' => $item?->name,
            'qty_primary' => $quantity,
        ];

        if ($existingIndex !== false) {
            $this->tableData[$existingIndex] = $record;
        } else {
            $this->tableData[] = $record;
        }
    }

    public function storeTransfer(): void
    {
        if (! $this->warehouseToId) {
            Notification::make()->title('يجب اختيار المكان المنقول إليه')->danger()->send();

            return;
        }

        $transferDate = $this->headerData['transfer_date'] ?? null;

        if (! $transferDate) {
            Notification::make()->title('يجب ادخال التاريخ')->danger()->send();
            $this->dispatch('focus-field', field: 'transfer_date');

            return;
        }

        if ($this->tableData === []) {
            Notification::make()->title('لم يتم ادخال اصناف')->warning()->send();

            return;
        }

        foreach ($this->tableData as $record) {
            $stock = app(WarehouseTransferService::class)->warehouseStockQty(
                (int) $record['item_id'],
                (int) $this->warehouseFromId,
            );

            if ((float) $record['qty_primary'] > $stock + 0.0001) {
                Notification::make()
                    ->title(new HtmlString(
                        '<span>رصيد الصنف </span>'
                        .'<span class="text-primary-600">'.e((string) $record['name']).'</span>'
                        .'<span> لا يكفي</span>',
                    ))
                    ->danger()
                    ->send();

                return;
            }
        }

        try {
            app(WarehouseTransferService::class)->store(
                (int) $this->warehouseFromId,
                (int) $this->warehouseToId,
                Carbon::parse($transferDate),
                collect($this->tableData)->map(fn (array $row): array => [
                    'item_id' => (int) $row['item_id'],
                    'qty_primary' => (float) $row['qty_primary'],
                ])->all(),
            );
        } catch (RuntimeException $exception) {
            Notification::make()->title($exception->getMessage())->danger()->send();

            return;
        } catch (\Throwable) {
            Notification::make()->title('حدث خطأ !!')->danger()->send();

            return;
        }

        Notification::make()->title('تم تخزين إذن النقل بنجاح')->success()->send();

        $this->tableData = [];
        $this->warehouseFromId = null;
        $this->warehouseToId = null;
        $this->warehouseFromName = null;
        $this->headerData = [
            'transfer_date' => now()->toDateString(),
            'created_by' => Auth::id(),
        ];
        $this->headerForm->fill($this->headerData);
        $this->lineForm->fill([]);
        $this->resetTable();
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (): Collection => collect($this->tableData))
            ->columns([
                TextColumn::make('barcode')->label('الباركود'),
                TextColumn::make('item_id')->label('رقم الصنف'),
                TextColumn::make('name')->label('اسم الصنف'),
                TextColumn::make('qty_primary')->label('الكمية'),
            ])
            ->recordActions([
                Action::make('del')
                    ->iconButton()
                    ->icon(Heroicon::XMark)
                    ->color('danger')
                    ->action(function (array $record): void {
                        unset($this->tableData[$record['__key']]);
                        $this->tableData = array_values($this->tableData);
                        $this->resetTable();
                    }),
                Action::make('edit')
                    ->iconButton()
                    ->icon(Heroicon::Pencil)
                    ->color('info')
                    ->action(function (array $record): void {
                        $this->gotoQuantity(
                            (int) $record['item_id'],
                            (string) ($record['barcode'] ?? ''),
                        );
                    }),
            ]);
    }
}
