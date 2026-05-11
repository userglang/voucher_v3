<?php

namespace App\Service;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;
use TCPDF;
use App\Models\Voucher;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use NumberToWords\NumberToWords as NumberToWordsLib;

class VoucherPrintPDFService
{
    private const MAX_DETAILS_PER_PAGE = 26;
    private const PDF_CREATOR_NAME = 'Voucher System';

    protected string $ticketNumber;
    protected TCPDF $pdf;

    public function __construct(string $ticketNumber)
    {
        if (!Auth::check()) {
            abort(403, 'Unauthorized.');
        }

        $this->ticketNumber = preg_replace('/[^A-Za-z0-9_\-]/', '', trim($ticketNumber));

        if (empty($this->ticketNumber)) {
            abort(400, 'Invalid ticket number format.');
        }

        $voucher = Voucher::with('upload.branch')->where('ticket_number', $this->ticketNumber)->first();

        if (!$voucher) {
            abort(404, 'Voucher not found.');
        }

        $userBranchNumber = Auth::user()->branch?->branch_number;

        if ($userBranchNumber && $voucher->upload?->branch_number !== $userBranchNumber) {
            abort(403, 'Access denied. You can only access vouchers from your branch.');
        }
    }

    public function generatePDFContent(): void
    {
        try {
            $voucher = $this->getVoucherData();

            if (!$voucher) {
                throw new \Exception('Voucher not found.');
            }

            $voucherDetails = $this->getVoucherDetails();
            $isJournalVoucher = empty($voucher['payee']);
            $totals = $this->calculateTotals($voucherDetails);

            $this->pdf = new TCPDF('P', 'mm', 'letter', true, 'UTF-8', false);
            $this->pdf->setPrintHeader(false);
            $this->pdf->SetCreator(self::PDF_CREATOR_NAME);
            $this->pdf->SetAuthor(config('app.name', 'Oro Integrated Cooperative'));
            $this->pdf->SetTitle('Voucher Report');
            $this->pdf->SetSubject('Voucher Report');
            $this->pdf->SetMargins(14, 10, 10);
            $this->pdf->SetAutoPageBreak(true, 20);
            $this->pdf->AddPage();

            $this->generateHeader($voucher);

            if (!$isJournalVoucher) {
                $this->generateAmountSection($voucher, $totals['crTotal']);
            }

            $this->generateDetailsTable($voucherDetails, $voucher, $isJournalVoucher);
            $this->generateFooter($voucher);

            $safeFilename = preg_replace('/[^A-Za-z0-9_\-]/', '', $voucher['voucher_number']);
            $mode = request()->get('download') ? 'D' : 'I';
            $this->pdf->Output("Voucher_{$safeFilename}.pdf", $mode);

        } catch (\Throwable $e) {
            Log::error('PDF generation failed: ' . $e->getMessage());
            abort(500, 'Oops! Something went wrong while generating the voucher. Please try again later.');
        }
    }

    private function calculateTotals(array $details): array
    {
        $drTotal = 0;
        $crTotal = 0;

        foreach ($details as $row) {
            if ($row->type === 'Dr') {
                $drTotal += $row->amount;
            } elseif ($row->type === 'Cr') {
                $crTotal += $row->amount;
            }
        }

        return compact('drTotal', 'crTotal');
    }

    private function getVoucherData(): ?array
    {
        try {
            $result = DB::table('vouchers')
                ->join('uploads', 'vouchers.ticket_number', '=', 'uploads.ticket_number')
                ->join('branches', 'uploads.branch_number', '=', 'branches.branch_number')
                ->where('vouchers.ticket_number', $this->ticketNumber)
                ->select(
                    'vouchers.voucher_number',
                    'vouchers.ticket_number',
                    'vouchers.payee',
                    'vouchers.description',
                    'vouchers.ck_number',
                    'vouchers.prepared_by',
                    'vouchers.prepared_designation',
                    'vouchers.checked_by',
                    'vouchers.checked_designation',
                    'vouchers.approved_by',
                    'vouchers.approved_designation',
                    'vouchers.created_at',
                    'branches.branch_name',
                    'uploads.type as voucher_type',
                    'uploads.voucher_number as voucher_num',
                    'uploads.amount as input_figure'
                )
                ->first();

            return $result ? (array) $result : null;

        } catch (QueryException $e) {
            Log::error('Database error fetching voucher: ' . $e->getMessage());
            return null;
        }
    }

    private function getVoucherDetails(): array
    {
        return DB::table('voucher_details')
            ->where('ticket_number', $this->ticketNumber)
            ->orderBy('series_id')
            ->get()
            ->toArray();
    }

