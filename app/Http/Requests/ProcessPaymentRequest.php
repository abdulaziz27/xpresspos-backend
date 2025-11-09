<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Enums\PaymentMethodEnum;

class ProcessPaymentRequest extends FormRequest
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
     */
    public function rules(): array
    {
        $paymentMethods = array_column(PaymentMethodEnum::getAll(), 'id');
        // Add 'pending' for open bill payments
        $paymentMethods[] = 'pending';
        
        return [
            'order_id' => 'required|uuid|exists:orders,id',
            'payment_method' => 'required|string|in:' . implode(',', $paymentMethods),
            'amount' => 'required|numeric|min:0.01|max:999999.99',
            'received_amount' => 'nullable|numeric|min:0',
            'status' => 'nullable|string|in:pending,completed,failed',
            'reference_number' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:1000',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'order_id.required' => 'Order ID is required.',
            'order_id.exists' => 'The selected order does not exist.',
            'payment_method.required' => 'Payment method is required.',
            'payment_method.in' => 'The selected payment method is invalid.',
            'amount.required' => 'Payment amount is required.',
            'amount.min' => 'Payment amount must be at least 0.01.',
            'amount.max' => 'Payment amount cannot exceed 999,999.99.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'order_id' => 'order',
            'payment_method' => 'payment method',
            'reference_number' => 'reference number',
        ];
    }
}