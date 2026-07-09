<?php

namespace App\Http\Requests\Institute;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UserStoreRequest extends FormRequest
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
        return [

            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'phone'  =>'nullable',
            'address'=>'nullable|string',
            'is_admin' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',

            'role_ids' => 'sometimes|nullable',
            'role_ids.*' => 'exists:roles,id',

            // Backward-compatible: frontend may send `role` as role IDs (array or comma-separated string).
            'role' => 'sometimes|nullable',
            'role.*' => 'exists:roles,id',
        ];
    }
}