    private function safeField(?string $value, string $default = ''): string
    {
        return !empty($value) ? $value : $default;
    }

    private function generateHeader(array $voucher): void
    {
        $createdAt = Carbon::parse($voucher['created_at'])->format('F j, Y / g:i A');
        $voucherType = empty($voucher['payee']) ? 'Journal Voucher' : 'Check/Cash Voucher';

        $cleanBranchName = preg_replace('/^OIC\s*[-]?\s*/i', '', $voucher['branch_name'] ?? '');
        $formattedBranchName = ucwords(strtolower($cleanBranchName));
        $voucherNumber = $voucher['voucher_number'] ?: $voucher['voucher_num'];

        $this->pdf->Image(public_path('images/OIC_Logo.jpg'), 83, 11, 40, '', 'JPG', '', 'T', false, 100, '', false, false, 0, false, false, false);
        $this->pdf->Ln(18);

        $this->pdf->SetFont('freesans', '', 9);
        $this->pdf->Cell(179, 3, $formattedBranchName . ' Branch', 0, 2, 'C');

        $this->pdf->SetFont('freesans', 'B', 16);
        $this->pdf->Cell(179, 5, $voucherType, 0, 2, 'C');
        $this->pdf->Ln(2);

        $this->pdf->SetFont('freesans', 'B', 9);
        $this->pdf->Cell(175, 5, 'Voucher#: ' . $this->safeField($voucherNumber), 0, 1, 'R');
        $this->pdf->Cell(100, 5, $createdAt, 0, 0, 'L');
        $this->pdf->Cell(75, 5, 'Ticket #: ' . $this->safeField($voucher['ticket_number']), 0, 0, 'R');

        $this->pdf->Ln(5);
        $this->pdf->SetFont('freesans', 'B', 10);
        $this->pdf->Cell(25, 5, 'Payee:', 0, 0, 'L');
        $this->pdf->SetFont('freesans', '', 9);
        $this->pdf->MultiCell(75, 5, $this->safeField($voucher['payee']), 0, 'L', 0, 0);

        if ($voucherType === 'Check/Cash Voucher') {
            $this->pdf->SetFont('freesans', 'B', 9);
            $this->pdf->MultiCell(75, 5, 'CK #: ' . $this->safeField($voucher['ck_number']), 0, 'R', 0, 1);
        }

        $this->pdf->Ln(3);
        $this->pdf->SetFont('freesans', 'B', 10);
        $this->pdf->Cell(30, 5, 'Description:', 0, 0, 'L');
        $this->pdf->SetFont('freesans', '', 9);
        $this->pdf->MultiCell(145, 5, $this->safeField($voucher['description']), 0, 'L', 0, 1);

        $this->pdf->Ln(5);
    }

    private function generateAmountSection(array $voucher, float $crTotal = 0): void
    {
        $figure = max(0, (float) ($voucher['input_figure'] ?? 0));

        if ($figure <= 0) {
            $figure = $crTotal;
        }

        if ($figure <= 0) {
            return;
        }

        $whole = (int) floor($figure);
        $decimal = (int) round(($figure - $whole) * 100);

        $transformer = (new NumberToWordsLib())->getNumberTransformer('en');
        $pesoWords = strtoupper($transformer->toWords($whole));
        $centavoWords = $decimal > 0
            ? ' AND ' . strtoupper($transformer->toWords($decimal)) . ' CENTAVOS'
            : '';

        $this->pdf->SetFont('freesans', '', 9);
        $this->pdf->Cell(179, 5, 'Amount in Figure: ' . number_format($figure, 2), 0, 1, 'L');
        $this->pdf->Cell(179, 5, "Amount in Words: ***{$pesoWords} PESOS{$centavoWords} ONLY***", 0, 1, 'L');
        $this->pdf->Ln(2);
    }

