<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class ChangePassword extends Page implements HasForms
{
    use InteractsWithForms;

    protected string $view = 'filament.pages.change-password';

    protected static ?string $title = 'Change Password';
    protected static ?string $navigationLabel = 'Change Password';
    protected static bool $shouldRegisterNavigation = true;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLockClosed;
    protected static ?string $slug = 'change-password';

    protected static ?int $navigationSort = 99;

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Change Your Password')
                    ->description('Please enter your current password and choose a new secure password.')
                    ->icon('heroicon-o-lock-closed')
                    ->schema([
                        TextInput::make('current_password')
                            ->label('Current Password')
                            ->password()
                            ->required()
                            ->revealable()
                            ->rules(['required', 'string'])
                            ->validationAttribute('current password')
                            ->helperText('Enter your existing password to confirm your identity.')
                            ->placeholder('Enter your current password'),

                        TextInput::make('password')
                            ->label('New Password')
                            ->password()
                            ->required()
                            ->rule(Password::min(8)
                                ->mixedCase()
                                ->numbers()
                                ->symbols()
                                ->uncompromised()
                            )
                            ->revealable()
                            ->same('password_confirmation')
                            ->validationAttribute('new password')
                            ->helperText('Must be at least 8 characters with uppercase, lowercase, numbers, and symbols.')
                            ->placeholder('Enter your new password')
                            ->live(onBlur: true),

                        TextInput::make('password_confirmation')
                            ->label('Confirm New Password')
                            ->password()
                            ->revealable()
                            ->required()
                            ->dehydrated(false)
                            ->validationAttribute('password confirmation')
                            ->helperText('Re-enter your new password to confirm.')
                            ->placeholder('Confirm your new password')
                            ->live(onBlur: true),
                    ])
                    ->columns(1)
                    ->collapsible(false),
            ])
            ->statePath('data');
    }

    public function changePassword(): void
    {
        try {
            $data = $this->form->getState();
            $user = Auth::user();

            if (!Hash::check($data['current_password'], $user->password)) {
                throw ValidationException::withMessages([
                    'data.current_password' => 'The current password is incorrect.',
                ]);
            }

            if (Hash::check($data['password'], $user->password)) {
                throw ValidationException::withMessages([
                    'data.password' => 'Your new password must be different from your current password.',
                ]);
            }

            $user->update([
                'password'            => Hash::make($data['password']),
                'password_changed_at' => now(),
            ]);

            $this->form->fill([]);

            Notification::make()
                ->success()
                ->title('Password Changed Successfully!')
                ->body('Your password has been updated securely. You can now use your new password to log in.')
                ->duration(5000)
                ->send();

            $this->redirect('/');

        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Notification::make()
                ->danger()
                ->title('Error Changing Password')
                ->body('An unexpected error occurred. Please try again or contact support if the problem persists.')
                ->duration(8000)
                ->send();
        }
    }

    public function getFormActions(): array
    {
        return [
            Action::make('changePassword')
                ->label('Change Password')
                ->icon('heroicon-o-check')
                ->color('primary')
                ->size('lg')
                ->submit('changePassword')
                ->keyBindings(['mod+enter']),

            Action::make('cancel')
                ->label('Cancel')
                ->icon('heroicon-o-x-mark')
                ->color('gray')
                ->size('lg')
                ->url('/staff'),
        ];
    }
}
