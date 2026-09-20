<?php

namespace App\Notifications;

class StudentAdmittedNotification extends BaseNotification
{
    public function __construct(
        public string $instituteName,
        public string $studentName,
        public string $classSection,
    ) {
    }

    public function title(): string
    {
        return 'New student admitted';
    }

    public function summary(): string
    {
        return "A new student, {$this->studentName}, was admitted to {$this->classSection}.";
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
            'student_name' => $this->studentName,
            'class_section' => $this->classSection,
        ];
    }
}