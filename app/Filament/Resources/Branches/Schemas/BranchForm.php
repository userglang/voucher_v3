<?php

namespace App\Filament\Resources\Branches\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BranchForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Branch Information')
                    ->columnSpan('full')
                    ->description('Basic details to identify this branch.')
                    ->icon('heroicon-o-building-office')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('branch_number')
                                    ->label('Branch Number')
                                    ->required()
                                    ->unique(ignoreRecord: true)
                                    ->maxLength(50)
                                    ->placeholder('e.g. BR-001')
                                    ->prefixIcon('heroicon-o-hashtag'),
                                TextInput::make('code')
                                    ->label('Branch Code')
                                    ->required()
                                    ->unique(ignoreRecord: true)
                                    ->maxLength(20)
                                    ->placeholder('e.g. CDO-MAIN')
                                    ->prefixIcon('heroicon-o-tag')
                                    ->alphaDash()
                                    ->hint('Letters, numbers, dashes and underscores only.')
                                    ->hintIcon('heroicon-m-information-circle'),
                            ]),

                        Grid::make(1)
                            ->schema([


                                TextInput::make('branch_name')
                                    ->label('Branch Name')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('e.g. Main Office')
                                    ->prefixIcon('heroicon-o-building-storefront'),

                                Textarea::make('address')
                                    ->label('Address')
                                    ->rows(3)
                                    ->maxLength(500)
                                    ->placeholder('Full branch address...')
                                    ->default(null)
                                    ->columnSpanFull(),
                            ]),
                    ]),

                Section::make('Status')
                    ->columnSpan('full')
                    ->description('Control whether this branch is currently operational.')
                    ->icon('heroicon-o-signal')
                    ->compact()
                    ->schema([
                        Toggle::make('is_active')
                            ->label('Active')
                            ->helperText('Inactive branches will be hidden from branch selection across the system.')
                            ->default(true)
                            ->onIcon('heroicon-m-check')
                            ->offIcon('heroicon-m-x-mark')
                            ->onColor('success')
                            ->offColor('danger'),
                    ]),
            ]);
    }
}
