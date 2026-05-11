<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Models\Branch;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('👤 Personal Information')
                    ->columnSpan('full')
                    ->description('Enter the basic details for this team member')
                    ->schema([
                        TextInput::make('name')
                            ->label('Full Name')
                            ->placeholder('e.g., John Doe')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Enter the person\'s full name as it should appear in the system'),

                        TextInput::make('email')
                            ->label('Email Address')
                            ->placeholder('e.g., john@company.com')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->helperText('This will be used for login and notifications')
                            ->suffixIcon('heroicon-m-envelope'),
                    ])
                    ->columns(2),

                Section::make('🔐 Security Settings')
                    ->columnSpan('full')
                    ->description('Configure login credentials and permissions')
                    ->schema([
                        TextInput::make('password')
                            ->label('Password')
                            ->placeholder('Enter a secure password')
                            ->password()
                            ->default('password123')
                            ->revealable()
                            ->required(fn ($livewire) => $livewire instanceof CreateUser)
                            ->dehydrateStateUsing(fn ($state) => !empty($state) ? Hash::make($state) : null)
                            ->maxLength(255)
                            ->minLength(8)
                            ->helperText('Password must be at least 8 characters long')
                            ->hiddenOn('edit')
                            ->suffixIcon('heroicon-m-key'),

                        Placeholder::make('password_change_note')
                            ->label('Password Management')
                            ->content('To change the password, the user should use the "Forgot Password" feature or contact an administrator.')
                            ->visibleOn('edit'),
                    ])
                    ->columns(1),

                Section::make('🏢 Work Assignment')
                    ->columnSpan('full')
                    ->description('Assign the team member to their work location')
                    ->schema([
                        Select::make('branch_id')
                            ->label('Branch/Location')
                            ->placeholder('Select a branch...')
                            ->options(
                                Branch::query()->pluck('branch_name', 'id')
                            )
                            ->searchable()
                            ->preload()
                            ->required()
                            ->helperText('Choose the primary branch where this person will work')
                            ->suffixIcon('heroicon-m-building-office'),

                        Select::make('roles')
                            ->relationship('roles', 'name')
                            ->multiple()
                            ->preload()
                            ->searchable(),

                        Toggle::make('is_active')
                            ->label('Account Status')
                            ->helperText('Turn off to temporarily disable login access')
                            ->default(true)
                            ->onColor('success')
                            ->offColor('danger')
                            ->onIcon('heroicon-m-check-circle')
                            ->offIcon('heroicon-m-x-circle'),
                    ])
                    ->columns(2),
            ]);
    }
}
