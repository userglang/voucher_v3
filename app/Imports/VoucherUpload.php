<?php

namespace App\Imports;

use App\Models\Upload;
use App\Models\Voucher;
use App\Models\VoucherDetail;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class VoucherUpload implements ToModel, WithHeadingRow
{
    public function model(array $row)
    {
        $ticketNumber = trim($row['ticket_number'] ?? '');

        // ✅ Skip and log if voucher doesn't exist
        if (empty($ticketNumber) || !Voucher::where('ticket_number', $ticketNumber)->exists()) {
            Log::warning('Skipped row: missing or unmatched ticket_number', [
                'ticket_number' => $ticketNumber,
                'row' => $row,
            ]);

            return null; // skip this row
        }

        return new Upload([
            //
            // 'voucher_number'         => $row['voucher_number'] ?? null,
            // 'ticket_number'          => $row['ticket_number'] ?? null,
            // 'payee'                  => $row['payee'] ?? null,
            // 'ck_number'              => $row['ck_number'] ?? null,
            // 'description'            => $row['description'] ?? null,
            // 'branch_name'            => $row['branch_name'] ?? null,
            // 'branch_address'         => $row['branch_address'] ?? null,
            // 'prepared_by'            => $row['prepared_by'] ?? null,
            // 'prepared_designation'   => $row['prepared_designation'] ?? null,
            // 'checked_by'             => $row['checked_by'] ?? null,
            // 'checked_designation'    => $row['checked_designation'] ?? null,
            // 'approved_by'            => $row['approved_by'] ?? null,
            // 'approved_designation'   => $row['approved_designation'] ?? null,


            // 'ticket_number'=> $row['ticket_number'] ?? null,
            // 'voucher_number'=> $row['voucher_number'] ?? null,
            // 'account_code'=> $row['account_code'] ?? null,
            // 'account_title'=> $row['account_title'] ?? null,
            // 'type'=> $row['transaction_type'] ?? null,
            // 'amount'=> $row['transaction_amount'] ?? null,
            // 'series_id'=> $row['seriesID'] ?? null,
//
//
            'user_id'                => $row['login_ID'] ?? null,
            'ticket_number'=> $row['ticket_number'] ?? null,
            'type'=> $row['upload_voucher_type'] ?? null,
            'amount'=> $row['inputFigure'] ?? null,
            'branch_number'=> $row['upload_user_branch'] ?? null,
            'voucher_number'=> $row['voucher_number'] ?? null,
            'created_at'=> $row['upload_voucher_dateTime'] ?? null,
            'updated_at'=> $row['upload_voucher_dateTime'] ?? null,



        ]);
    }
}



// update the created_at in voucher table
// <?php

// namespace App\Imports;

// use App\Models\Voucher;
// use App\Models\VoucherDetail;
// use Illuminate\Support\Facades\Log;
// use Maatwebsite\Excel\Concerns\ToModel;
// use Maatwebsite\Excel\Concerns\WithHeadingRow;
// use Carbon\Carbon;

// class VoucherUpload implements ToModel, WithHeadingRow
// {
//     public function model(array $row)
//     {
//         $ticketNumber = trim($row['ticket_number'] ?? '');
//         $createdAt = trim($row['created_at'] ?? '');

//         // ✅ Skip and log if voucher doesn't exist
//         if (empty($ticketNumber) || !Voucher::where('ticket_number', $ticketNumber)->exists()) {
//             Log::warning('Skipped row: missing or unmatched ticket_number', [
//                 'ticket_number' => $ticketNumber,
//                 'row' => $row,
//             ]);
//             return null; // skip this row
//         }

//         // ✅ Fetch the voucher
//         $voucher = Voucher::where('ticket_number', $ticketNumber)->first();

//         // ✅ If `created_at` exists and is a valid date, update the voucher
//         if (!empty($createdAt)) {
//             try {
//                 $createdAt = Carbon::parse($createdAt); // Parse the created_at date
//                 $voucher->update([
//                     'created_at' => $createdAt, // Set `created_at` to the parsed date
//                 ]);
//             } catch (\Exception $e) {
//                 Log::error('Invalid date format for created_at', [
//                     'ticket_number' => $ticketNumber,
//                     'created_at' => $createdAt,
//                     'error' => $e->getMessage(),
//                 ]);
//                 return null; // Skip if the date is invalid
//             }
//         }

//         // ✅ Optionally, return a VoucherDetail or something else if necessary
//         // You can create and return a VoucherDetail instance here if it's relevant to your import logic.

//         return null; // Return null if no further model is being imported
//     }
// }
