<?php

namespace App\Http\Requests\OndernemerBeheer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/** Vestiging met bezoektijden per weekdag. Rechten controleert de controller. */
class VestigingRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:190'],
            'city' => ['required', 'string', 'max:120'],
            'experience' => ['nullable', 'string', 'max:2000'],
            'windows' => ['array', 'max:50'],
            'windows.*.weekday' => ['required', 'integer', 'between:1,7'],
            'windows.*.starts_at' => ['required', 'date_format:H:i'],
            'windows.*.ends_at' => ['required', 'date_format:H:i'],
        ];
    }

    public function attributes(): array
    {
        return ['name' => 'naam', 'address' => 'adres', 'city' => 'plaats', 'experience' => 'wat de creator mag ervaren'];
    }

    public function after(): array
    {
        return [function (Validator $v) {
            $perDag = [];
            foreach ($this->input('windows', []) as $i => $w) {
                if (! is_array($w) || ! isset($w['starts_at'], $w['ends_at'], $w['weekday'])) {
                    continue;
                }
                if ($w['ends_at'] <= $w['starts_at']) {
                    $v->errors()->add("windows.{$i}.ends_at", 'De eindtijd moet na de begintijd liggen.');
                }
                foreach ($perDag[$w['weekday']] ?? [] as [$van, $tot]) {
                    if ($w['starts_at'] < $tot && $van < $w['ends_at']) {
                        $v->errors()->add("windows.{$i}.starts_at", 'Deze tijden overlappen met een ander tijdvak op dezelfde dag.');
                    }
                }
                $perDag[$w['weekday']][] = [$w['starts_at'], $w['ends_at']];
            }
        }];
    }
}
