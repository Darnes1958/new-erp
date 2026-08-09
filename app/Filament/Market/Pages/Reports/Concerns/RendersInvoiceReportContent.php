<?php

namespace App\Filament\Market\Pages\Reports\Concerns;

use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;

trait RendersInvoiceReportContent
{
    public function content(Schema $schema): Schema
    {
        $components = [
            EmbeddedSchema::make('filtersForm'),
        ];

        if (method_exists($this, 'getCachedTabs') && count($this->getCachedTabs()) > 0) {
            $components[] = $this->getTabsContentComponent();
        }

        $components[] = EmbeddedTable::make();

        return $schema->components($components);
    }
}
