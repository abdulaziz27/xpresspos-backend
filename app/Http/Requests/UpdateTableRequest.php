<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTableRequest extends FormRequest
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
        $table = $this->route('table');

        return [
            'table_number' => [
                'sometimes',
                'string',
                'max:20',
                Rule::unique('tables')->where(function ($query) {
                    return $query->where('store_id', auth()->user()->store_id);
                })->ignore($table->id)
            ],
            'name' => 'sometimes|nullable|string|max:255',
            'capacity' => 'sometimes|integer|min:1|max:50',
            'status' => [
                'sometimes',
                'string',
                Rule::in(['available', 'occupied', 'reserved', 'maintenance'])
            ],
            'location' => 'sometimes|nullable|string|max:255',
            'is_active' => 'sometimes|boolean',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'table_number.unique' => 'This table number already exists in your store.',
            'table_number.max' => 'Table number cannot exceed 20 characters.',
            'capacity.integer' => 'Table capacity must be a number.',
            'capacity.min' => 'Table capacity must be at least 1.',
            'capacity.max' => 'Table capacity cannot exceed 50.',
            'status.in' => 'The selected status is not valid.',
            'name.max' => 'Table name cannot exceed 255 characters.',
            'location.max' => 'Location cannot exceed 255 characters.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'table_number' => 'table number',
            'name' => 'table name',
            'capacity' => 'table capacity',
            'status' => 'table status',
            'location' => 'location',
            'is_active' => 'active status',
        ];
    }
}