    private function generateDetailsTable(array $details, array $voucher, bool $isJournalVoucher): void
    {
        $this->printTableHeader();

        $drTotal = 0;
        $crTotal = 0;

        foreach ($details as $i => $row) {
            if ($i > 0 && ($i % self::MAX_DETAILS_PER_PAGE) === 0) {
                $this->pdf->AddPage();

                if (!$isJournalVoucher) {
                    $this->generateAmountSection($voucher);
                }

                $this->generateHeader($voucher);
                $this->printTableHeader();
            }

            $debit  = $row->type === 'Dr' ? number_format($row->amount, 2) : '';
            $credit = $row->type === 'Cr' ? number_format($row->amount, 2) : '';

            $this->pdf->Ln(1);
            $this->pdf->Cell(95, 1, htmlspecialchars_decode($row->account_title, ENT_QUOTES), 0, 0, 'L');
            $this->pdf->Cell(40, 1, $debit, 0, 0, 'C');
            $this->pdf->Cell(40, 1, $credit, 0, 1, 'C');

            $drTotal += $row->type === 'Dr' ? $row->amount : 0;
            $crTotal += $row->type === 'Cr' ? $row->amount : 0;
        }

        $this->pdf->Ln(2);
        $this->pdf->SetFont('freesans', 'B', 9);
        $this->pdf->SetFillColor(224, 235, 255);
        $this->pdf->Cell(95, 1, 'GRAND TOTAL:', 0, 0, 'R', 0);
        $this->pdf->Cell(40, 1, number_format($drTotal, 2), 1, 0, 'C', 1);
        $this->pdf->Cell(40, 1, number_format($crTotal, 2), 1, 1, 'C', 1);
    }

    private function printTableHeader(): void
    {
        $this->pdf->Ln(12);
        $this->pdf->SetFont('freesans', 'B', 10);
        $this->pdf->SetFillColor(224, 235, 255);
        $this->pdf->Cell(95, 1, 'Account Title', 1, 0, 'L', 1);
        $this->pdf->Cell(40, 1, 'Debit Amount', 1, 0, 'C', 1);
        $this->pdf->Cell(40, 1, 'Credit Amount', 1, 1, 'C', 1);
        $this->pdf->Ln(1);
        $this->pdf->SetFont('freesans', '', 9);
    }

    private function generateFooter(array $voucher): void
    {
        $this->pdf->SetY(-82);
        $this->pdf->Ln(5);
        $this->pdf->Cell(175, 3, str_repeat('-', 150), 0, 1, 'C');
        $this->pdf->Ln(5);

        $this->pdf->SetFont('times', 'B', 9);
        $this->pdf->Cell(180, 3, 'Received By: ' . ($voucher['payee'] ?: '______________________________'), 0, 1, 'C');
        $this->pdf->Ln(5);

        $this->pdf->SetFont('times', '', 9);
        $this->pdf->Cell(25, 1, 'Prepared By:', 0, 0);
        $this->pdf->Cell(85, 1, '______________________________', 0, 0, 'L');
        $this->pdf->Cell(5, 1, 'Checked By:', 0, 0, 'R');
        $this->pdf->Cell(70, 1, '______________________________', 0, 1, 'C');

        $this->pdf->SetFont('times', 'B', 10);
        $this->pdf->Cell(10, 1, '', 0, 0);
        $this->pdf->Cell(78, 1, $this->safeField($voucher['prepared_by']), 0, 0, 'C');
        $this->pdf->Cell(20, 1, '', 0, 0);
        $this->pdf->Cell(85, 1, $this->safeField($voucher['checked_by']), 0, 1, 'C');

        $this->pdf->SetFont('times', '', 9);
        $this->pdf->Cell(100, 1, $this->safeField($voucher['prepared_designation']), 0, 0, 'C');
        $this->pdf->Cell(100, 1, $this->safeField($voucher['checked_designation']), 0, 1, 'C');

        $this->pdf->Ln(2);
        $this->pdf->Cell(25, 1, 'Disbursed By:', 0, 0);
        $this->pdf->Cell(85, 1, '______________________________', 0, 0, 'L');
        $this->pdf->Cell(5, 1, 'Approved By:', 0, 0, 'R');
        $this->pdf->Cell(70, 1, '______________________________', 0, 1, 'C');

        $this->pdf->SetFont('times', 'B', 10);
        $this->pdf->Cell(10, 1, '', 0, 0);
        $this->pdf->Cell(78, 1, 'CASHIER/TELLER', 0, 0, 'C');
        $this->pdf->Cell(20, 1, '', 0, 0);
        $this->pdf->Cell(85, 1, $this->safeField($voucher['approved_by']), 0, 1, 'C');

        $this->pdf->SetFont('times', '', 9);
        $this->pdf->Cell(100, 1, '', 0, 0);
        $this->pdf->Cell(100, 1, $this->safeField($voucher['approved_designation']), 0, 1, 'C');

        $this->pdf->Ln(3);
        $this->pdf->Cell(175, 3, str_repeat('-', 150), 0, 1, 'C');
        $this->pdf->SetFont('times', 'I', 8);
        $this->pdf->Cell(179, 5, 'Page ' . $this->pdf->getAliasNumPage() . ' of ' . $this->pdf->getAliasNbPages(), 0, false, 'R');
    }
}
