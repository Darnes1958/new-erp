<?php

namespace App\Imports\Installments;

use App\Models\DeductionImportStagingLine;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithStartRow;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class DeductionBatchExcelImport implements ToCollection, WithStartRow
{
    /**
     * @param  array<string, string>  $columnMap  internal key => excel header label
     */
    public function __construct(
        protected string $importSessionId,
        protected int $payrollBankId,
        protected ?int $installmentBankId,
        protected int $headingRow,
        protected array $columnMap,
    ) {}

    public function startRow(): int
    {
        return $this->headingRow;
    }

    public function collection(Collection $rows): void
    {
        if ($rows->isEmpty()) {
            return;
        }

        $headerRow = $rows->first();
        $columnIndexes = $this->resolveColumnIndexes($headerRow);

        foreach ($rows->slice(1) as $index => $row) {
            $excelRowNumber = $this->headingRow + (int) $index + 1;

            $accountNumber = $this->cellValue($row, $columnIndexes['account_number'] ?? null);
            $customerName = $this->cellValue($row, $columnIndexes['customer_name'] ?? null);
            $amount = $this->cellValue($row, $columnIndexes['amount'] ?? null);
            $deductionDate = $this->cellValue($row, $columnIndexes['deduction_date'] ?? null);

            if ($this->isEmpty($accountNumber) || $this->isEmpty($amount) || $this->isEmpty($deductionDate)) {
                continue;
            }

            if (! is_numeric($amount) || ! is_numeric($accountNumber)) {
                continue;
            }

            $parsedDate = $this->parseDate($deductionDate);

            if ($parsedDate === null) {
                continue;
            }

            DeductionImportStagingLine::query()->create([
                'import_session_id' => $this->importSessionId,
                'payroll_bank_id' => $this->payrollBankId,
                'installment_bank_id' => $this->installmentBankId,
                'account_number' => trim((string) $accountNumber),
                'customer_name' => $customerName !== null ? trim((string) $customerName) : null,
                'amount' => round((float) $amount, 3),
                'deduction_date' => $parsedDate,
                'row_number' => $excelRowNumber,
                'created_by' => Auth::id(),
            ]);
        }
    }

    /**
     * @return array<string, int>
     */
    protected function resolveColumnIndexes(Collection $headerRow): array
    {
        $indexes = [];

        foreach ($this->columnMap as $internalKey => $headerLabel) {
            $normalizedLabel = $this->normalizeHeader((string) $headerLabel);

            foreach ($headerRow as $columnIndex => $headerCell) {
                if ($this->normalizeHeader((string) $headerCell) === $normalizedLabel) {
                    $indexes[$internalKey] = (int) $columnIndex;

                    break;
                }
            }
        }

        return $indexes;
    }

    protected function cellValue(Collection $row, ?int $index): mixed
    {
        if ($index === null) {
            return null;
        }

        return $row->get($index);
    }

    protected function isEmpty(mixed $value): bool
    {
        return $value === null || trim((string) $value) === '';
    }

    protected function normalizeHeader(string $header): string
    {
        return mb_strtolower(trim($header));
    }

    protected function parseDate(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_numeric($value)) {
            return Date::excelToDateTimeObject((float) $value)->format('Y-m-d');
        }

        $stringValue = trim((string) $value);

        if ($stringValue === '') {
            return null;
        }

        foreach (['d/m/Y', 'd-m-Y', 'Y-m-d', 'm/d/Y'] as $format) {
            try {
                return Carbon::createFromFormat($format, $stringValue)->format('Y-m-d');
            } catch (InvalidArgumentException) {
                continue;
            }
        }

        try {
            return Carbon::parse($stringValue)->format('Y-m-d');
        } catch (InvalidArgumentException) {
            return null;
        }
    }
}
