<?php

namespace App\Http\Requests;

use App\Enums\AssignmentRoleEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserAssignmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled by middleware/policies
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $rules = [
            'store_id' => 'required|exists:stores,id',
            'user_id' => 'required|exists:users,id',
            'assignment_role' => [
                'required',
                'string',
                Rule::in(AssignmentRoleEnum::values())
            ],
            'is_primary' => 'boolean'
        ];

        // For updates, make fields optional
        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            $rules['store_id'] = 'sometimes|exists:stores,id';
            $rules['user_id'] = 'sometimes|exists:users,id';
            $rules['assignment_role'] = [
                'sometimes',
                'string',
                Rule::in(AssignmentRoleEnum::values())
            ];
        }

        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'store_id.required' => 'Store is required',
            'store_id.exists' => 'Selected store does not exist',
            'user_id.required' => 'User is required',
            'user_id.exists' => 'Selected user does not exist',
            'assignment_role.required' => 'Assignment role is required',
            'assignment_role.in' => 'Assignment role must be one of: ' . implode(', ', AssignmentRoleEnum::values()),
            'is_primary.boolean' => 'Primary flag must be true or false'
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'store_id' => 'store',
            'user_id' => 'user',
            'assignment_role' => 'role',
            'is_primary' => 'primary store'
        ];
    }
}