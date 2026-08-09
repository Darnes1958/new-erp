@if (\App\Support\DatabaseBackupAccess::allowed())
    <x-filament::icon-button
        color="gray"
        icon="heroicon-o-circle-stack"
        href="{{ route('backup.company') }}"
        tag="a"
        tooltip="نسخ احتياطي"
        label="نسخ احتياطي"
    />
@endif
