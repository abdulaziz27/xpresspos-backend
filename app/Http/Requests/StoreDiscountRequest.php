<?php

namespace App\Http\Requests;

use App\Models\Discount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDiscountRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', Discount::class);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $storeId = $this->resolveStoreId();

        return [
            'store_id' => [
                Rule::requiredIf(fn () => $this->user()->hasRole('admin_sistem')),
                'nullable',
                'uuid',
                'exists:stores,id',
            ],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('discounts')->where(function ($query) use ($storeId) {
                    if ($storeId) {
                        $query->where('store_id', $storeId);
                    }
                    return $query;
                }),
            ],
            'description' => 'nullable|string|max:1000',
            'type' => [
                'required',
                Rule::in([Discount::TYPE_PERCENTAGE, Discount::TYPE_FIXED]),
            ],
            'value' => [
                'required',
                'numeric',
                'min:0',
                Rule::when(
                    $this->input('type') === Discount::TYPE_PERCENTAGE,
                    ['max:100']
                ),
            ],
            'status' => [
                'nullable',
                Rule::in([Discount::STATUS_ACTIVE, Discount::STATUS_INACTIVE]),
            ],
            'expired_date' => [
                'nullable',
                'date',
                'after_or_equal:today',
            ],
        ];
    }

    /**
     * Custom validation messages.
     */
    public function messages(): array
    {
        return [
            'store_id.required' => 'Store ID is required for system administrators.',
            'store_id.uuid' => 'Store ID must be a valid UUID.',
            'store_id.exists' => 'Selected store does not exist.',
            'name.required' => 'Discount name is required.',
            'name.unique' => 'A discount with this name already exists for the selected store.',
            'type.required' => 'Discount type is required.',
            'type.in' => 'Discount type must be either percentage or fixed.',
            'value.required' => 'Discount value is required.',
            'value.numeric' => 'Discount value must be a number.',
            'value.max' => 'Percentage discounts cannot exceed 100%.',
            'status.in' => 'Discount status must be either active or inactive.',
            'expired_date.after_or_equal' => 'Expiration date cannot be in the past.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'status' => $this->input('status', Discount::STATUS_ACTIVE),
        ]);
    }

    /**
     * Resolve the store ID used for validation.
     */
    protected function resolveStoreId(): ?string
    {
        if ($this->user()->hasRole('admin_sistem')) {
            return $this->input('store_id');
        }

        return $this->user()->store_id;
    }
}
