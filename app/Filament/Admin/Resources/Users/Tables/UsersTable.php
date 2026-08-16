<?php

namespace App\Filament\Admin\Resources\Users\Tables;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
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
                    ->with(['roles', 'permissions'])
                    ->where('company', Auth::user()?->company)
                    ->where('is_prog', false);
            })
            ->columns([
                TextColumn::make('id')
                    ->label('الرقم')
                    ->sortable(),
                ImageColumn::make('avatar_path')
                    ->label('الصورة')
                    ->disk('public')
                    ->circular()
                    ->imageSize(40)
                    ->checkFileExistence(false),
                TextColumn::make('name')
                    ->label('الاسم')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label('البريد')
                    ->searchable(),
                TextColumn::make('roles.name')
                    ->label('الأدوار')
                    ->badge()
                    ->limitList(2)
                    ->expandableLimitedList(),
                TextColumn::make('permissions.name')
                    ->label('الصلاحيات')
                    ->badge()
                    ->limitList(3)
                    ->expandableLimitedList(),
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
                Action::make('changePassword')
                    ->label('تغيير كلمة المرور')
                    ->icon('heroicon-o-key')
                    ->iconButton()
                    ->color('warning')
                    ->modalHeading('تغيير كلمة المرور')
                    ->modalSubmitActionLabel('حفظ')
                    ->visible(fn (): bool => (bool) Auth::user()?->is_prog)
                    ->schema([
                        TextInput::make('password')
                            ->label('كلمة المرور الجديدة')
                            ->password()
                            ->revealable()
                            ->required()
                            ->minLength(8)
                            ->confirmed(),
                        TextInput::make('password_confirmation')
                            ->label('تأكيد كلمة المرور')
                            ->password()
                            ->revealable()
                            ->required()
                            ->minLength(8),
                    ])
                    ->action(function (User $record, array $data): void {
                        $record->update([
                            'password' => $data['password'],
                        ]);

                        Notification::make()
                            ->title('تم تغيير كلمة المرور')
                            ->success()
                            ->send();
                    }),
                DeleteAction::make()->iconButton(),
            ]);
    }
}
