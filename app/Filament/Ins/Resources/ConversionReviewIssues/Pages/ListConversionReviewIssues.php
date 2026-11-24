<?php

namespace App\Filament\Ins\Resources\ConversionReviewIssues\Pages;

use App\Filament\Ins\Resources\ConversionReviewIssues\ConversionReviewIssueResource;
use Filament\Resources\Pages\ListRecords;

class ListConversionReviewIssues extends ListRecords
{
    protected static string $resource = ConversionReviewIssueResource::class;

    protected static ?string $title = 'بيانات الكونفيرت التي تحتاج مراجعة — بن طاهر';
}
