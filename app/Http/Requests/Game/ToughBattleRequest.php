<?php

namespace App\Http\Requests\Game;

use Illuminate\Foundation\Http\FormRequest;

final class ToughBattleRequest extends FormRequest
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
            'locationId' => ['required', 'string'],
        ];
    }
}
