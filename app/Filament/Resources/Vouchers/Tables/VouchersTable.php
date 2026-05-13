<?php

namespace App\Filament\Resources\Vouchers\Tables;

use App\Models\Voucher;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;

class VouchersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('upload.voucher_number')
                    ->label('Voucher Number')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->description(fn ($record) => $record->branch_name ?? null)
                    ->copyMessage('Voucher number copied!')
                    ->color(fn ($record) => match($record->upload->type) {
                        'Journal Voucher' => 'success',
                        'Check/Cash Voucher' => 'info',
                        default => 'primary',
                    })
                    ->weight(FontWeight::Medium),

                TextColumn::make('ticket_number')
                    ->label('Ticket Number')
                    ->searchable()
                    ->sortable()
                    ->description(fn ($record) => $record->upload->type ?? null)
                    ->color(fn ($record) => match($record->upload->type) {
                        'Journal Voucher' => 'success',
                        'Check/Cash Voucher' => 'info',
                        default => null,
                    })
                    ->copyable()
                    ->copyMessage('Ticket number copied!')
                    ->copyMessageDuration(1500),

                TextColumn::make('payee')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Payee copied!')
                    ->limit(25)
                    ->placeholder('Not Applicable')
                    ->toggleable(true)
                    ->tooltip(function (TextColumn $column): ?string {
                        $state = $column->getState();
                        return strlen($state) > 25 ? $state : null;
                    }),

                TextColumn::make('description')
                    ->label('Description')
                    ->searchable()
                    ->limit(30)
                    ->copyable()
                    ->copyMessage('Description copied!')
                    ->placeholder('No description')
                    ->tooltip(function (TextColumn $column): ?string {
                        $state = $column->getState();
                        return strlen($state) > 30 ? $state : null;
                    }),

                TextColumn::make('ck_number')
                    ->label('Check #')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->copyable()
                    ->placeholder('Not Applicable')
                    ->formatStateUsing(fn ($state) =>
                        blank($state) || in_array(strtolower(trim($state)), ['n/a', 'na'])
                            ? 'Not Applicable'
                            : $state
                    ),

                TextColumn::make('prepared_by')
                    ->label('Prepared By')
                    ->placeholder('Unknown')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->color('gray')
                    ->icon('heroicon-o-user'),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('M j, Y')
                    ->sortable()
                    ->since()
                    ->tooltip(fn ($state) => $state?->format('F j, Y \a\t g:i A'))
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->color('gray')
                    ->size('sm'),
            ])
            ->filters([
                //
                SelectFilter::make('upload_type')
                    ->label('Voucher Type')
                    ->options([
                        'Journal Voucher' => 'Journal Voucher',
                        'Check/Cash Voucher' => 'Check/Cash Voucher',
                    ])
                    ->placeholder('All Types')
                    ->multiple()
                    ->query(function ($query, array $data) {
                        if (filled($data['values'])) {
                            $query->whereHas('upload', function ($q) use ($data) {
                                $q->whereIn('type', $data['values']);
                            });
                        }
                    }),
                SelectFilter::make('branch_name')
                    ->label('Branch')
                    ->options(function () {
                        return collect(['Main Branch', 'Downtown', 'Uptown', 'North Branch'])
                            ->mapWithKeys(fn ($branch) => [$branch => $branch]);
                    })
                    ->placeholder('All Branches')
                    ->multiple(),

                Filter::make('created_at')
                    ->label('Date Range')
                    ->form([
                        DatePicker::make('created_from')
                            ->label('From Date')
                            ->placeholder('Select start date')
                            ->native(false),
                        DatePicker::make('created_until')
                            ->label('To Date')
                            ->placeholder('Select end date')
                            ->native(false),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['created_from'], fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['created_until'], fn ($q, $date) => $q->whereDate('created_at', '<=', $date));
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['created_from']) {
                            $indicators[] = 'From: ' . Carbon::parse($data['created_from'])->format('M j, Y');
                        }
                        if ($data['created_until']) {
                            $indicators[] = 'To: ' . Carbon::parse($data['created_until'])->format('M j, Y');
                        }
                        return $indicators;
                    }),

                Filter::make('recent')
                    ->label('Recent Vouchers')
                    ->query(fn ($query) => $query->where('created_at', '>=', now()->subDays(7)))
                    ->toggle(),

                Filter::make('has_check')
                    ->label('Has Check Number')
                    ->query(fn ($query) => $query->whereNotNull('ck_number'))
                    ->toggle(),

                Filter::make('has_ticket')
                    ->label('Has Ticket Number')
                    ->query(fn ($query) => $query->whereNotNull('ticket_number'))
                    ->toggle(),
            ])
            ->recordActions([
                EditAction::make()
                    ->label('Edit Voucher')
                    ->icon('heroicon-o-pencil')
                    ->color('warning'),

                Action::make('print_voucher')
                    ->label('Print Voucher')
                    ->icon('heroicon-o-printer')
                    ->color('success')
                    ->url(fn (Voucher $record) => route('voucher.print', $record))
                    ->openUrlInNewTab(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ])
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(10)
            ->poll('60s') // Auto-refresh every 60 seconds
            ->persistSortInSession()
            ->persistSearchInSession()
            ->persistFiltersInSession()
            ->extremePaginationLinks()
            ->emptyStateIcon('heroicon-o-document-text')
            ->emptyStateHeading('No vouchers found')
            ->emptyStateDescription('Create your first voucher to get started with financial record keeping.');
    }
}
