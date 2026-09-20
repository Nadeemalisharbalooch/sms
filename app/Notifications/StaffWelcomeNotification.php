<?php

namespace App\Notifications;

use App\Models\User;

class StaffWelcomeNotification extends BaseNotification
{
    public function __construct(
        public User $staff,
        public string $password,
        public string $loginUrl,
    ) {
    }

    public function title(): string
    {
        return 'Welcome to '.$this->instituteName();
    }

    public function summary(): string
    {
        return 'Your staff account has been created. Use your email and the temporary password below to sign in, then change it after your first login.';
    }

    public function priority(): string
    {
        return 'high';
    }

    public function category(): string
    {
        return 'account';
    }

    public function actionText(): string
    {
        return 'Log In to Your Account';
    }

    public function actionUrl(): string
    {
        return $this->loginUrl;
    }

    public function mailLines(): array
    {
        return [
            'Your staff account has been created. Here are your login details:',
            'Email: '.$this->staff->email,
            'Temporary Password: '.$this->password,
            'After signing in, please change your password as soon as possible.',
        ];
    }

    public function payload(): array
    {
        return [
            'user_id' => $this->staff->id,
            'institute_id' => $this->staff->instituteUsers()->first()?->institute_id,
        ];
    }

    private function instituteName(): string
    {
        return $this->staff->instituteUsers()->first()?->institute?->name ?? 'your institute';
    }
}