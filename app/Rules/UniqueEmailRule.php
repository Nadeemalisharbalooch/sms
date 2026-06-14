<?php

namespace App\Rules;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class UniqueEmailRule implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $user = User::where('email', $value)->first();

        if (empty($value)) {
            $fail("The {$attribute} field is required.");

            return;
        }

        if (strlen($value) > 255) {
            $fail("The {$value} is too long.");

            return;
        }

        if (! filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $fail("The {$value} is not a valid email address.");

            return;
        }

        if ($user) {
            $fail("The {$value} is already registered.");

            return;
        }
    }
}
