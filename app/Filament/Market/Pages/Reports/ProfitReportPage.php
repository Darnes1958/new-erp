<?php

namespace App\Filament\Market\Pages\Reports;

use App\Filament\Admin\Support\AdminNavigationGroup;
use App\Filament\Market\Pages\Reports\Concerns\ConfiguresProfitReportTable;
use App\Filament\Market\Pages\Reports\Concerns\ShowsInventoryCountCallouts;
use App\Services\Market\ProfitReportService;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
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

class ProfitReportPage extends Page implements HasActions, HasForms, HasTable
{
    use ConfiguresProfitReportTable;
    use ShowsInventoryCountCallouts;
    use InteractsWithActions;
    use InteractsWithForms;
    use InteractsWithTable;

    protected static bool $isDiscovered = false;

    protected static ?string $navigationLabel = 'الأرباح';

    protected static ?string $title = 'الأرباح';

    protected static ?string $slug = 'profit-report';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|\UnitEnum|null $navigationGroup = AdminNavigationGroup::Management;

    protected static ?int $navigationSort = 1;

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
        );
    }

    protected function refreshFiltersForm(): void
    {
        $this->filtersForm->fill([
            'year' => $this->year,
        ]);
    }
}
