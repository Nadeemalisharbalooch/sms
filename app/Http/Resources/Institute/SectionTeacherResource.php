<?php

namespace App\Http\Resources\Institute;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SectionTeacherResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'section_id' => $this->section_id,
            'teacher_id' => $this->teacher_id,
            'section' => $this->whenLoaded('section', fn () => [
                'id' => $this->section->id,
                'name' => $this->section->name,
                'code' => $this->section->code,
            ]),
            'teacher' => $this->whenLoaded('teacher', fn () => [
                'id' => $this->teacher->id,
                'name' => $this->teacher->name,
                'email' => $this->teacher->email,
            ]),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
