<?php

namespace App\Imports;

use App\Models\Upload;
use App\Models\Voucher;
use App\Models\VoucherDetail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;

class VoucherImport implements ToArray, WithHeadingRow, WithBatchInserts, WithChunkReading
{
    use \Maatwebsite\Excel\Concerns\Importable;
    use \Maatwebsite\Excel\Concerns\SkipsErrors;
    use \Maatwebsite\Excel\Concerns\SkipsFailures;

    protected string $ticketNumber;
    protected ?string $description;
    protected ?string $checkNumber;
    protected ?string $voucherType;
    protected ?string $payee;
    protected ?string $amount;
    protected int $importedCount = 0;
    protected array $customErrors = [];
    protected ?string $authorizedBranchNumber;

    // Security: Define allowed voucher types
    private const ALLOWED_VOUCHER_TYPES = [
        'Journal Voucher',
        'Check/Cash Voucher'
    ];

    // Security: Define maximum limits
    private const MAX_ROWS = 1000;
    private const MAX_AMOUNT = 999999999.99;
    private const MAX_STRING_LENGTH = 255;

    public function __construct(string $ticketNumber, ?string $description = null, ?string $voucherType = null, ?string $checkNumber = null, ?string $payee = null, ?string $amount = null, ?string $authorizedBranchNumber = null)
    {

        if (!Auth::check()) {
            abort(403, 'Unauthorized.');
        }

        // Security: Validate and sanitize inputs
        $this->ticketNumber = $this->sanitizeTicketNumber($ticketNumber);
        $this->description = $this->sanitizeString($description);
        $this->voucherType = $this->validateVoucherType($voucherType);
        $this->checkNumber = $this->sanitizeString($checkNumber);
        $this->payee = $this->sanitizeString($payee);
        $this->amount = $this->sanitizeAmount($amount);
        $this->authorizedBranchNumber = $this->getBranchNumber();

        // Security: Validate user authentication and authorization
        if (!Auth::check()) {
            throw new \Exception('User not authenticated');
        }

        if (!Auth::user()->branch) {
            throw new \Exception('User branch not found');
        }

        // Security: Log the import attempt
        Log::info('VoucherImport initialized', [
            'user_id' => Auth::id(),
            'ticket_number' => $this->ticketNumber,
            'voucher_type' => $this->voucherType,
            'branch_number' => $this->authorizedBranchNumber,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    public function array(array $rows): void
    {
        DB::beginTransaction();

        try {
            // Security: Validate row count
            if (count($rows) > self::MAX_ROWS) {
                throw new \Exception("Too many rows. Maximum allowed: " . self::MAX_ROWS);
            }

            $validRows = $this->filterValidRows($rows);

            if (empty($validRows)) {
                DB::rollBack();
                $this->customErrors[] = 'No valid rows found in the uploaded file';
                return;
            }

            // Security: Additional validation for all rows
            $this->validateAllRows($validRows);

            // Step 1: Create Voucher
            $voucher = $this->createVoucher($validRows[0]);

            // Step 2: Create Voucher Details
            $this->createVoucherDetails($validRows, $voucher);

            // Step 3: Create Upload
            $this->createUpload($voucher, $validRows[0]);

            $this->importedCount = count($validRows);

            // Security: Log successful import
            Log::info('VoucherImport completed successfully', [
                'user_id' => Auth::id(),
                'ticket_number' => $this->ticketNumber,
                'imported_count' => $this->importedCount,
                'voucher_id' => $voucher->id ?? null,
            ]);

            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();

            // Security: Log failed import attempt
            Log::error('VoucherImport failed', [
                'user_id' => Auth::id(),
                'ticket_number' => $this->ticketNumber,
                'error' => $e->getMessage(),
                'ip_address' => request()->ip(),
            ]);

            throw $e;
        }
    }

    private function filterValidRows(array $rows): array
    {
        $validRows = [];

        foreach ($rows as $index => $row) {
            // Security: Skip empty rows
            if (empty(array_filter($row))) {
                continue;
            }

            // Security: Validate and sanitize row data
            $sanitizedRow = $this->sanitizeRowData($row);

            $accountCode = trim((string) ($sanitizedRow['glcode'] ?? ''));
            $seriesId = trim((string) ($sanitizedRow['journalentryseries'] ?? ''));

            if (!empty($accountCode) && !empty($seriesId)) {
                $validRows[] = $sanitizedRow;
            }
        }

        return $validRows;
    }

    private function validateAllRows(array $validRows): void
    {
        foreach ($validRows as $index => $row) {
            // Security: Validate numeric fields
            if (isset($row['tranamount']) && !is_numeric($row['tranamount'])) {
                throw new \Exception("Invalid amount format in row " . ($index + 1));
            }

            // Security: Validate amount limits
            $amount = $this->parseAmount($row['tranamount'] ?? '0');
            if ($amount > self::MAX_AMOUNT) {
                throw new \Exception("Amount exceeds maximum limit in row " . ($index + 1));
            }

            // Security: Validate branch consistency
            if (isset($row['branchno']) && $row['branchno'] != $this->authorizedBranchNumber) {
                throw new \Exception("Branch number mismatch in row " . ($index + 1));
            }
        }
    }

    private function createVoucher(array $firstRow): Voucher
    {
        // Security: Validate ticket number from Excel
        $excelTicketBase = $this->sanitizeString($firstRow['ticketno'] ?? '');
        $excelTicketNumber = $excelTicketBase . '-' . $this->authorizedBranchNumber;

        if ($this->ticketNumber !== $excelTicketNumber) {
            throw new \Exception(
                "Ticket number mismatch detected. Expected: {$excelTicketNumber}, Found: {$this->ticketNumber}"
            );
        }

        // Security: Check for existing voucher with database lock
        $existingVoucher = Voucher::where('ticket_number', $this->ticketNumber)->lockForUpdate()->first();

        if ($existingVoucher) {
            throw new \Exception("Voucher with ticket number \"{$this->ticketNumber}\" already exists");
        }

        // Security: Sanitize all input data
        $voucherData = [
            'ticket_number' => $this->ticketNumber,
            'voucher_number' => $this->generateVoucherNumber(), // Generate formatted voucher number here
            'payee' => $this->sanitizeString($this->payee),
            'ck_number' => $this->sanitizeString($this->checkNumber),
            'description' => $this->sanitizeString($this->description),
            'branch_name' => $this->sanitizeString($firstRow['branchname'] ?? null),
            'branch_address' => $this->sanitizeString($firstRow['branchaddress'] ?? null),
            'prepared_by' => $this->sanitizeString($firstRow['preparedby'] ?? null),
            'prepared_designation' => $this->sanitizeString($firstRow['prepareddesignation'] ?? null),
            'checked_by' => $this->sanitizeString($firstRow['checkedby'] ?? null),
            'approved_by' => $this->sanitizeString($firstRow['approvedby'] ?? null),
            'checked_designation' => $this->sanitizeString($firstRow['checkeddesignation'] ?? null),
            'approved_designation' => $this->sanitizeString($firstRow['approveddesignation'] ?? null),
        ];

        return Voucher::create($voucherData);
    }

    private function createVoucherDetails(array $validRows, Voucher $voucher): void
    {
        foreach ($validRows as $row) {
            $transactionAmount = $row['tranamount'] ?? $this->amount;
            $amount = $this->parseAmount((string) $transactionAmount);

            // Security: Validate amount
            if ($amount > self::MAX_AMOUNT) {
                throw new \Exception("Amount exceeds maximum limit");
            }

            VoucherDetail::create([
                'ticket_number' => $this->ticketNumber,
                'voucher_number' => $voucher->voucher_number,
                'account_code' => $this->sanitizeString($row['glcode']),
                'account_title' => $this->sanitizeString($row['gllongdesc'] ?? ''),
                'amount' => $amount,
                'series_id' => $this->sanitizeString($row['journalentryseries']),
                'type' => $this->sanitizeString($row['trantype'] ?? null),
            ]);
        }
    }

    private function createUpload(Voucher $voucher, array $firstRow): void
    {
        $excelBranchNo = (string) ($firstRow['branchno'] ?? '');

        // Security: Strict branch validation
        if ($excelBranchNo !== $this->authorizedBranchNumber) {
            throw new \Exception("Unauthorized branch access detected");
        }

        Upload::create([
            'ticket_number' => $this->ticketNumber,
            'type' => $this->voucherType,
            'amount' => $this->parseAmount($this->amount),
            'voucher_number' => $voucher->voucher_number,
            'branch_number' => $this->authorizedBranchNumber,
        ]);
    }

    private function generateVoucherNumber(): string
    {
        $branchCode = $this->sanitizeString(Auth::user()->branch->code);
        $branchNumber = $this->authorizedBranchNumber;

        $voucherCount = Upload::where('branch_number', $branchNumber)
            ->where('type', $this->voucherType)
            ->count();

        $voucherSequence = str_pad($voucherCount + 1, 4, '0', STR_PAD_LEFT);

        return $branchCode . '-' . $voucherSequence;
    }

    // Security: Enhanced input sanitization methods
    private function sanitizeString(?string $input): ?string
    {
        if ($input === null || $input === '') {
            return null;
        }

        // Strip HTML tags and trim whitespace, but do NOT encode entities
        $sanitized = trim(strip_tags($input));

        // Limit string length
        if (strlen($sanitized) > self::MAX_STRING_LENGTH) {
            $sanitized = substr($sanitized, 0, self::MAX_STRING_LENGTH);
        }

        return $sanitized;
    }

    private function sanitizeTicketNumber(string $ticketNumber): string
    {
        $sanitized = trim($ticketNumber);

        // Security: Validate ticket number format
        if (!preg_match('/^[A-Z0-9\-]+$/i', $sanitized)) {
            throw new \Exception('Invalid ticket number format');
        }

        return $sanitized . '-' . $this->getBranchNumber();
    }

    private function validateVoucherType(?string $voucherType): ?string
    {
        if ($voucherType === null) {
            return null;
        }

        $sanitized = trim($voucherType);

        // Security: Validate against allowed types
        if (!in_array($sanitized, self::ALLOWED_VOUCHER_TYPES)) {
            throw new \Exception('Invalid voucher type');
        }

        return $sanitized;
    }

    private function sanitizeAmount(?string $amount): ?string
    {
        if ($amount === null || $amount === '') {
            return null;
        }

        // Security: Validate numeric format
        $cleaned = preg_replace('/[^\d.-]/', '', $amount);

        if (!is_numeric($cleaned)) {
            throw new \Exception('Invalid amount format');
        }

        $numericAmount = floatval($cleaned);

        if ($numericAmount > self::MAX_AMOUNT) {
            throw new \Exception('Amount exceeds maximum limit');
        }

        return $cleaned;
    }

    private function sanitizeRowData(array $row): array
    {
        $sanitized = [];

        foreach ($row as $key => $value) {
            $sanitizedKey = $this->sanitizeString($key);
            $sanitizedValue = is_string($value) ? $this->sanitizeString($value) : $value;
            $sanitized[$sanitizedKey] = $sanitizedValue;
        }

        return $sanitized;
    }

    private function getBranchNumber(): string
    {
        if (!Auth::check() || !Auth::user()->branch) {
            throw new \Exception('User branch not found');
        }

        return (string) Auth::user()->branch->branch_number;
    }

    private function parseAmount(?string $amount): float
    {
        if (!$amount || $amount === '') {
            return 0.0;
        }

        // Security: Enhanced amount parsing with validation
        $cleaned = preg_replace('/[^\d.-]/', '', $amount);

        if (!is_numeric($cleaned)) {
            throw new \Exception('Invalid amount format');
        }

        $numericAmount = floatval($cleaned);

        // Security: Validate amount range
        if ($numericAmount < 0) {
            throw new \Exception('Negative amounts are not allowed');
        }

        if ($numericAmount > self::MAX_AMOUNT) {
            throw new \Exception('Amount exceeds maximum limit');
        }

        return $numericAmount;
    }

    // Removed rules() and customValidationMessages() methods
    // Manual validation is now handled in validateAllRows() and other methods

    public function batchSize(): int
    {
        return 500; // Reduced batch size for better security monitoring
    }

    public function chunkSize(): int
    {
        return 500; // Reduced chunk size for better security monitoring
    }

    public function getImportedCount(): int
    {
        return $this->importedCount;
    }

    public function getErrors(): array
    {
        return array_merge($this->customErrors, $this->failures()->toArray());
    }

    public function hasErrors(): bool
    {
        return !empty($this->customErrors) || !empty($this->failures());
    }

    public function getVoucherNumber(): string
    {
        return $this->ticketNumber;
    }
}
