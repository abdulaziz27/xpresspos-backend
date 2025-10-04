<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrderRequest extends FormRequest
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
            'member_id' => [
                'nullable',
                'uuid',
                Rule::exists('members', 'id')->where(function ($query) {
                    $query->where('store_id', auth()->user()->store_id);
                })
            ],
            'table_id' => [
                'nullable',
                'uuid',
                Rule::exists('tables', 'id')->where(function ($query) {
                    $query->where('store_id', auth()->user()->store_id);
                })
            ],
            'status' => 'sometimes|in:draft,open,completed',
            'service_charge' => 'sometimes|numeric|min:0|max:999999.99',
            'discount_amount' => 'sometimes|numeric|min:0|max:999999.99',
            'notes' => 'nullable|string|max:1000',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'member_id.exists' => 'The selected member is invalid or does not belong to your store.',
            'table_id.exists' => 'The selected table is invalid or does not belong to your store.',
            'status.in' => 'The order status must be one of: draft, open, completed.',
            'service_charge.numeric' => 'The service charge must be a valid number.',
            'service_charge.min' => 'The service charge cannot be negative.',
            'service_charge.max' => 'The service charge is too large.',
            'discount_amount.numeric' => 'The discount amount must be a valid number.',
            'discount_amount.min' => 'The discount amount cannot be negative.',
            'discount_amount.max' => 'The discount amount is too large.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'member_id' => 'member',
            'table_id' => 'table',
            'service_charge' => 'service charge',
            'discount_amount' => 'discount amount',
        ];
    }
}