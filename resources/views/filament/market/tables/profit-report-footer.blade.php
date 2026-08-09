@php
    $totalColumns = ['rebh', 'masr', 'sal', 'rent', 'ksm', 'safi'];
    $recordCollection = collect($records);
@endphp

@foreach ($columns as $column)
    <td class="fi-ta-cell">
        <div class="fi-ta-col-wrp">
            @if ($column->getName() === 'month_name')
                <span class="font-semibold">الإجمالي</span>
            @elseif (in_array($column->getName(), $totalColumns, true))
                <span @class([
                    'font-semibold',
                    'text-danger-600' => $column->getName() === 'safi' && $recordCollection->sum($column->getName()) < 0,
                    'text-indigo-700' => $column->getName() !== 'safi' || $recordCollection->sum($column->getName()) >= 0,
                ])>
                    {{ number_format($recordCollection->sum($column->getName()), 0, '', ',') }}
                </span>
            @endif
        </div>
    </td>
@endforeach
