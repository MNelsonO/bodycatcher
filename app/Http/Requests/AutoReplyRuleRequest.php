<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AutoReplyRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isOwner() === true;
    }

    protected function prepareForValidation(): void
    {
        $stage = $this->input('pipeline_stage_id');

        if ($stage === '' || $stage === 'none') {
            $this->merge(['pipeline_stage_id' => null]);
        }

        if ($this->input('match_type') === 'any') {
            $this->merge(['match_value' => null]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'is_active' => ['required', 'boolean'],
            'priority' => ['required', 'integer', 'min:1', 'max:9999'],
            'match_type' => ['required', Rule::in(['contains', 'exact', 'any'])],
            'match_value' => ['nullable', 'string', 'max:255', 'required_unless:match_type,any'],
            'pipeline_stage_id' => ['nullable', 'integer', 'exists:pipeline_stages,id'],
            'reply_mode' => ['required', Rule::in(['template', 'llm'])],
            'template_body' => ['nullable', 'string', 'max:2000', 'required_if:reply_mode,template'],
            'require_open_window' => ['required', 'boolean'],
        ];
    }
}
