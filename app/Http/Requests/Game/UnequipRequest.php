<?php

namespace App\Http\Requests\Game;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UnequipRequest extends FormRequest
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
            'slot' => ['required', Rule::in(['weapon', 'armor', 'accessory'])],
        ];
    }
}
