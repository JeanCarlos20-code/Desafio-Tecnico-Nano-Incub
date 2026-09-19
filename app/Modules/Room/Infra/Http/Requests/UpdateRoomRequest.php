<?php

namespace App\Modules\Room\Infra\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRoomRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'capacity' => ['required', 'integer', 'min:1'],
            'is_active' => ['required', 'boolean'],
            'scheduled_meetings_action' => ['sometimes', 'nullable', 'in:keep,cancel'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Informe o nome da sala.',
            'capacity.required' => 'Informe a capacidade da sala.',
            'capacity.integer' => 'A capacidade deve ser um número inteiro.',
            'capacity.min' => 'A capacidade deve ser de pelo menos 1 pessoa.',
            'is_active.required' => 'Informe o status da sala.',
            'is_active.boolean' => 'Informe um status válido.',
            'scheduled_meetings_action.in' => 'Informe se as reuniões programadas devem ser mantidas ou canceladas.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $name = $this->input('name');

        if (! is_string($name)) {
            return;
        }

        $this->merge([
            'name' => trim($name),
        ]);
    }
}
