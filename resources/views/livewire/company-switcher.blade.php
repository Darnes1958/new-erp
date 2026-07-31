@if (auth()->user()?->is_prog)
    <div class="fi-company-switcher me-2 min-w-[12rem]">
        {{ $this->form }}
    </div>
@endif
