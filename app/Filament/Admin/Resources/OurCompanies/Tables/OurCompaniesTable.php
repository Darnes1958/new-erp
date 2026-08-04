<?php

namespace App\Filament\Admin\Resources\OurCompanies\Tables;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class OurCompaniesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('connection_name')
                    ->label('الاتصال')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('display_name')
                    ->label('الاسم')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('display_name_suffix')
                    ->label('اللاحقة')
                    ->wrap()
                    ->toggleable(),
                TextColumn::make('comp_code')
                    ->label('الرمز')
                    ->toggleable(isToggledHiddenByDefault: true),
                ImageColumn::make('logo_path')
                    ->label('الشعار')
                    ->disk('public')
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_active')
                    ->label('نشطة')
                    ->boolean(),
            ])
            ->recordActions([
                EditAction::make()->iconButton(),
                DeleteAction::make()
                    ->iconButton()
                    ->visible(fn (): bool => (bool) Auth::user()?->is_prog),
            ]);
    }
}
