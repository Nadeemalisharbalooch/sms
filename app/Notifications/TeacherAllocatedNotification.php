<?php

namespace App\Notifications;

use App\Models\User;

class TeacherAllocatedNotification extends BaseNotification
{
    public function __construct(
        public User $teacher,
        public string $instituteName,
        public string $details,
    ) {
    }

    public function title(): string
    {
        return 'New class assignment';
    }

    public function summary(): string
    {
        return "You have been assigned as a teacher for {$this->details}.";
    }

    public function priority(): string
    {
        return 'standard';
    }

    public function category(): string
    {
        return 'academics';
    }

    public function payload(): array
    {
        return [
            'user_id' => $this->teacher->id,
            'details' => $this->details,
        ];
    }
}