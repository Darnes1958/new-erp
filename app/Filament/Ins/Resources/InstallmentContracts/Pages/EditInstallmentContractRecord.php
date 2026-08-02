<?php

namespace App\Filament\Ins\Resources\InstallmentContracts\Pages;

use App\Filament\Ins\Resources\InstallmentContracts\InstallmentContractResource;
use App\Services\Installments\InstallmentContractService;
use App\Support\CompanySettings;
use Filament\Actions\Action;
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
            Action::make('cancel')
                ->label('الغاء')
                ->icon('heroicon-m-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn (): bool => static::getResource()::canDelete($this->record))
                ->action(function (): void {
                    app(InstallmentContractService::class)->cancel($this->record);

                    $this->redirect(static::getResource()::getUrl('index'));
                }),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return app(InstallmentContractService::class)->updateStandalone($record, $data);
    }
}
