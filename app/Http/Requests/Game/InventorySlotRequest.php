<?php

namespace App\Http\Requests\Game;

use Illuminate\Foundation\Http\FormRequest;

final class InventorySlotRequest extends FormRequest
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
            'index' => ['required', 'integer', 'min:0', 'max:14'],
        ];
    }
}
