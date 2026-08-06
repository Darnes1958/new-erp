<?php

namespace App\Filament\Market\Pages\InpWarehouseTransfer\Schemas;

use App\Filament\Market\Resources\WarehouseTransfers\WarehouseTransferResource;
use App\Models\Warehouse;
use App\Models\WarehouseTransfer;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\VerticalAlignment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class TransferHeaderForm
{
    public static function configure(Schema $schema, object $page): Schema
    {
        return $schema
            ->model(WarehouseTransfer::class)
            ->statePath('headerData')
            ->columns(6)
            ->components([
                Section::make()
                    ->schema([
                        Select::make('warehouse_from_id')
                            ->label('مــــن')
                            ->relationship('warehouseFrom', 'name', modifyQueryUsing: fn (Builder $query) => $query->where('is_active', true))
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->disabled(fn (): bool => filled($page->tableData))
                            ->afterStateUpdated(function ($state) use ($page): void {
                                $page->warehouseFromId = $state ? (int) $state : null;
                                $page->warehouseFromName = $state
                                    ? Warehouse::query()->find($state)?->name
                                    : null;
                            })
                            ->columnSpan(2),
                        Select::make('warehouse_to_id')
                            ->label('إلـــــي')
                            ->relationship(
                                'warehouseTo',
                                'name',
                                modifyQueryUsing: fn (Builder $query, Get $get) => $query
                                    ->where('is_active', true)
                                    ->when(
                                        filled($get('warehouse_from_id')),
                                        fn (Builder $query) => $query->where('id', '!=', $get('warehouse_from_id')),
                                    ),
                            )
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->disabled(fn (): bool => ! filled($page->warehouseFromId))
                            ->afterStateUpdated(fn ($state) => $page->warehouseToId = $state ? (int) $state : null)
                            ->columnSpan(2),
                        DatePicker::make('transfer_date')
                            ->label('التاريخ')
                            ->required()
                            ->default(fn () => now()->toDateString())
                            ->id('transfer_date'),
                        Hidden::make('created_by')->default(fn () => Auth::id()),
                        Actions::make([
                            Action::make('goBack')
                                ->label('عودة')
                                ->url(fn (): string => WarehouseTransferResource::getUrl())
                                ->color('gray'),
                            Action::make('store')
                                ->label('تخزين')
                                ->color('success')
                                ->visible(fn (): bool => filled($page->tableData))
                                ->requiresConfirmation()
                                ->action(fn () => $page->storeTransfer()),
                        ])
                            ->verticalAlignment(VerticalAlignment::Center)
                            ->alignEnd(),
                    ])
                    ->columns(6)
                    ->columnSpanFull(),
            ]);
    }
}
