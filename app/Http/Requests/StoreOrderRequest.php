<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrderRequest extends FormRequest
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
                            $query->where('store_id', $store->id)
                                  ->where('status', 'available');
                        } else {
                            $query->whereRaw('1 = 0');
                        }
                    } else {
                        $query->whereRaw('1 = 0');
                    }
                })
            ],
            'status' => 'nullable|in:draft,open,completed',
            'service_charge' => 'nullable|numeric|min:0|max:999999.99',
            'discount_amount' => 'nullable|numeric|min:0|max:999999.99',
            'notes' => 'nullable|string|max:1000',
            
            // Items array (optional for initial order creation)
            'items' => 'nullable|array|min:1',
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
            'items.*.quantity' => 'required_with:items|integer|min:1|max:1000',
            'items.*.product_options' => 'nullable|array',
            'items.*.product_options.*' => [
                'uuid',
                Rule::exists('product_variants', 'id')->where(function ($query) {
                    $user = auth()->user() ?? request()->user();
                    if ($user) {
                        $tenantId = $user->store()?->tenant_id ?? $user->currentTenantId();
                        if ($tenantId) {
                            $query->where('tenant_id', $tenantId)
                                  ->where('is_active', true);
                        } else {
                            // If no tenant context, restrict to no results
                            $query->whereRaw('1 = 0');
                        }
                    } else {
                        $query->whereRaw('1 = 0');
                    }
                })
            ],
            'items.*.notes' => 'nullable|string|max:500',
            
            // Inventory management
            'deduct_inventory' => 'nullable|boolean',
            'operation_mode' => 'nullable|in:dine_in,takeaway,delivery',
            'payment_mode' => 'nullable|in:direct,open_bill',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'member_id.exists' => 'The selected member is invalid or does not belong to your store.',
            'table_id.exists' => 'The selected table is invalid, does not belong to your store, or is not available.',
            'items.*.product_id.exists' => 'One or more selected products are invalid or not available.',
            'items.*.quantity.min' => 'Item quantity must be at least 1.',
            'items.*.quantity.max' => 'Item quantity cannot exceed 1000.',
            'items.*.product_options.*.exists' => 'One or more selected product options are invalid.',
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
            'items.*.product_id' => 'product',
            'items.*.quantity' => 'quantity',
            'items.*.product_options' => 'product options',
            'items.*.notes' => 'item notes',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Set default values
        $this->merge([
            'status' => $this->input('status', 'draft'),
            'service_charge' => $this->input('service_charge', 0),
            'discount_amount' => $this->input('discount_amount', 0),
        ]);
    }
}