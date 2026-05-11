<?php

namespace App\Filament\Resources\Vouchers\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class VoucherForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Voucher Information')
                ->columnSpan('full')
                ->description('Provide the necessary voucher and reference details below.')
                ->icon('heroicon-o-document-text')
                ->schema([
                    Grid::make(3)->schema([
                        TextInput::make('voucher_number')
                            ->label('Voucher Number')
                            ->placeholder('Auto-generated if left blank')
                            ->helperText('Leave empty to generate automatically')
                            ->maxLength(255),

                        TextInput::make('ticket_number')
                            ->label('Ticket Number')
                            ->placeholder('Enter related ticket number')
                            ->helperText('Reference number from related ticket')
                            ->maxLength(255),

                        TextInput::make('ck_number')
                            ->label('Check Number')
                            ->placeholder('Enter check number')
                            ->helperText('If applicable, provide the check number')
                            ->maxLength(255),
                    ]),

                    Grid::make(2)->schema([
                        Select::make('upload.type')
                            ->label('Voucher Type')
                            ->placeholder('Choose type')
                            ->options([
                                'Journal Voucher' => 'Journal Voucher',
                                'Check/Cash Voucher' => 'Check/Cash Voucher',
                            ])
                            ->helperText('Select the appropriate type of voucher')
                            ->required()
                            ->afterStateHydrated(function ($component, $record) {
                                $component->state($record?->upload?->type);
                            })
                            ->dehydrated(false) // prevent saving to vouchers table
                            ->saveRelationshipsUsing(function ($record, $state) {
                                $record->upload?->update(['type' => $state]);
                            }),

                        TextInput::make('branch_name')
                            ->label('Branch Name')
                            ->placeholder('e.g., Main Branch')
                            ->helperText('Specify the branch issuing the voucher')
                            ->required()
                            ->maxLength(255),
                    ]),

                    TextInput::make('payee')
                        ->label('Payee Name')
                        ->placeholder('Enter name of the payee')
                        ->helperText('Full name of the person or entity receiving payment')
                        ->maxLength(255)
                        ->columnSpanFull(),

                    Textarea::make('description')
                        ->label('Voucher Description')
                        ->placeholder('Describe the purpose of this voucher...')
                        ->helperText('A brief explanation of the voucher’s purpose')
                        ->rows(3)
                        ->maxLength(1000)
                        ->columnSpanFull(),

                        Grid::make(2)->schema([
                            // Column 1
                            Grid::make()->schema([
                                TextInput::make('prepared_by')
                                    ->label('Prepared By')
                                    ->placeholder('Enter full name')
                                    ->helperText('Person who prepared this voucher')
                                    ->default(Auth::user()->name ?? '')
                                    ->maxLength(255),

                                TextInput::make('prepared_designation')
                                    ->label('Designation (Prepared By)')
                                    ->placeholder('Enter designation')
                                    ->helperText('Designation of the preparer')
                                    ->maxLength(255),
                            ]),

                            // Column 2
                            Grid::make()->schema([
                                TextInput::make('checked_by')
                                    ->label('Checked By')
                                    ->placeholder('Enter full name')
                                    ->helperText('Person who checked this voucher')
                                    ->default(Auth::user()->name ?? '')
                                    ->maxLength(255),

                                TextInput::make('checked_designation')
                                    ->label('Designation (Checked By)')
                                    ->placeholder('Enter designation')
                                    ->helperText('Designation of the checker')
                                    ->maxLength(255),
                            ]),
                        ]),

                        // Full-width Approval Section (optional: keep this below or wrap into the same row)
                        Grid::make(2)->schema([
                            TextInput::make('approved_by')
                                ->label('Approved By')
                                ->placeholder('Enter full name')
                                ->helperText('Person who approved this voucher')
                                ->default(Auth::user()->name ?? '')
                                ->maxLength(255),

                            TextInput::make('approved_designation')
                                ->label('Designation (Approved By)')
                                ->placeholder('Enter designation')
                                ->helperText('Designation of the approver')
                                ->maxLength(255),
                        ]),

                ]),
            ]);
    }
}
