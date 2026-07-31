<?php

namespace App\Filament\Admin\Resources\Users\Tables;

use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query): Builder {
                return $query
                    ->where('company', Auth::user()?->company)
                    ->where('is_prog', false);
            })
            ->columns([
                TextColumn::make('id')
                    ->label('الرقم')
                    ->sortable(),
                TextColumn::make('name')
                    ->label('الاسم')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label('البريد')
                    ->searchable(),
                TextColumn::make('roles.name')
                    ->label('الأدوار')
                    ->badge(),
                IconColumn::make('status')
                    ->label('الحالة')
                    ->boolean(),
                TextColumn::make('warehouse_id')
                    ->label('المخزن')
                    ->formatStateUsing(function (?int $state, User $record): ?string {
                        if (! $state || ! $record->company) {
                            return null;
                        }

                        return DB::connection($record->company)
                            ->table('warehouses')
                            ->where('id', $state)
                            ->value('name');
                    }),
                TextColumn::make('company')
                    ->label('الشركة')
                    ->visible(fn (): bool => (bool) Auth::user()?->is_prog),
            ])
            ->recordActions([
                EditAction::make()->iconButton(),
                DeleteAction::make()->iconButton(),
            ]);
    }
}
