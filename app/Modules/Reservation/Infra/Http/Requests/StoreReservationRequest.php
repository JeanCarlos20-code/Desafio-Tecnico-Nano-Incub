<?php

namespace App\Modules\Reservation\Infra\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreReservationRequest extends FormRequest
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
            'room_id' => ['required', 'integer'],
            'responsible' => ['required', 'string', 'max:255'],
            'title' => ['required', 'string', 'max:255'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'participants' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'room_id.required' => 'Informe a sala.',
            'room_id.integer' => 'Selecione uma sala válida.',
            'responsible.required' => 'Informe o responsável.',
            'title.required' => 'Informe o título da reserva.',
            'starts_at.required' => 'Informe o início.',
            'starts_at.date' => 'Informe um início válido.',
            'ends_at.required' => 'Informe o término.',
            'ends_at.date' => 'Informe um término válido.',
            'ends_at.after' => 'O término deve ser posterior ao início.',
            'participants.required' => 'Informe o número de participantes.',
            'participants.integer' => 'Os participantes devem ser um número inteiro.',
            'participants.min' => 'Informe pelo menos 1 participante.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $payload = [];

        foreach (['responsible', 'title'] as $field) {
            $value = $this->input($field);

            if (is_string($value)) {
                $payload[$field] = trim($value);
            }
        }

        if ($payload !== []) {
            $this->merge($payload);
        }
    }
}
