<?php

namespace App\Modules\Room\Infra\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IndexRoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'in:all,active,inactive'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.in' => 'Informe um status válido.',
            'page.integer' => 'Informe uma página válida.',
            'page.min' => 'Informe uma página válida.',
            'limit.integer' => 'Informe um limite válido.',
            'limit.min' => 'Informe um limite válido.',
            'limit.max' => 'Informe um limite válido.',
        ];
    }
}
