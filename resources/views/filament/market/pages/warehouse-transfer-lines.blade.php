<div class="space-y-4">
    <div class="grid gap-2 text-sm">
        <div><strong>الرقم:</strong> {{ $transfer->id }}</div>
        <div><strong>التاريخ:</strong> {{ $transfer->transfer_date?->format('Y-m-d') }}</div>
        <div><strong>من:</strong> {{ $transfer->warehouseFrom?->name }}</div>
        <div><strong>إلى:</strong> {{ $transfer->warehouseTo?->name }}</div>
    </div>

    <table class="w-full text-sm border-collapse">
        <thead>
            <tr class="bg-gray-100">
                <th class="border p-2 text-right">رقم الصنف</th>
                <th class="border p-2 text-right">اسم الصنف</th>
                <th class="border p-2 text-right">الكمية</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($transfer->lines as $line)
                <tr>
                    <td class="border p-2">{{ $line->item_id }}</td>
                    <td class="border p-2">{{ $line->item?->name }}</td>
                    <td class="border p-2">{{ $line->qty_primary }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
