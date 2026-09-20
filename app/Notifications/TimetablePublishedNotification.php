<?php

namespace App\Notifications;

class TimetablePublishedNotification extends BaseNotification
{
    public function __construct(
        public string $instituteName,
        public string $classNames,
    ) {
    }

    public function title(): string
    {
        return 'Timetable updated';
    }

    public function summary(): string
    {
        return "A new timetable has been published for {$this->classNames}. Please review your schedule.";
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
        return ['classes' => $this->classNames];
    }
}