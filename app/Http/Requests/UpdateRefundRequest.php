<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRefundRequest extends FormRequest
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
        return [
            'amount' => 'sometimes|numeric|min:0.01|max:999999.99',
            'reason' => 'sometimes|string|max:500',
            'status' => [
                'sometimes',
                'string',
                Rule::in(['pending', 'completed', 'failed', 'cancelled'])
            ],
            'notes' => 'nullable|string|max:1000',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'amount.numeric' => 'Refund amount must be a valid number.',
            'amount.min' => 'Refund amount must be at least 0.01.',
            'amount.max' => 'Refund amount is too large.',
            'reason.max' => 'Refund reason cannot exceed 500 characters.',
            'status.in' => 'The selected status is not valid.',
            'notes.max' => 'Notes cannot exceed 1000 characters.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'amount' => 'refund amount',
            'reason' => 'refund reason',
            'status' => 'refund status',
            'notes' => 'notes',
        ];
    }
}