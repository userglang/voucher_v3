<?php

namespace App\Filament\Resources\Users\Tables;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Full Name')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold')
                    ->icon('heroicon-m-user'),

                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Email copied!')
                    ->icon('heroicon-m-envelope'),

                TextColumn::make('branch.branch_name')
                    ->label('Branch')
                    ->sortable()
                    ->badge()
                    ->color('info')
                    ->icon('heroicon-m-building-office'),

                BadgeColumn::make('is_active')
                    ->label('Status')
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Active' : 'Inactive')
                    ->colors([
                        'success' => 'Active',
                        'danger' => 'Inactive',
                    ])
                    ->icons([
                        'heroicon-m-check-circle' => 'Active',
                        'heroicon-m-x-circle' => 'Inactive',
                    ]),

                TextColumn::make('created_at')
                    ->label('Added')
                    ->dateTime('M j, Y')
                    ->sortable()
                    ->toggleable()
                    ->since()
                    ->icon('heroicon-m-calendar'),
            ])
            ->filters([
                //
                SelectFilter::make('branch_id')
                    ->relationship('branch', 'branch_name')
                    ->label('Filter by Branch')
                    ->placeholder('All branches')
                    ->multiple(),

                TernaryFilter::make('is_active')
                    ->label('Account Status')
                    ->placeholder('All statuses')
                    ->trueLabel('Active users only')
                    ->falseLabel('Inactive users only')
                    ->default(null),
            ])
            ->recordActions([
                EditAction::make()
                    ->label('Edit Account')
                    ->icon('heroicon-m-pencil-square')
                    ->color('warning'),
                Action::make('resetPassword')
                    ->label('Reset Password')
                    ->icon('heroicon-m-key')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading('Reset Password')
                    ->modalDescription('Are you sure you want to reset the password for this user? It will be set to the default: password123.')
                    ->modalSubmitActionLabel('Yes, reset')
                    ->visible(fn ($record) => Auth::user()->can('update_user', $record))
                    ->action(function ($record) {
                        $record->update([
                            'password' => Hash::make('password123'),
                        ]);
                    })
                    ->successNotification(
                        Notification::make()
                            ->success()
                            ->title('Password Reset')
                            ->body('The password has been reset to the default: password123.')
                    ),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ]);
    }
}
