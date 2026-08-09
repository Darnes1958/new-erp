<?php

namespace App\Filament\Market\Resources\Items\Pages;

use App\Filament\Market\Resources\Items\ItemResource;
use App\Models\Item;
use App\Models\ItemType;
use App\Services\Pdf\ItemLabelPdfService;
use App\Support\PdfDownload;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListItems extends ListRecords
{
    protected static string $resource = ItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('printLabels')
                ->label('طباعة ملصقات')
                ->icon('heroicon-o-printer')
                ->color('primary')
                ->schema([
                    Select::make('item_type_id')
                        ->label('التصنيف')
                        ->options(fn (): array => ItemType::query()->orderBy('name')->pluck('name', 'id')->all())
                        ->required()
                        ->searchable(),
                ])
                ->action(function (array $data): mixed {
                    $items = Item::query()
                        ->where('item_type_id', $data['item_type_id'])
                        ->orderBy('id')
                        ->get();

                    if ($items->isEmpty()) {
                        Notification::make()
                            ->title('لا توجد أصناف لهذا التصنيف')
                            ->warning()
                            ->send();

                        return null;
                    }

                    return PdfDownload::streamed(
                        app(ItemLabelPdfService::class)->forItems($items),
                    );
                }),
            CreateAction::make()
                ->label('إضافة صنف'),
        ];
    }
}
