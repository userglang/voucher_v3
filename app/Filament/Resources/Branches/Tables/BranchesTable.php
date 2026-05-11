<?php

namespace App\Filament\Resources\Branches\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class BranchesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('branch_number')
                    ->label('Branch #')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Branch number copied')
                    ->fontFamily('mono')
                    ->weight(FontWeight::Medium),

                TextColumn::make('branch_name')
                    ->label('Branch Name')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::SemiBold)
                    ->description(fn ($record) => $record->code ?? null),

                TextColumn::make('address')
                    ->searchable()
                    ->limit(50)
                    ->tooltip(fn ($record) => $record->address)
                    ->placeholder('No address provided')
                    ->icon('heroicon-o-map-pin')
                    ->color('gray')
                    ->toggleable(),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->alignCenter(),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('M d, Y')
                    ->sortable()
                    ->since()
                    ->tooltip(fn ($record) => $record->created_at?->format('F d, Y h:i A'))
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime('M d, Y')
                    ->sortable()
                    ->since()
                    ->tooltip(fn ($record) => $record->updated_at?->format('F d, Y h:i A'))
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Status')
                    ->placeholder('All Branches')
                    ->trueLabel('Active Only')
                    ->falseLabel('Inactive Only')
                    ->native(false),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('branch_name', 'asc')
            ->striped()
            ->paginated([10, 25, 50, 100])
            ->emptyStateIcon('heroicon-o-building-office')
            ->emptyStateHeading('No branches found')
            ->emptyStateDescription('Once branches are added, they will appear here.')
            ->persistFiltersInSession()
            ->persistSearchInSession();
    }
}
