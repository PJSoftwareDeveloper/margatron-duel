<?php

namespace App\Http\Requests\Game;

use Illuminate\Foundation\Http\FormRequest;

final class StageBattleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'locationId' => ['required', 'string'],
            'stage' => ['required', 'integer', 'min:1', 'max:5'],
        ];
    }
}
