<?php

namespace App\Http\Requests\OndernemerBeheer;

use Illuminate\Foundation\Http\FormRequest;

/** Collega uitnodigen voor een bedrijf. Rechten controleert de controller. */
class UitnodigingRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'role' => ['required', 'in:eigenaar,lid'],
        ];
    }

    public function attributes(): array
    {
        return ['name' => 'naam', 'email' => 'e-mailadres', 'role' => 'rol'];
    }
}
