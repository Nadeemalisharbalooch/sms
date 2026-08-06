<?php

namespace App\Http\Resources\Institute;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClassSubjectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            // 'id' => $this->id,
            // 'class_id' => $this->class_id,
            // 'section_id' => $this->section_id,
            // 'subject_id' => $this->subject_id,
            'subject' => $this->whenLoaded('subject', fn () => [
                'id' => $this->subject->id,
                'name' => $this->subject->name,
                'code' => $this->subject->code,
                'description' => $this->subject->description,
                'is_active' => $this->subject->is_active,
            ]),
            // 'section' => $this->whenLoaded('section', fn () => [
            //     'id' => $this->section->id,
            //     'name' => $this->section->name,
            //     'code' => $this->section->code,
            // ]),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}