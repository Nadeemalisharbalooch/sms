<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    protected $fillable = [
        'name', 'description', 'price', 'billing_interval', 'trial_days',
        'student_limit', 'teacher_limit', 'class_limit', 'features', 'is_active',
    ];

    protected function casts(): array
    {
        return ['price' => 'decimal:2', 'is_active' => 'boolean'];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(InstituteSubscription::class);
    }

    /**
     * Returns the usage metric keys (e.g. ['students']) whose current value
     * exceeds the plan's limit. Plans with a null limit are unlimited.
     */
    public function usageLimitsExceeded(array $usage): array
    {
        $exceeded = [];

        foreach (['student_limit' => 'students', 'teacher_limit' => 'teachers', 'class_limit' => 'classes'] as $limit => $metric) {
            $limitValue = $this->{$limit};
            if ($limitValue !== null && ((int) ($usage[$metric] ?? 0)) > (int) $limitValue) {
                $exceeded[] = $metric;
            }
        }

        return $exceeded;
    }
}
