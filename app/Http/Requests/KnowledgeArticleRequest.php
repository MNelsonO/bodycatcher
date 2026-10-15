<?php

namespace App\Http\Requests;

use App\Ai\KnowledgeArticle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class KnowledgeArticleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $article = $this->route('article');

        if ($article instanceof KnowledgeArticle) {
            return $this->user()?->can('update', $article) ?? false;
        }

        return $this->user()?->can('create', KnowledgeArticle::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $product = $this->input('product_id');

        if ($product === '' || $product === 'none') {
            $this->merge(['product_id' => null]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:160'],
            'body' => ['required', 'string', 'max:8000'],
            'category' => ['nullable', Rule::in(['fact', 'lesson', 'session'])],
            'product_id' => ['nullable', 'integer', Rule::exists('products', 'id')],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
