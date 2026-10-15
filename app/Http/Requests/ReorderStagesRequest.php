<?php

namespace App\Http\Requests;

use App\SalesPipelines\Pipeline;
use Illuminate\Foundation\Http\FormRequest;

class ReorderStagesRequest extends FormRequest
{
    public function authorize(): bool
    {
        $pipeline = $this->route('pipeline');

        return $pipeline instanceof Pipeline && ($this->user()?->can('update', $pipeline) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'stage_ids' => ['required', 'array', 'min:1'],
            'stage_ids.*' => ['integer', 'distinct'],
        ];
    }
}
