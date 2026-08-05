@if (\App\Support\PanelNavigation::hasMultiplePanels())
    <div class="fi-panel-switcher">
        {{ $this->form }}
    </div>
@endif
