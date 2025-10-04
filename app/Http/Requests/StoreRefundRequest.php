<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRefundRequest extends FormRequest
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
            'order_id' => [
                'required_without:payment_id',
                'integer',
                Rule::exists('orders', 'id')->where(function ($query) {
                    $query->where('store_id', auth()->user()->store_id);
                })
            ],
            'payment_id' => [
                'required_without:order_id',
                'integer',
                Rule::exists('payments', 'id')->where(function ($query) {
                    $query->whereHas('order', function ($orderQuery) {
                        $orderQuery->where('store_id', auth()->user()->store_id);
                    });
                })
            ],
            'amount' => 'required|numeric|min:0.01|max:999999.99',
            'reason' => 'required|string|max:500',
            'notes' => 'nullable|string|max:1000',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'order_id.required_without' => 'Either an order or payment must be selected for refund.',
            'order_id.exists' => 'The selected order is invalid or does not belong to your store.',
            'payment_id.required_without' => 'Either an order or payment must be selected for refund.',
            'payment_id.exists' => 'The selected payment is invalid or does not belong to your store.',
            'amount.required' => 'Refund amount is required.',
            'amount.numeric' => 'Refund amount must be a valid number.',
            'amount.min' => 'Refund amount must be at least 0.01.',
            'amount.max' => 'Refund amount is too large.',
            'reason.required' => 'Refund reason is required.',
            'reason.max' => 'Refund reason cannot exceed 500 characters.',
            'notes.max' => 'Notes cannot exceed 1000 characters.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'order_id' => 'order',
            'payment_id' => 'payment',
            'amount' => 'refund amount',
            'reason' => 'refund reason',
            'notes' => 'notes',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Validate that both order_id and payment_id are not provided
            if ($this->filled('order_id') && $this->filled('payment_id')) {
                $validator->errors()->add(
                    'payment_id',
                    'Cannot specify both order and payment for refund. Choose one.'
                );
            }
        });
    }
}