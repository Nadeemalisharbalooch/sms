<?php

namespace App\Http\Resources\Institute;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $enrollment = $this->whenLoaded('enrollments') ? $this->enrollments->first() : null;

        return [
            'id' => $this->id, 'institute_id' => $this->institute_id, 'first_name' => $this->first_name, 'last_name' => $this->last_name,
            'dob' => $this->dob?->toDateString(), 'gender' => $this->gender, 'guardian_name' => $this->guardian_name,
            'guardian_phone' => $this->guardian_phone, 'address' => $this->address, 'admission_date' => $this->admission_date?->toDateString(),
            'enrollment' => $enrollment ? [
                'id' => $enrollment->id, 'session_id' => $enrollment->session_id, 'class_id' => $enrollment->class_id,
                'section_id' => $enrollment->section_id, 'roll_number' => $enrollment->roll_number,
                'status' => $enrollment->result_status,
            ] : null,
            'created_at' => $this->created_at, 'updated_at' => $this->updated_at,
        ];
    }
}
