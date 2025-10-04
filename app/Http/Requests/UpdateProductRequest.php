<?php

namespace App\Http\Requests;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('product'));
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $product = $this->route('product');

        return [
            'category_id' => [
                'required',
                'exists:categories,id',
                function ($attribute, $value, $fail) {
                    $category = Category::find($value);
                    if (!$category || $category->store_id !== $this->user()->store_id) {
                        $fail('The selected category is invalid.');
                    }
                }
            ],
            'name' => 'required|string|max:255',
            'sku' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('products')->where(function ($query) {
                    return $query->where('store_id', $this->user()->store_id);
                })->ignore($product->id)
            ],
            'description' => 'nullable|string|max:2000',
            'image' => 'nullable|string|max:255',
            'price' => 'required|numeric|min:0|max:999999.99',
            'cost_price' => 'nullable|numeric|min:0|max:999999.99',
            'track_inventory' => 'boolean',
            'stock' => 'integer|min:0',
            'min_stock_level' => 'integer|min:0',
            'status' => 'boolean',
            'is_favorite' => 'boolean',
            'sort_order' => 'integer|min:0',
            'price_change_reason' => 'nullable|string|max:255'
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'category_id.required' => 'Product category is required.',
            'category_id.exists' => 'The selected category is invalid.',
            'name.required' => 'Product name is required.',
            'name.max' => 'Product name cannot exceed 255 characters.',
            'sku.unique' => 'A product with this SKU already exists in your store.',
            'sku.max' => 'SKU cannot exceed 100 characters.',
            'price.required' => 'Product price is required.',
            'price.numeric' => 'Price must be a valid number.',
            'price.min' => 'Price cannot be negative.',
            'price.max' => 'Price cannot exceed 999,999.99.',
            'cost_price.numeric' => 'Cost price must be a valid number.',
            'cost_price.min' => 'Cost price cannot be negative.',
            'stock.integer' => 'Stock must be a whole number.',
            'stock.min' => 'Stock cannot be negative.',
            'min_stock_level.integer' => 'Minimum stock level must be a whole number.',
            'min_stock_level.min' => 'Minimum stock level cannot be negative.',
            'price_change_reason.max' => 'Price change reason cannot exceed 255 characters.'
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'track_inventory' => $this->boolean('track_inventory'),
            'status' => $this->boolean('status'),
            'is_favorite' => $this->boolean('is_favorite'),
            'stock' => $this->integer('stock'),
            'min_stock_level' => $this->integer('min_stock_level'),
            'sort_order' => $this->integer('sort_order')
        ]);
    }
}