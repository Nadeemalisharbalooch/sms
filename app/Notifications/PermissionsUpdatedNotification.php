<?php

namespace App\Notifications;

use App\Models\User;

class PermissionsUpdatedNotification extends BaseNotification
{
    public function __construct(
        public User $user,
        public string $roleName,
    ) {
    }

    public function title(): string
    {
        return 'Permissions updated';
    }

    public function summary(): string
    {
        return "Your access level ({$this->roleName}) has been updated. If you notice any issues, please contact your institute administrator.";
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
        return [
            'user_id' => $this->user->id,
            'role' => $this->roleName,
        ];
    }
}