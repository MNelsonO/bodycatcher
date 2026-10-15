<?php

namespace App\Http\Requests;

use App\FollowUps\AutomationRule;
use App\FollowUps\FollowUpAction;
use App\FollowUps\FollowUpTrigger;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AutomationRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $rule = $this->route('rule');

        if ($rule instanceof AutomationRule) {
            return $this->user()?->can('update', $rule) ?? false;
        }

        return $this->user()?->can('create', AutomationRule::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $product = $this->input('product_id');

        if ($product === '' || $product === 'none') {
            $this->merge(['product_id' => null]);
        }

        foreach (['delay_hours', 'local_time'] as $field) {
            if ($this->input($field) === '') {
                $this->merge([$field => null]);
            }
        }

        $time = $this->input('local_time');

        if (is_string($time) && preg_match('/^\d{2}:\d{2}/', $time) === 1) {
            $this->merge(['local_time' => substr($time, 0, 5)]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $trigger = $this->string('trigger')->toString();

        return [
            'name' => ['required', 'string', 'max:160'],
            'trigger' => ['required', Rule::enum(FollowUpTrigger::class)],
            'action' => ['required', Rule::enum(FollowUpAction::class)],
            'delay_hours' => [
                'nullable',
                'integer',
                'min:1',
                'max:8760',
                Rule::requiredIf($trigger === FollowUpTrigger::NoResponse->value),
            ],
            'local_time' => [
                'nullable',
                'date_format:H:i',
                Rule::requiredIf($trigger === FollowUpTrigger::FollowUpDue->value),
            ],
            'body' => ['required', 'string', 'max:4000'],
            'use_conversation_memory' => ['sometimes', 'boolean'],
            'product_id' => ['nullable', 'integer', Rule::exists('products', 'id')],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
