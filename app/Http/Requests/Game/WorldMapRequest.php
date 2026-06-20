<?php

namespace App\Http\Requests\Game;

use Illuminate\Foundation\Http\FormRequest;

final class WorldMapRequest extends FormRequest
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
            'mapId' => ['required', 'integer', 'min:1'],
        ];
    }
}
