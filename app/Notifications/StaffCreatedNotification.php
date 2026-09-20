<?php

namespace App\Notifications;

use App\Models\User;

class StaffCreatedNotification extends BaseNotification
{
    public function __construct(
        public User $staff,
        public string $instituteName,
    ) {
    }

    public function title(): string
    {
        return 'Welcome to '.$this->instituteName;
    }

    public function summary(): string
    {
        return 'Your staff account was created on '.$this->instituteName.'. Welcome aboard!';
    }

    public function priority(): string
    {
        return 'standard';
    }

    public function category(): string
    {
        return 'account';
    }

    public function payload(): array
    {
        return ['user_id' => $this->staff->id];
    }
}