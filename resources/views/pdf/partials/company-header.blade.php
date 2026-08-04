<div>
    @if ($company?->logoAbsolutePath())
        <div style="margin-bottom: 6px;">
            <img src="{{ $company->logoAbsolutePath() }}" alt="" style="max-height: 70px; max-width: 180px;">
        </div>
    @endif

    <label style="font-size: {{ $nameSize ?? '20pt' }}; margin-right: 12px;">
        {{ \App\Support\Utf8Text::clean($company?->display_name) }}
    </label>

    @if ($company?->display_name_suffix)
        <div>
            <label style="font-size: {{ $suffixSize ?? '16pt' }}; margin-right: 12px;">
                {{ \App\Support\Utf8Text::clean($company->display_name_suffix) }}
            </label>
        </div>
    @endif

    @if ($company?->address)
        <div>
            <label style="font-size: {{ $addressSize ?? '14pt' }}; margin-right: 12px;">
                {{ \App\Support\Utf8Text::clean($company->address) }}
            </label>
        </div>
    @endif

    @if ($company?->phone)
        <div>
            <label style="font-size: {{ $phoneSize ?? '12pt' }}; margin-right: 12px;">
                {{ \App\Support\Utf8Text::clean($company->phone) }}
            </label>
        </div>
    @endif
</div>
