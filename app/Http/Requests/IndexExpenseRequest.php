<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexExpenseRequest extends FormRequest
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
     */
    public function rules(): array
    {
        $categories = [
            'office_supplies',
            'utilities',
            'maintenance',
            'marketing',
            'travel',
            'meals',
            'professional_services',
            'inventory',
            'equipment',
            'rent',
            'insurance',
            'taxes',
            'miscellaneous'
        ];

        return [
            'category' => ['nullable', 'string', Rule::in($categories)],
            'start_date' => 'nullable|date|date_format:Y-m-d',
            'end_date' => 'nullable|date|date_format:Y-m-d|after_or_equal:start_date',
            'cash_session_id' => 'nullable|uuid|exists:cash_sessions,id',
            'user_id' => 'nullable|uuid|exists:users,id',
            'vendor' => 'nullable|string|max:255',
            'min_amount' => 'nullable|numeric|min:0',
            'max_amount' => 'nullable|numeric|min:0|gte:min_amount',
            'per_page' => 'nullable|integer|min:1|max:100',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'category.in' => 'The selected category is invalid.',
            'start_date.date' => 'The start date must be a valid date.',
            'start_date.date_format' => 'The start date must be in format Y-m-d.',
            'end_date.date' => 'The end date must be a valid date.',
            'end_date.date_format' => 'The end date must be in format Y-m-d.',
            'end_date.after_or_equal' => 'The end date must be after or equal to the start date.',
            'cash_session_id.exists' => 'The selected cash session does not exist.',
            'user_id.exists' => 'The selected user does not exist.',
            'vendor.max' => 'The vendor name cannot exceed 255 characters.',
            'min_amount.numeric' => 'The minimum amount must be a valid number.',
            'min_amount.min' => 'The minimum amount cannot be negative.',
            'max_amount.numeric' => 'The maximum amount must be a valid number.',
            'max_amount.min' => 'The maximum amount cannot be negative.',
            'max_amount.gte' => 'The maximum amount must be greater than or equal to the minimum amount.',
            'per_page.integer' => 'The per page value must be an integer.',
            'per_page.min' => 'The per page value must be at least 1.',
            'per_page.max' => 'The per page value cannot exceed 100.',
        ];
    }
}

