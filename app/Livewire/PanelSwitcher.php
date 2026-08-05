<?php

namespace App\Livewire;

use App\Support\PanelNavigation;
use Filament\Facades\Filament;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class PanelSwitcher extends Component implements HasSchemas
{
    use InteractsWithSchemas;

    public ?string $panel = null;

    public function mount(): void
    {
        $this->panel = Filament::getCurrentPanel()?->getId();
    }

    public function form(Schema $schema): Schema
    {
        if (! PanelNavigation::hasMultiplePanels()) {
            return $schema->components([]);
        }

        return $schema
            ->components([
                ToggleButtons::make('panel')
                    ->hiddenLabel()
                    ->live()
                    ->inline()
                    ->options(PanelNavigation::toggleOptions())
                    ->colors(PanelNavigation::toggleColors())
                    ->icons(PanelNavigation::toggleIcons())
                    ->afterStateUpdated(function (?string $state): void {
                        if (! filled($state)) {
                            return;
                        }

                        $panel = PanelNavigation::panelForId($state);
                        $user = Auth::user();

                        if (! $panel || ! $user?->canAccessPanel($panel)) {
                            return;
                        }

                        $this->redirect($panel->getUrl(), navigate: false);
                    }),
            ]);
    }

    public function render()
    {
        return view('livewire.panel-switcher');
    }
}
