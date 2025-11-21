<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AddOrderItemRequest extends FormRequest
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
            'product_id' => [
                'required',
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
            'quantity' => 'required|integer|min:1|max:1000',
            'product_options' => 'nullable|array',
            'product_options.*' => [
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
            'notes' => 'nullable|string|max:500',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'product_id.required' => 'A product must be selected.',
            'product_id.exists' => 'The selected product is invalid or not available.',
            'quantity.required' => 'Quantity is required.',
            'quantity.integer' => 'Quantity must be a whole number.',
            'quantity.min' => 'Quantity must be at least 1.',
            'quantity.max' => 'Quantity cannot exceed 1000.',
            'product_options.array' => 'Product options must be provided as an array.',
            'product_options.*.uuid' => 'Each product option must be a valid identifier.',
            'product_options.*.exists' => 'One or more selected product options are invalid or not available.',
            'notes.string' => 'Notes must be text.',
            'notes.max' => 'Notes cannot exceed 500 characters.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'product_id' => 'product',
            'quantity' => 'quantity',
            'product_options' => 'product options',
            'product_options.*' => 'product option',
            'notes' => 'notes',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Ensure product_options is an array if provided
        if ($this->has('product_options') && !is_array($this->input('product_options'))) {
            $this->merge([
                'product_options' => []
            ]);
        }
    }
}