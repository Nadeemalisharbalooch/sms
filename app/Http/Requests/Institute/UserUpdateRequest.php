<?php

namespace App\Http\Requests\Institute;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UserUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $userId = $this->route('user');

        return [


            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,'.$userId.',id',
            'password' => 'nullable|string|min:8|confirmed',
            'is_admin' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
            'role' => 'sometimes|string|exists:roles,name',
        ];
    }
}
