<?php

namespace App\Modules\Reservation\Infra\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IndexReservationRequest extends FormRequest
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
            'room_id' => ['nullable', 'uuid'],
            'date' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'room_id.uuid' => 'Selecione uma sala válida.',
            'date.date_format' => 'Informe uma data válida.',
        ];
    }
}
