<?php

namespace App\Http\Requests\Institute;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;


class UpdateRoleRequest extends FormRequest
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
    public function rules()
    {
        /** @var \App\Models\Role|null $role */

        return [
            'is_active' => 'nullable|boolean',
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('roles', 'name')->ignore(optional($this->route('role'))->id),

            ],
            'guard_name' => ['nullable', 'in:web,sanctum'],
            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,id',
        ];

    }
}
