<?php

namespace App\Rules;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class EmailRule implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // field is required
        if (empty($value)) {
            $fail("The {$attribute} is required.");

            return;
        }
        // Valid Email required
        if (! filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $fail("The {$attribute} is not a valid email address.");

            return;
        }

        // Email should be exists in database
        $user = User::where('email', $value)->first();
        if (! $user) {
            $fail("The {$attribute} does not exists in our record.");
        }
    }
}
