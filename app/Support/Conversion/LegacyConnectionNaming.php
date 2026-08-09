<?php

namespace App\Support\Conversion;

use InvalidArgumentException;

class LegacyConnectionNaming
{
    /**
     * Legacy connection name is kept exactly as-is (BenTaher, Motafoek, …).
     */
    public static function legacyName(string $name): string
    {
        return trim($name);
    }

    /**
     * Target ERP connection name derived from the legacy name using config rules.
     */
    public static function targetName(string $legacyName): string
    {
        $legacyName = self::legacyName($legacyName);
        $mode = (string) config('erp.conversion.target_name_mode', 'suffix');
        $suffix = (string) config('erp.conversion.target_name_suffix', '_erp');
        $prefix = (string) config('erp.conversion.target_name_prefix', '');

        return match ($mode) {
            'none' => $legacyName,
            'prefix' => $prefix !== '' ? $prefix.$legacyName : $legacyName,
            'suffix' => $suffix !== '' ? $legacyName.$suffix : $legacyName,
            default => throw new InvalidArgumentException("Unsupported conversion target_name_mode [{$mode}]."),
        };
    }

    /**
     * @return array{legacy: string, target: string, mode: string, suffix: string, prefix: string}
     */
    public static function describe(string $legacyName): array
    {
        return [
            'legacy' => self::legacyName($legacyName),
            'target' => self::targetName($legacyName),
            'mode' => (string) config('erp.conversion.target_name_mode', 'suffix'),
            'suffix' => (string) config('erp.conversion.target_name_suffix', '_erp'),
            'prefix' => (string) config('erp.conversion.target_name_prefix', ''),
        ];
    }
}
