<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class CambiarPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'password_actual' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', 'different:password_actual', Password::defaults()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'password.different' => 'La nueva contraseña debe ser distinta de la actual (o de la temporal que le entregaron).',
        ];
    }
}
