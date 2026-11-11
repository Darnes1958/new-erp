<table width="100%" style="width: 100%; border-collapse: collapse; margin-top: 0; margin-bottom: 0;">
    <tr style="line-height: normal;">
        <td style="width: 34%; border: none; text-align: right; vertical-align: top; padding: 0;">
            <label style="font-size: 20pt; margin-right: 12px;">
                {{ \App\Support\Utf8Text::clean($company?->display_name) }}
            </label>

            @if ($company?->display_name_suffix)
                <div>
                    <label style="font-size: 11pt; margin-right: 12px;">
                        {{ \App\Support\Utf8Text::clean($company->display_name_suffix) }}
                    </label>
                </div>
            @endif

            @if ($company?->address)
                <div>
                    <label style="font-size: 14pt; margin-right: 12px;">
                        {{ \App\Support\Utf8Text::clean($company->address) }}
                    </label>
                </div>
            @endif

            @if ($company?->phone)
                <div>
                    <label style="font-size: 12pt; margin-right: 12px;">
                        {{ \App\Support\Utf8Text::clean($company->phone) }}
                    </label>
                </div>
            @endif
        </td>
        <td style="width: 32%; border: none; text-align: center; vertical-align: middle; padding: 0;">
            @php($logoSrc = $company?->logoPdfSrc())
            @if ($logoSrc)
                <img src="{{ $logoSrc }}" alt="" style="max-height: 100px; max-width: 220px;">
            @endif
        </td>
        <td style="width: 34%; border: none; text-align: left; vertical-align: top; padding: 8px 0 0 0; font-size: 9pt; font-family: DejaVu Sans, sans-serif; color: #333;">
            @if (filled($reportDate ?? null))
                التاريخ : {{ $reportDate }}
            @endif
        </td>
    </tr>
</table>
