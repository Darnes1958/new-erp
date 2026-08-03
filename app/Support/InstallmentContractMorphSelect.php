<?php

namespace App\Support;

use App\Models\InstallmentContract;
use App\Models\InstallmentContractArchive;
use Filament\Forms\Components\MorphToSelect;
use Filament\Forms\Components\MorphToSelect\Type;
use Illuminate\Database\Eloquent\Builder;

class InstallmentContractMorphSelect
{
    public static function make(string $name = 'contractable'): MorphToSelect
    {
        return MorphToSelect::make($name)
            ->types([
                Type::make(InstallmentContract::class)
                    ->titleAttribute('id')
                    ->label('العقود القائمة')
                    ->getOptionLabelFromRecordUsing(fn (InstallmentContract $record): string => self::contractLabel($record))
                    ->getSearchResultsUsing(fn (?string $search): array => self::searchContracts(InstallmentContract::query(), $search)),
                Type::make(InstallmentContractArchive::class)
                    ->titleAttribute('id')
                    ->label('الأرشيف')
                    ->getOptionLabelFromRecordUsing(fn (InstallmentContractArchive $record): string => self::contractLabel($record))
                    ->getSearchResultsUsing(fn (?string $search): array => self::searchContracts(InstallmentContractArchive::query(), $search)),
            ])
            ->searchable()
            ->preload()
            ->required()
            ->label('العقد');
    }

    /**
     * @param  Builder<InstallmentContract|InstallmentContractArchive>  $query
     * @return array<int|string, string>
     */
    protected static function searchContracts(Builder $query, ?string $search): array
    {
        if ($search) {
            $query->where(function (Builder $inner) use ($search): void {
                $inner->where('id', 'like', "%{$search}%")
                    ->orWhereHas('customer', fn (Builder $customer) => $customer->where('name', 'like', "%{$search}%"));
            });
        }

        return $query
            ->with('customer')
            ->limit(50)
            ->get()
            ->mapWithKeys(fn ($record): array => [$record->id => self::contractLabel($record)])
            ->all();
    }

    protected static function contractLabel(InstallmentContract|InstallmentContractArchive $record): string
    {
        $name = $record->customer?->name ?? '—';

        return "{$record->id} — {$name}";
    }
}
