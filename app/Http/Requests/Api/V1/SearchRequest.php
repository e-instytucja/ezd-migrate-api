<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class SearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type'   => ['required', 'string', 'in:case,document,registry,shipment'],
            'q'      => ['sometimes', 'nullable', 'string', 'max:255'],
            'limit'  => ['sometimes', 'integer', 'min:1', 'max:100'],
            'offset' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.required' => 'Parametr "type" jest wymagany.',
            'type.in'       => 'Parametr "type" musi być jednym z: case, document, registry, shipment.',
            'limit.max'     => 'Parametr "limit" nie może przekraczać 100.',
        ];
    }
}
