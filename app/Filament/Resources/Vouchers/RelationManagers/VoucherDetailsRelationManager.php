<?php

namespace App\Filament\Resources\Vouchers\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class VoucherDetailsRelationManager extends RelationManager
{
    protected static string $relationship = 'voucherDetails';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('account_code')
                    ->label('Account Code')
                    ->placeholder('e.g., 501-01')
                    ->required(),

                TextInput::make('account_title')
                    ->label('Account Title')
                    ->placeholder('e.g., Office Supplies')
                    ->required(),

                Select::make('type')
                    ->label('Entry Type')
                    ->options([
                        'Dr' => 'Dr',
                        'Cr' => 'Cr',
                    ])
                    ->required()
                    ->native(false),

                TextInput::make('amount')
                    ->label('Amount (PHP)')
                    ->prefix('₱')
                    ->numeric()
                    ->inputMode('decimal')
                    ->placeholder('e.g., 1000.00')
                    ->required()
                    ->rule('decimal:2'),

                TextInput::make('series_id')
                    ->label('Series ID')
                    ->placeholder('Unique identifier for the series')
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('account_code')
                    ->label('Account Code'),

                TextColumn::make('account_title')
                    ->label('Account Title'),

                TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->colors([
                        'primary' => 'debit',
                        'success' => 'credit',
                    ]),

                TextColumn::make('amount')
                    ->label('Amount')
                    ->money('PHP', true)
                    ->alignRight(),

                TextColumn::make('series_id')
                    ->label('Series ID')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ]);
    }
}
