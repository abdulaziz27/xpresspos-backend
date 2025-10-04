<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProcessPaymentRequest extends FormRequest
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
            'order_id' => 'required|uuid|exists:orders,id',
            'payment_method' => [
                'required',
                'string',
                Rule::in(['cash', 'card', 'qris', 'bank_transfer', 'e_wallet'])
            ],
            'amount' => 'required|numeric|min:0.01',
            'reference_number' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:500',
            'received_amount' => 'nullable|numeric|min:0', // For cash payments
            'change_amount' => 'nullable|numeric|min:0', // For cash payments
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'payment_method.required' => 'Payment method is required.',
            'payment_method.in' => 'The selected payment method is not valid.',
            'amount.required' => 'Payment amount is required.',
            'amount.numeric' => 'Payment amount must be a valid number.',
            'amount.min' => 'Payment amount must be greater than 0.',
            'reference_number.max' => 'Reference number cannot exceed 255 characters.',
            'notes.max' => 'Notes cannot exceed 500 characters.',
            'received_amount.numeric' => 'Received amount must be a valid number.',
            'change_amount.numeric' => 'Change amount must be a valid number.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'payment_method' => 'payment method',
            'amount' => 'payment amount',
            'reference_number' => 'reference number',
            'notes' => 'notes',
            'received_amount' => 'received amount',
            'change_amount' => 'change amount',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Generate reference number for cash payments if not provided
        if ($this->input('payment_method') === 'cash' && !$this->input('reference_number')) {
            $this->merge([
                'reference_number' => 'CASH-' . now()->format('YmdHis') . '-' . rand(1000, 9999)
            ]);
        }

        // Calculate change for cash payments
        if ($this->input('payment_method') === 'cash' && $this->input('received_amount')) {
            $changeAmount = max(0, $this->input('received_amount') - $this->input('amount'));
            $this->merge([
                'change_amount' => $changeAmount
            ]);
        }
    }
}