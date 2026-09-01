<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePriceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'price' => ['sometimes', 'required', 'numeric', 'gt:0'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['sometimes', 'required', 'string', 'max:10'],
            'effective_from' => ['sometimes', 'required', 'date'],
            'effective_until' => ['nullable', 'date', 'after:effective_from'],
        ];
    }
}
