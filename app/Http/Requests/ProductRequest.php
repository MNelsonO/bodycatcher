<?php

namespace App\Http\Requests;

use App\Settings\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        $product = $this->route('product');

        if ($product instanceof Product) {
            return $this->user()?->can('update', $product) ?? false;
        }

        return $this->user()?->can('create', Product::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $slug = $this->input('slug');
        $name = $this->input('name');

        if ((! is_string($slug) || trim($slug) === '') && is_string($name)) {
            $this->merge(['slug' => Str::slug($name)]);
        } elseif (is_string($slug)) {
            $this->merge(['slug' => Str::slug($slug)]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $product = $this->route('product');
        $ignore = $product instanceof Product ? $product->id : null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('products', 'slug')->ignore($ignore)],
            'description' => ['nullable', 'string', 'max:5000'],
            'pricing_notes' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
