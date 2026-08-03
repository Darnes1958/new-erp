@foreach ($filterLines ?? [] as $line)
    <div>
        <label style="font-size: 14pt; margin-right: 12px;">{{ \App\Support\Utf8Text::clean($line) }}</label>
    </div>
@endforeach
