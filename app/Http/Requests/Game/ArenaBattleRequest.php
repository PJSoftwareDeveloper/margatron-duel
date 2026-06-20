<?php

namespace App\Http\Requests\Game;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ArenaBattleRequest extends FormRequest
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
            'difficulty' => ['required', Rule::in(['easy', 'medium', 'hard'])],
        ];
    }
}
