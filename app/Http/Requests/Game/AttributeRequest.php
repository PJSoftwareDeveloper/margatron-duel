<?php

namespace App\Http\Requests\Game;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class AttributeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'attribute' => ['required', Rule::in(['vitality', 'strength', 'luck'])],
        ];
    }
}
