<?php

namespace App\Http\Requests;

use App\SalesPipelines\Pipeline;
use App\SalesPipelines\StageKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StageRequest extends FormRequest
{
    public function authorize(): bool
    {
        $pipeline = $this->route('pipeline');

        return $pipeline instanceof Pipeline && ($this->user()?->can('update', $pipeline) ?? false);
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('default_follow_up_delay_hours') === '') {
            $this->merge(['default_follow_up_delay_hours' => null]);
        }

        if ($this->input('purpose') === '') {
            $this->merge(['purpose' => null]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'purpose' => ['nullable', 'string', 'max:255'],
            'kind' => ['required', Rule::enum(StageKind::class)],
            'default_follow_up_delay_hours' => ['nullable', 'integer', 'min:0', 'max:8760'],
        ];
    }
}
