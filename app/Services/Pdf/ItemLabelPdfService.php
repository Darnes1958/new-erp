<?php

namespace App\Services\Pdf;

use App\Models\Item;
use App\Support\PdfChrome;
use Illuminate\Support\Collection;
use Spatie\Browsershot\Browsershot;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;

class ItemLabelPdfService
{
    public function single(Item $item): PdfBuilder
    {
        return $this->configureLabelPdf(
            Pdf::view('pdf.item-label', [
                'item' => $item,
                ...$this->labelDimensions(),
            ])->name("item-label-{$item->id}.pdf"),
            singlePage: true,
        );
    }

    /**
     * @param  Collection<int, Item>  $items
     */
    public function forItems(Collection $items): PdfBuilder
    {
        return $this->configureLabelPdf(
            Pdf::view('pdf.item-labels', [
                'items' => $items,
                ...$this->labelDimensions(),
            ])->name('item-labels.pdf'),
        );
    }

    /**
     * @return array{width: int, height: int}
     */
    protected function labelDimensions(): array
    {
        return [
            'width' => (int) config('printing.label_width', 30),
            'height' => (int) config('printing.label_height', 40),
        ];
    }

    protected function configureLabelPdf(PdfBuilder $pdf, bool $singlePage = false): PdfBuilder
    {
        ['width' => $width, 'height' => $height] = $this->labelDimensions();

        $pdf = $pdf
            ->paperSize($width, $height, 'mm')
            ->margins(0, 0, 0, 0)
            ->footerView('pdf.empty');

        return $pdf->withBrowsershot(function (Browsershot $shot) use ($singlePage): void {
            if (config('laravel-pdf.browsershot.no_sandbox')) {
                $shot->noSandbox();
            }

            $chromePath = PdfChrome::resolve();

            if ($chromePath !== null) {
                $shot->setChromePath($chromePath);
            }

            $nodeBinary = config('laravel-pdf.browsershot.node_binary');

            if (is_string($nodeBinary) && $nodeBinary !== '') {
                $shot->setNodeBinary($nodeBinary);
            }

            $shot->margins(0, 0, 0, 0);

            if ($singlePage) {
                $shot->pages('1');
            }
        });
    }
}
