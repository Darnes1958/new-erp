<?php

namespace App\Support;

use Illuminate\Support\Facades\File;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class PdfChrome
{
    public static function resolve(): ?string
    {
        $configured = config('laravel-pdf.browsershot.chrome_path');

        if (is_string($configured) && $configured !== '' && File::isFile($configured)) {
            return $configured;
        }

        $cacheRoot = base_path('.cache/puppeteer');

        if (! File::isDirectory($cacheRoot)) {
            return null;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($cacheRoot, RecursiveDirectoryIterator::SKIP_DOTS),
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->isFile() && strtolower($file->getFilename()) === 'chrome.exe') {
                return $file->getPathname();
            }
        }

        return null;
    }
}
