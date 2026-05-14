<?php

namespace App\Filament\Resources\Vouchers\Pages;

use App\Filament\Resources\Vouchers\VoucherResource;
use App\Imports\VoucherImport;
use App\Models\Upload;
use App\Models\Voucher;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Validators\ValidationException;

class ListVouchers extends ListRecords
{
    protected static string $resource = VoucherResource::class;

    protected function getHeaderActions(): array
    {

        return [
            Action::make('create')
                ->label('Upload Voucher')
                ->icon('heroicon-o-cloud-arrow-up')
                ->modalHeading('Upload New Voucher')
                ->modalSubmitActionLabel('Upload Voucher')
                ->modalWidth(Width::FourExtraLarge)
                ->form([
                    Select::make('type')
                        ->label('Voucher Type')
                        ->required()
                        ->options([
                            'Journal Voucher' => 'Journal Voucher',
                            'Check/Cash Voucher' => 'Check/Cash Voucher',
                        ])
                        ->default('Journal Voucher')
                        ->helperText('Select the type of voucher to upload')
                        ->live()
                        ->afterStateUpdated(fn ($state, callable $set) => [
                            // Clear dependent fields when type changes
                            $set('check_number', null),
                            $set('payee', null),
                            $set('amount', null),
                        ]),

                    TextInput::make('ticket_number')
                        ->label('Ticket Number')
                        ->placeholder('Enter ticket number (e.g., TKT-2024-001)')
                        ->required()
                        ->maxLength(255)
                        ->helperText('Unique identifier for this voucher')
                        ->rule('regex:/^[A-Z0-9\-]+$/i')
                        ->validationMessages([
                            'regex' => 'Ticket number can only contain letters, numbers, and hyphens.',
                        ]),

                    Textarea::make('description')
                        ->label('Description')
                        ->placeholder('Brief description of the voucher purpose...')
                        ->required()
                        ->maxLength(500)
                        ->rows(3)
                        ->helperText('Provide a clear description of what this voucher is for'),

                    // Fields that are only shown for Check/Cash Vouchers
                    TextInput::make('check_number')
                        ->label('Check Number')
                        ->placeholder('Enter check number')
                        ->maxLength(255)
                        ->visible(fn (Get $get): bool => $get('type') === 'Check/Cash Voucher'),

                    TextInput::make('payee')
                        ->label('Payee')
                        ->placeholder('Enter payee name')
                        ->maxLength(255)
                        ->visible(fn (Get $get): bool => $get('type') === 'Check/Cash Voucher'),

                    TextInput::make('amount')
                        ->label('Amount')
                        ->numeric()
                        ->helperText('Payment amount')
                        ->visible(fn (Get $get): bool => $get('type') === 'Check/Cash Voucher'),

                    FileUpload::make('voucher_file')
                        ->label('Voucher File')
                        ->required()
                        ->disk('local')
                        ->directory('vouchers')
                        ->acceptedFileTypes([
                            'application/vnd.ms-excel',
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        ])
                        ->maxSize(10240)
                        ->helperText('Upload Excel file (.xls, .xlsx). Maximum size: 10MB')
                        ->uploadingMessage('Uploading voucher file...')
                        ->rules([
                            'mimes:xls,xlsx',
                            'max:10240',
                        ])
                        ->validationMessages([
                            'mimes' => 'Please upload a valid Excel file (.xls or .xlsx).',
                            'max' => 'File size cannot exceed 10MB.',
                        ]),
                ])
                ->action(function (array $data, \Filament\Actions\Action $action) {
                    try {
                        $ticketNumber = trim($data['ticket_number']);
                        $description = trim($data['description']);
                        $voucherType = $data['type'];

                        // Only get these fields for Check/Cash Vouchers
                        $checkNumber = ($voucherType === 'Check/Cash Voucher' && isset($data['check_number']))
                            ? trim($data['check_number']) : null;
                        $payee = ($voucherType === 'Check/Cash Voucher' && isset($data['payee']))
                            ? trim($data['payee']) : null;
                        $amount = ($voucherType === 'Check/Cash Voucher' && isset($data['amount']))
                            ? trim($data['amount']) : null;

                        // Check for duplicate ticket number
                        if (Upload::where('ticket_number', $ticketNumber)->exists()) {
                            Notification::make()
                                ->title('Duplicate Ticket Number')
                                ->danger()
                                ->send();
                            return;
                        }

                        // Resolve the full path to the uploaded file properly
                        $filePath = Storage::disk('local')->path($data['voucher_file']);

                        if (!file_exists($filePath)) {
                            throw new \Exception("Uploaded file not found at path: {$filePath}");
                        }

                        // Create import instance
                        $import = new VoucherImport(
                            ticketNumber: $ticketNumber,
                            description: $description,
                            amount: $amount,
                            payee: $payee,
                            checkNumber: $checkNumber,
                            voucherType: $voucherType,
                        );

                        // Import the Excel file
                        Excel::import($import, $filePath);

                        // Find the voucher after import
                        $voucher = Voucher::where('ticket_number', $import->getVoucherNumber())->first();

                        if ($voucher) {
                            // Redirect to the print route
                            // return redirect()->away(route('voucher.print', $voucher))->with('openInNewTab', true);

                            $this->js("window.open('" . route('voucher.print', $voucher) . "', '_blank')");

                            // Show success notification with print action
                            Notification::make()
                                ->title('Voucher uploaded successfully!')
                                ->body('Print view has been opened in a new tab.')
                                ->success()
                                ->actions([
                                    Action::make('print-again')
                                        ->label('Open Print View Again')
                                        ->icon('heroicon-o-printer')
                                        ->url(route('voucher.print', $voucher))
                                        ->openUrlInNewTab()
                                ])
                                ->send();

                            // Mark action as successful to close modal
                            $action->success();
                            return;

                        }

                        // If voucher not found, notify user
                        Notification::make()
                            ->title('Upload Successful, but voucher not found.')
                            ->warning()
                            ->send();

                    } catch (ValidationException $e) {
                        Notification::make()
                            ->title('Upload Failed due to validation error.')
                            ->danger()
                            ->send();

                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('Upload Failed: ' . $e->getMessage())
                            ->danger()
                            ->send();
                    }
                })
                ->modalDescription('Upload a new voucher file. Select the voucher type and fill in the required information.')
                ->slideOver()
                ->closeModalByClickingAway(false)
                ->requiresConfirmation(false),
        ];
    }


    protected function getTableQuery(): Builder
    {
        $user = Auth::user();
        $query = Voucher::query()->with(['upload.user']);

        if ($user?->hasRole('super_admin')) {
            return $query;
        }

        $branchNumber = $user?->branch?->branch_number;

        return $branchNumber
            ? $query->whereHas('upload', fn ($q) => $q->where('branch_number', $branchNumber))
            : $query->whereRaw('0 = 1');
    }
}
