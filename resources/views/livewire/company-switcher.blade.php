@if (auth()->user()?->is_prog)
    <div class="fi-company-switcher min-w-[12rem]">
        {{ $this->form }}
    </div>
@endif
