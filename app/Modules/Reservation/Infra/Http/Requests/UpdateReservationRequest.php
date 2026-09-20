<?php

namespace App\Modules\Reservation\Infra\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateReservationRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
            'responsible' => ['required', 'string', 'max:255'],
            'starts_at' => ['prohibited'],
            'ends_at' => ['prohibited'],
            'room_id' => ['prohibited'],
            'participants' => ['prohibited'],
            'cancelled_at' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Informe o título da reserva.',
            'responsible.required' => 'Informe o responsável.',
            'starts_at.prohibited' => 'O início da reserva não pode ser alterado.',
            'ends_at.prohibited' => 'O término da reserva não pode ser alterado.',
            'room_id.prohibited' => 'A sala da reserva não pode ser alterada.',
            'participants.prohibited' => 'Os participantes não podem ser alterados.',
            'cancelled_at.prohibited' => 'O cancelamento da reserva não pode ser alterado neste formulário.',
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
