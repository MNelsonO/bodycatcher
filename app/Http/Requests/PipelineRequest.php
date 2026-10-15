<?php

namespace App\Http\Requests;

use App\SalesPipelines\Pipeline;
use Illuminate\Foundation\Http\FormRequest;

class PipelineRequest extends FormRequest
{
    public function authorize(): bool
    {
        $pipeline = $this->route('pipeline');

        if ($pipeline instanceof Pipeline) {
            return $this->user()?->can('update', $pipeline) ?? false;
        }

        return $this->user()?->can('create', Pipeline::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
