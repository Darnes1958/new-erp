<?php

namespace App\Filament\Market\Pages\Reports;

use App\Filament\Admin\Support\AdminNavigationGroup;
use App\Filament\Market\Pages\Reports\Concerns\ConfiguresProfitReportTable;
use App\Filament\Market\Pages\Reports\Concerns\ShowsInventoryCountCallouts;
use App\Models\Warehouse;
use App\Services\Market\ProfitReportService;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class WarehouseProfitReportPage extends Page implements HasActions, HasForms, HasTable
{
    use ConfiguresProfitReportTable;
    use ShowsInventoryCountCallouts;
    use InteractsWithActions;
    use InteractsWithForms;
    use InteractsWithTable;

    protected static bool $isDiscovered = false;

    protected static ?string $navigationLabel = 'الأرباح حسب الصالات';

    protected static ?string $title = 'الأرباح حسب الصالات';

    protected static ?string $slug = 'warehouse-profit-report';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBarSquare;

    protected static string|\UnitEnum|null $navigationGroup = AdminNavigationGroup::Management;

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.market.pages.reports.profit-report';

    protected ?string $heading = '';

    public ?int $year = null;

    public ?int $warehouseId = null;

    public static function shouldRegisterNavigation(): bool
    {
        $user = Auth::user();

        return $user?->is_prog || $user?->hasRole('admin');
    }

    public static function canAccess(): bool
    {
        return static::shouldRegisterNavigation();
    }

    public function mount(): void
    {
        $service = app(ProfitReportService::class);
        $years = $service->availableYears();
        $this->year ??= (int) array_key_first($years);
        $this->warehouseId ??= Warehouse::query()->orderBy('id')->value('id');
        $this->refreshFiltersForm();
    }

    protected function getForms(): array
    {
        return ['filtersForm'];
    }

    public function filtersForm(Schema $schema): Schema
    {
        $service = app(ProfitReportService::class);

        return $schema
            ->components([
                Grid::make()
                    ->schema([
                        Select::make('year')
                            ->columnSpan(2)
                            ->label('السنة')
                            ->options(fn (): array => $service->availableYears())
                            ->searchable()
                            ->preload()
                            ->live(),
                        Select::make('warehouseId')
                            ->columnSpan(2)
                            ->label('المكان')
                            ->options(fn (): array => Warehouse::query()->orderBy('name')->pluck('name', 'id')->all())
                            ->searchable()
                            ->preload()
                            ->live(),
                        Placeholder::make('adminExpenses')
                            ->columnSpan(2)
                            ->label('مصروفات الإدارة العامة')
                            ->content(fn (): string => number_format(
                                $service->adminExpensesTotal($this->year ?? (int) now()->format('Y')),
                                3,
                                '.',
                                ',',
                            )),
                        ...$this->inventoryCountCallouts($service),
                    ])
                    ->columns(6),
            ]);
    }

    public function updatedYear(): void
    {
        $this->resetTable();
        $this->refreshFiltersForm();
    }

    public function updatedWarehouseId(): void
    {
        $this->resetTable();
        $this->refreshFiltersForm();
    }

    public function getTableRecordKey(Model|array $record): string
    {
        if (is_array($record)) {
            return (string) ($record['month'] ?? uniqid());
        }

        return (string) $record->getKey();
    }

    public function table(Table $table): Table
    {
        return $this->configureProfitReportTable(
            $table,
            app(ProfitReportService::class),
            $this->year ?? (int) now()->format('Y'),
        )->emptyStateHeading(fn (): string => filled($this->warehouseId) ? 'لا توجد بيانات' : 'اختر المكان');
    }

    protected function profitReportWarehouseId(): ?int
    {
        return $this->warehouseId;
    }

    protected function refreshFiltersForm(): void
    {
        $this->filtersForm->fill([
            'year' => $this->year,
            'warehouseId' => $this->warehouseId,
        ]);
    }
}
