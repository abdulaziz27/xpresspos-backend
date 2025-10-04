<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMemberRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization is handled in the controller
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $member = $this->route('member');

        return [
            'name' => 'sometimes|string|max:255',
            'email' => [
                'sometimes',
                'nullable',
                'email',
                'max:255',
                Rule::unique('members')->where(function ($query) {
                    return $query->where('store_id', auth()->user()->store_id);
                })->ignore($member->id)
            ],
            'phone' => [
                'sometimes',
                'nullable',
                'string',
                'max:20',
                Rule::unique('members')->where(function ($query) {
                    return $query->where('store_id', auth()->user()->store_id);
                })->ignore($member->id)
            ],
            'date_of_birth' => 'sometimes|nullable|date|before:today',
            'address' => 'sometimes|nullable|string|max:500',
            'loyalty_points' => 'sometimes|integer|min:0|max:999999',
            'is_active' => 'sometimes|boolean',
            'notes' => 'sometimes|nullable|string|max:1000',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.max' => 'Member name cannot exceed 255 characters.',
            'email.email' => 'Please provide a valid email address.',
            'email.unique' => 'This email address is already registered for another member.',
            'email.max' => 'Email address cannot exceed 255 characters.',
            'phone.unique' => 'This phone number is already registered for another member.',
            'phone.max' => 'Phone number cannot exceed 20 characters.',
            'date_of_birth.date' => 'Please provide a valid date of birth.',
            'date_of_birth.before' => 'Date of birth must be before today.',
            'address.max' => 'Address cannot exceed 500 characters.',
            'loyalty_points.integer' => 'Loyalty points must be a number.',
            'loyalty_points.min' => 'Loyalty points cannot be negative.',
            'loyalty_points.max' => 'Loyalty points cannot exceed 999,999.',
            'notes.max' => 'Notes cannot exceed 1000 characters.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'name' => 'member name',
            'email' => 'email address',
            'phone' => 'phone number',
            'date_of_birth' => 'date of birth',
            'address' => 'address',
            'loyalty_points' => 'loyalty points',
            'is_active' => 'active status',
            'notes' => 'notes',
        ];
    }
}