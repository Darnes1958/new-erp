<?php

namespace App\Support;

use App\Models\OurCompany;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class DashboardPresentation
{
    public static function user(): ?User
    {
        return Auth::user();
    }

    public static function company(): ?OurCompany
    {
        return OurCompany::forCurrentUser();
    }

    public static function settings(): ?\App\Models\CompanySetting
    {
        return CompanySettings::current();
    }

    public static function panelId(): ?string
    {
        return Filament::getCurrentPanel()?->getId();
    }

    public static function panelLabel(): string
    {
        return match (static::panelId()) {
            'market' => 'المبيعات',
            'ins' => 'التقسيط',
            'finance' => 'المالية',
            'admin' => 'الإدارة',
            default => 'النظام',
        };
    }

    public static function panelWelcomeLine(): string
    {
        return match (static::panelId()) {
            'market' => 'في نظام المبيعات',
            'ins' => 'في نظام التقسيط',
            'finance' => 'في نظام المالية',
            'admin' => 'في لوحة الإدارة',
            default => 'في النظام',
        };
    }

    public static function userAvatarUrl(?User $user = null): ?string
    {
        $user ??= static::user();

        if (! filled($user?->avatar_path)) {
            return null;
        }

        return Storage::disk('public')->url($user->avatar_path);
    }

    public static function companyLogoUrl(?OurCompany $company = null): ?string
    {
        $company ??= static::company();

        if (! filled($company?->logo_path)) {
            return null;
        }

        return Storage::disk('public')->url($company->logo_path);
    }

    /**
     * @return array<int, array{label: string, color: string}>
     */
    public static function roleBadges(?User $user = null): array
    {
        $user ??= static::user();

        if (! $user) {
            return [];
        }

        $badges = [];

        if ($user->is_prog) {
            $badges[] = ['label' => 'مدير نظام', 'color' => 'danger'];
        }

        if ($user->hasRole('admin')) {
            $badges[] = ['label' => 'مدير', 'color' => 'warning'];
        }

        $badges[] = [
            'label' => (int) $user->status === 1 ? 'نشط' : 'غير نشط',
            'color' => (int) $user->status === 1 ? 'success' : 'gray',
        ];

        $badges[] = [
            'label' => static::panelLabel(),
            'color' => match (static::panelId()) {
                'market' => 'warning',
                'ins' => 'success',
                'finance' => 'info',
                'admin' => 'gray',
                default => 'primary',
            },
        ];

        return $badges;
    }
}
