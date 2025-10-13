<?php

namespace App\Http\Requests;

use App\Models\Discount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDiscountRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        /** @var \App\Models\Discount $discount */
        $discount = $this->route('discount');

        return $this->user()->can('update', $discount);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        /** @var \App\Models\Discount $discount */
        $discount = $this->route('discount');
        $storeId = $discount->store_id;

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('discounts')
                    ->where(fn ($query) => $query->where('store_id', $storeId))
                    ->ignore($discount->id),
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
                    $this->input('type', $discount->type) === Discount::TYPE_PERCENTAGE,
                    ['max:100']
                ),
            ],
            'status' => [
                'required',
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
            'name.required' => 'Discount name is required.',
            'name.unique' => 'A discount with this name already exists for this store.',
            'type.required' => 'Discount type is required.',
            'type.in' => 'Discount type must be either percentage or fixed.',
            'value.required' => 'Discount value is required.',
            'value.numeric' => 'Discount value must be a number.',
            'value.max' => 'Percentage discounts cannot exceed 100%.',
            'status.required' => 'Discount status is required.',
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
            'status' => $this->input('status', $this->route('discount')->status),
        ]);
    }
}
