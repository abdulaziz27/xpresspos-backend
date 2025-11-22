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
                    $user = auth()->user() ?? request()->user();
                    if ($user) {
                        $store = $user->store();
                        if ($store) {
                            $query->where('store_id', $store->id);
                        } else {
                            $query->whereRaw('1 = 0');
                        }
                    } else {
                        $query->whereRaw('1 = 0');
                    }
                })
            ],
            'table_id' => [
                'nullable',
                'uuid',
                Rule::exists('tables', 'id')->where(function ($query) {
                    $user = auth()->user() ?? request()->user();
                    if ($user) {
                        $store = $user->store();
                        if ($store) {
                            $query->where('store_id', $store->id);
                        } else {
                            $query->whereRaw('1 = 0');
                        }
                    } else {
                        $query->whereRaw('1 = 0');
                    }
                })
            ],
            'status' => 'sometimes|in:draft,open,completed,cancelled',
            'service_charge' => 'sometimes|numeric|min:0|max:999999.99',
            'discount_amount' => 'sometimes|numeric|min:0|max:999999.99',
            'tax_amount' => 'sometimes|numeric|min:0|max:999999.99', // ✅ Add tax_amount validation
            'notes' => 'nullable|string|max:1000',
            
            // Items update
            'items' => 'nullable|array',
            'items.*.product_id' => [
                'required_with:items',
                'integer',
                Rule::exists('products', 'id')->where(function ($query) {
                    $user = auth()->user() ?? request()->user();
                    if ($user) {
                        $tenantId = $user->store()?->tenant_id ?? $user->currentTenantId();
                        if ($tenantId) {
                            $query->where('tenant_id', $tenantId)
                                  ->where('status', true);
                        } else {
                            // If no tenant context, restrict to no results
                            $query->whereRaw('1 = 0');
                        }
                    } else {
                        $query->whereRaw('1 = 0');
                    }
                })
            ],
            'items.*.product_name' => 'nullable|string|max:255',
            'items.*.quantity' => 'required_with:items|integer|min:1',
            'items.*.unit_price' => 'required_with:items|numeric|min:0',
            'items.*.total_price' => 'nullable|numeric|min:0',
            'items.*.notes' => 'nullable|string|max:500',
            
            // Inventory management
            'update_inventory' => 'nullable|boolean',
            'restore_inventory' => 'nullable|boolean',
            'cancel_payment' => 'nullable|boolean',
            'operation_mode' => 'nullable|in:dine_in,takeaway,delivery',
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
            'status.in' => 'The order status must be one of: draft, open, completed, cancelled.',
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