<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductOptionRequest extends FormRequest
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
            'name' => 'sometimes|required|string|max:255',
            'value' => 'sometimes|required|string|max:255',
            'price_adjustment' => 'nullable|numeric|min:0',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'The option name is required.',
            'name.string' => 'The option name must be a string.',
            'name.max' => 'The option name may not be greater than 255 characters.',
            'value.required' => 'The option value is required.',
            'value.string' => 'The option value must be a string.',
            'value.max' => 'The option value may not be greater than 255 characters.',
            'price_adjustment.numeric' => 'The price adjustment must be a number.',
            'price_adjustment.min' => 'The price adjustment must be at least 0.',
            'is_active.boolean' => 'The is active field must be true or false.',
            'sort_order.integer' => 'The sort order must be an integer.',
            'sort_order.min' => 'The sort order must be at least 0.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'name' => 'option name',
            'value' => 'option value',
            'price_adjustment' => 'price adjustment',
            'is_active' => 'active status',
            'sort_order' => 'sort order',
        ];
    }
}