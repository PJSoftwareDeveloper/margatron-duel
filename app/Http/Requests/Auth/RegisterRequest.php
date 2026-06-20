<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

final class RegisterRequest extends FormRequest
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
            'nick' => ['required', 'string', 'min:3', 'max:30', 'unique:users,name'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ];
    }
}
