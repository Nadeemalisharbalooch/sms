<?php

namespace App\Http\Resources\Institute;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Include roles assigned to the user (Spatie)
        return array_merge(parent::toArray($request), [
            'roles' => $this->whenLoaded('roles', function () {
                // If relationship isn't eager loaded, this will return null
                return $this->roles->pluck('name')->values();
            }),
            'role' => $this->whenLoaded('roles', function () {
                // convenience single-role field (if only one role is assigned)
                return $this->roles->pluck('name')->first();
            }),
        ]);
    }
}
