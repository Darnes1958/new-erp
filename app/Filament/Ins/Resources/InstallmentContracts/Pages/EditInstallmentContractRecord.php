<?php

namespace App\Filament\Ins\Resources\InstallmentContracts\Pages;

use App\Filament\Ins\Resources\InstallmentContracts\InstallmentContractResource;
use App\Filament\Ins\Support\InstallmentContractCancelAfterActions;
use App\Filament\Ins\Support\InstallmentContractDeleteActions;
use App\Services\Installments\InstallmentContractService;
use App\Support\CompanySettings;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

class EditInstallmentContractRecord extends EditRecord
{
    protected static string $resource = InstallmentContractResource::class;

    protected ?string $heading = '';

    public static function canAccess(array $parameters = []): bool
    {
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        if ($user->is_prog) {
            return true;
        }

        return $user->can('تعديل عقود') && ! CompanySettings::linkSalesToInstallments();
    }

    public function getTitle(): string|Htmlable
    {
        return new HtmlString('<div class="leading-3 h-4 py-0 text-base text-primary-400 py-0">تعديل عقود</div>');
    }

    public function getBreadcrumbs(): array
    {
        return [''];
    }

    protected function getHeaderActions(): array
    {
        return [
            ...InstallmentContractCancelAfterActions::make(
                visible: fn (): bool => static::getResource()::canDelete($this->record),
                after: fn ($record, mixed $livewire) => $livewire->redirect(
                    static::getResource()::getUrl('index')
                ),
            ),
            InstallmentContractDeleteActions::make(
                visible: fn (): bool => static::getResource()::canDelete($this->record),
                afterDelete: fn ($record, mixed $livewire) => $livewire->redirect(
                    static::getResource()::getUrl('index')
                ),
            ),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return app(InstallmentContractService::class)->updateStandalone($record, $data);
    }
}
