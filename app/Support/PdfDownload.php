<?php

namespace App\Support;

use Spatie\LaravelPdf\PdfBuilder;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class PdfDownload
{
    public static function streamed(PdfBuilder $pdf): StreamedResponse
    {
        $filename = $pdf->downloadName !== '' ? $pdf->downloadName : 'report.pdf';

        return response()->streamDownload(
            fn () => print($pdf->generatePdfContent()),
            $filename,
            ['Content-Type' => 'application/pdf'],
        );
    }
}
