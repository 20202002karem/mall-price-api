<?php

namespace App\Http\Requests;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'barcode' => ['nullable', 'string', 'max:100', 'unique:products,barcode'],
            'sku' => ['nullable', 'string', 'max:255'],
            'brand' => ['nullable', 'string', 'max:255'],
            'image' => ['nullable', 'image', 'max:2048'],
            'description' => ['nullable', 'string'],
            'availability' => ['nullable', 'string', Rule::in(['available', 'out_of_stock', 'unknown'])],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->filled('category_id')) {
                $category = Category::find($this->input('category_id'));

                if ($category && !$category->is_active) {
                    $validator->errors()->add('category_id', 'Selected category is inactive.');
                }
            }
        });
    }
}
