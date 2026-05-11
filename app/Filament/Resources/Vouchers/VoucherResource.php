<?php

namespace App\Filament\Resources\Vouchers;

use App\Filament\Resources\Vouchers\Pages\CreateVoucher;
use App\Filament\Resources\Vouchers\Pages\EditVoucher;
use App\Filament\Resources\Vouchers\Pages\ListVouchers;
use App\Filament\Resources\Vouchers\RelationManagers;
use App\Filament\Resources\Vouchers\Schemas\VoucherForm;
use App\Filament\Resources\Vouchers\Tables\VouchersTable;
use App\Models\Voucher;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class VoucherResource extends Resource
{
    protected static ?string $model = Voucher::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::DocumentText;

    protected static ?string $navigationLabel = 'Voucher';

    protected static ?string $pluralModelLabel = 'Vouchers';

    protected static ?string $modelLabel = 'Voucher';

    public static function getNavigationBadge(): ?string
    {
        $user = Auth::user();

        if ($user?->hasRole('super_admin')) {
            return (string) static::getModel()::count();
        }

        $branchNumber = $user?->branch?->branch_number;

        return (string) static::getModel()::whereHas('upload', fn ($q) =>
            $q->where('branch_number', $branchNumber)
        )->count();
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        return "{$record->ticket_number}";
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['ticket_number', 'voucher_number', 'ck_number', 'payee', 'description'];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        $query = parent::getGlobalSearchEloquentQuery()
            ->select(['id', 'ticket_number', 'voucher_number', 'ck_number', 'payee', 'description', 'prepared_by'])
            ->orderBy('created_at', 'desc');

        $user = Auth::user();
        if ($user && !$user->hasRole('super_admin')) {
            if ($user->branch->branch_number) {
                $branch_ticket = '-' . $user->branch->branch_number;
                $query->where('ticket_number', 'like', "%$branch_ticket");
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        return $query;
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        $description = $record->description
            ? (strlen($record->description) > 60
                ? substr($record->description, 0, 60) . '...'
                : $record->description)
            : null;

        return [
            'Voucher Number' => $record->upload->voucher_number,
            'Payee'          => $record->payee ?? ' - Not Applicable -',
            'Description'    => $description,
            'Prepared By'    => $record->prepared_by,
        ];
    }

    public static function getGlobalSearchResultActions(Model $record): array
    {
        return [
            Action::make('open')
                ->label('Print Voucher')
                ->url(route('voucher.print', $record), shouldOpenInNewTab: true),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return VoucherForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return VouchersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\VoucherDetailsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListVouchers::route('/'),
            'create' => CreateVoucher::route('/create'),
            'edit'   => EditVoucher::route('/{record}/edit'),
        ];
    }
}
