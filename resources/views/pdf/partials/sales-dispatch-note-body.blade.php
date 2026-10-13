<div>
    <table class="invoice-header" style="width: 100%; margin: 0 0 32px 0; border: none;">
        <tr style="border: none;">
            <td style="width: 38%; border: none; vertical-align: top; text-align: right;">
                @include('pdf.partials.company-header', [
                    'company' => $company,
                    'showLogo' => false,
                    'nameSize' => '22pt',
                    'suffixSize' => '11pt',
                    'addressSize' => '11pt',
                    'phoneSize' => '11pt',
                ])
            </td>
            <td style="width: 24%; border: none; vertical-align: top; text-align: center;">
                @if ($company?->logoAbsolutePath())
                    <img src="{{ $company->logoAbsolutePath() }}" alt="" style="max-height: 80px; max-width: 160px;">
                @endif
            </td>
            <td style="width: 38%; border: none; vertical-align: top; text-align: left;">
                <label>{{ $dispatchTitle ?? ('إذن صرف بضاعة للفاتورة رقم : '.$invoice->id) }}</label>
                <div>
                    <label>بتاريخ:</label>
                    <label style="font-size: 12px;">{{ $invoice->invoice_date?->format('Y-m-d') }}</label>
                </div>
            </td>
        </tr>
    </table>

    <div>
        <label style="margin-right: 12px;">اسم الزبون:</label>
        <label>{{ $invoice->customer?->name }}</label>
    </div>
    <div>
        <label style="margin-right: 12px;">صرف من:</label>
        <label>{{ $invoice->warehouse?->name }}</label>
    </div>
    <br>

    <table width="100%" align="right" style="border: none;">
        <thead>
        <tr style="background: #9dc1d3;">
            <th width="12%">رقم الصنف</th>
            <th>اسم الصنف</th>
            <th width="14%">الكمية</th>
            @if ($hasDualUnit)
                <th width="14%">الكمية 2</th>
            @endif
        </tr>
        </thead>
        <tbody>
        @php
            $qtyPrimaryTotal = 0;
            $qtySecondaryTotal = 0;
        @endphp
        @foreach ($invoice->lines as $line)
            @php
                $qtyPrimaryTotal += (float) $line->qty_primary;
                $qtySecondaryTotal += (float) $line->qty_secondary;
            @endphp
            <tr>
                <td style="color: #0c63e4; text-align: center;">{{ $line->item_id }}</td>
                <td style="text-align: right;">{{ $line->item?->name }}</td>
                <td style="text-align: center;">{{ number_format((float) $line->qty_primary, 0) }}</td>
                @if ($hasDualUnit)
                    <td style="text-align: center;">{{ number_format((float) $line->qty_secondary, 0) }}</td>
                @endif
            </tr>
        @endforeach
        <tr style="font-weight: bold;">
            <td colspan="2" style="text-align: center;">إجمالي الكمية</td>
            <td style="text-align: center; background: lightgray;">{{ number_format($qtyPrimaryTotal, 0) }}</td>
            @if ($hasDualUnit)
                <td style="text-align: center; background: lightgray;">{{ number_format($qtySecondaryTotal, 0) }}</td>
            @endif
        </tr>
        </tbody>
    </table>
</div>
