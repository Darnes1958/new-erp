<?php

namespace App\Filament\Market\Resources\InventoryCountSessions\Schemas;

use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class InventoryCountSessionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->schema([
                    TextInput::make('title')
                        ->label('البيان')
                        ->helperText('شرح مختصر لسبب عملية الجرد')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('notes')
                        ->label('ملاحظات')
                        ->maxLength(255),
                    TextInput::make('year')
                        ->label('السنة')
                        ->numeric()
                        ->required()
                        ->default(fn (): int => (int) now()->format('Y')),
                    Toggle::make('is_active')
                        ->label('نشط')
                        ->default(true),
                    Hidden::make('created_by')
                        ->default(fn (): ?int => Auth::id()),
                ])
                ->columns(1),
        ]);
    }
}
