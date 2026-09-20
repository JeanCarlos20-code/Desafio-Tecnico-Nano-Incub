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
            'room_id' => ['nullable', 'integer'],
            'period' => ['sometimes', 'in:all,today,tomorrow,week'],
            'starts_on' => ['nullable', 'date_format:Y-m-d', 'required_with:ends_on'],
            'ends_on' => ['nullable', 'date_format:Y-m-d', 'required_with:starts_on', 'after_or_equal:starts_on'],
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
            'room_id.integer' => 'Selecione uma sala válida.',
            'period.in' => 'Informe um período válido.',
            'starts_on.date_format' => 'Informe uma data inicial válida.',
            'starts_on.required_with' => 'Informe a data inicial e a data final.',
            'ends_on.date_format' => 'Informe uma data final válida.',
            'ends_on.required_with' => 'Informe a data inicial e a data final.',
            'ends_on.after_or_equal' => 'A data final deve ser igual ou posterior à data inicial.',
            'page.integer' => 'Informe uma página válida.',
            'page.min' => 'Informe uma página válida.',
            'limit.integer' => 'Informe um limite válido.',
            'limit.min' => 'Informe um limite válido.',
            'limit.max' => 'Informe um limite válido.',
        ];
    }
}
