<?php

namespace App\Livewire;

use App\Support\CompanyConnections;
use Filament\Forms\Components\Select;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class CompanySwitcher extends Component implements HasSchemas
{
    use InteractsWithSchemas;

    public ?string $company = null;

    public function mount(): void
    {
        $this->company = Auth::user()?->company;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('company')
                    ->hiddenLabel()
                    ->placeholder('اختر الشركة')
                    ->options(fn (): array => CompanyConnections::options())
                    ->searchable()
                    ->preload()
                    ->live()
                    ->afterStateUpdated(function (?string $state): void {
                        if (! filled($state) || ! CompanyConnections::isValid($state)) {
                            return;
                        }

                        $user = Auth::user();

                        if (! $user || ! $user->is_prog) {
                            return;
                        }

                        $user->update(['company' => $state]);
                        Auth::setUser($user->fresh());

                        $this->redirect(request()->header('Referer') ?? '/', navigate: false);
                    }),
            ]);
    }

    public function render()
    {
        return view('livewire.company-switcher');
    }
}
