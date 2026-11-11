<?php

namespace App\Models;

use App\Support\Utf8Text;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class OurCompany extends Model
{
    protected $table = 'our_companies';

    public $timestamps = false;

    protected $fillable = [
        'connection_name',
        'display_name',
        'display_name_suffix',
        'comp_code',
        'address',
        'phone',
        'logo_path',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public static function forCurrentUser(): ?self
    {
        $connectionName = Auth::user()?->company;

        if (! is_string($connectionName) || $connectionName === '') {
            return null;
        }

        return static::query()
            ->where('connection_name', $connectionName)
            ->where('is_active', true)
            ->first();
    }

    public function logoAbsolutePath(): ?string
    {
        if (! filled($this->logo_path)) {
            return null;
        }

        $relative = str_replace('\\', '/', ltrim((string) $this->logo_path, '/'));
        $path = storage_path('app/public/'.$relative);

        if (is_file($path)) {
            return $path;
        }

        $withoutExtension = (string) preg_replace('/\.(jpe?g|png|gif|webp)$/i', '', $path);

        if ($withoutExtension !== $path && is_file($withoutExtension)) {
            return $withoutExtension;
        }

        foreach (['.jpg', '.jpeg', '.png', '.gif', '.webp'] as $extension) {
            if (is_file($withoutExtension.$extension)) {
                return $withoutExtension.$extension;
            }
        }

        return null;
    }

    public function logoPdfSrc(): ?string
    {
        $path = $this->logoAbsolutePath();

        if ($path === null) {
            return null;
        }

        $contents = @file_get_contents($path);

        if ($contents === false || $contents === '') {
            return null;
        }

        $mime = @mime_content_type($path) ?: 'image/jpeg';

        return 'data:'.$mime.';base64,'.base64_encode($contents);
    }

    /**
     * @return array<int, array<int, string>>
     */
    public function excelCompanyRows(): array
    {
        $rows = [[Utf8Text::clean($this->display_name) ?? '']];

        if (filled($this->display_name_suffix)) {
            $rows[] = [Utf8Text::clean($this->display_name_suffix)];
        }

        return $rows;
    }

    /**
     * @return array<int, array<int, string>>
     */
    public function excelHeaderRows(string $subtitleLine): array
    {
        return [
            ...$this->excelCompanyRows(),
            [$subtitleLine],
        ];
    }
}
