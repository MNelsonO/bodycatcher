<?php

namespace App\Http\Requests;

use App\Ai\KnowledgeArticle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AnswerKnowledgeGapRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', KnowledgeArticle::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'answer' => ['required', 'string', 'max:4000'],
            'category' => ['required', Rule::in(['fact', 'lesson', 'session'])],
            'title' => ['nullable', 'string', 'max:160'],
        ];
    }
}
