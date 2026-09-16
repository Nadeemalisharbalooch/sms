<?php

namespace App\Http\Resources\Institute;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InstituteResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'public_id' => $this->public_id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'logo' => $this->logo,
            'favicon' => $this->favicon,
            'attendance_mode' => $this->attendance_mode,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,

            // Subscription status for the current institute.
            'subscription' => $this->whenLoaded('subscription', function () {
                $status = $this->subscription?->status;
                $isExpired = $this->subscription?->ends_at?->isPast()
                    && in_array($status, ['trial', 'active'], true);

                return [
                    'status' => $status,
                    'is_expired' => $isExpired || $status === 'expired',
                    'blocked' => $status === null || ! in_array($status, ['trial', 'active'], true) || $isExpired,
                    'days_remaining' => $this->subscription?->ends_at
                        ? max(0, (int) ceil(now()->diffInSeconds($this->subscription->ends_at, false) / 86400))
                        : null,
                ];
            }),
        ];
    }
}
