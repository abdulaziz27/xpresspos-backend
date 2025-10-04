<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateExpenseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('expenses.update');
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
            'cash_session_id' => [
                'sometimes',
                'nullable',
                'uuid',
                Rule::exists('cash_sessions', 'id')->where(function ($query) {
                    return $query->where('store_id', auth()->user()->store_id);
                })
            ],
            'category' => ['sometimes', 'required', 'string', Rule::in($categories)],
            'description' => 'sometimes|required|string|max:255',
            'amount' => 'sometimes|required|numeric|min:0.01|max:999999.99',
            'receipt_number' => 'sometimes|nullable|string|max:100',
            'vendor' => 'sometimes|nullable|string|max:255',
            'expense_date' => 'sometimes|nullable|date|before_or_equal:today',
            'notes' => 'sometimes|nullable|string|max:1000',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'cash_session_id.exists' => 'The selected cash session does not exist or does not belong to your store.',
            'category.required' => 'Expense category is required.',
            'category.in' => 'The selected category is invalid.',
            'description.required' => 'Expense description is required.',
            'description.max' => 'Description cannot exceed 255 characters.',
            'amount.required' => 'Expense amount is required.',
            'amount.numeric' => 'Amount must be a valid number.',
            'amount.min' => 'Amount must be at least 0.01.',
            'amount.max' => 'Amount cannot exceed 999,999.99.',
            'receipt_number.max' => 'Receipt number cannot exceed 100 characters.',
            'vendor.max' => 'Vendor name cannot exceed 255 characters.',
            'expense_date.date' => 'Expense date must be a valid date.',
            'expense_date.before_or_equal' => 'Expense date cannot be in the future.',
            'notes.max' => 'Notes cannot exceed 1000 characters.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'cash_session_id' => 'cash session',
            'category' => 'category',
            'description' => 'description',
            'amount' => 'amount',
            'receipt_number' => 'receipt number',
            'vendor' => 'vendor',
            'expense_date' => 'expense date',
            'notes' => 'notes',
        ];
    }
}