<?php

namespace Tests\Unit\Reservation;

use App\Modules\Reservation\Infra\Http\Requests\StoreReservationRequest;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class StoreReservationRequestTest extends TestCase
{
    public function test_it_rejects_missing_or_malformed_fields_with_the_spec_portuguese_messages(): void
    {
        $missing = $this->validationErrors([]);

        $this->assertSame(['Informe a sala.'], $missing['room_id']);
        $this->assertSame(['Informe o responsável.'], $missing['responsible']);
        $this->assertSame(['Informe o título da reserva.'], $missing['title']);
        $this->assertSame(['Informe o início.'], $missing['starts_at']);
        $this->assertSame(['Informe o término.'], $missing['ends_at']);
        $this->assertSame(['Informe o número de participantes.'], $missing['participants']);

        $this->assertSame(['Selecione uma sala válida.'], $this->validationErrors($this->validPayload([
            'room_id' => 'not-a-uuid',
        ]))['room_id']);
        $this->assertSame(['Selecione uma sala válida.'], $this->validationErrors($this->validPayload([
            'room_id' => '018f2b5c-6a7f-7b12-9d6f-2f8a4e0c9c11',
        ]))['room_id']);
        $this->assertSame(['Informe um início válido.'], $this->validationErrors($this->validPayload([
            'starts_at' => 'amanha',
        ]))['starts_at']);
        $this->assertContains('Informe um término válido.', $this->validationErrors($this->validPayload([
            'ends_at' => 'depois',
        ]))['ends_at']);
        $this->assertSame(['O término deve ser posterior ao início.'], $this->validationErrors($this->validPayload([
            'starts_at' => '2026-09-21 10:00:00',
            'ends_at' => '2026-09-21 10:00:00',
        ]))['ends_at']);
        $this->assertSame(['Os participantes devem ser um número inteiro.'], $this->validationErrors($this->validPayload([
            'participants' => 'dois',
        ]))['participants']);
        $this->assertSame(['Informe pelo menos 1 participante.'], $this->validationErrors($this->validPayload([
            'participants' => 0,
        ]))['participants']);
    }

    public function test_it_accepts_an_integer_room_id_in_the_isolated_input_contract(): void
    {
        $validated = $this->validated($this->validPayload(['room_id' => 1]));

        $this->assertSame(1, $validated['room_id']);
    }

    public function test_it_trims_responsible_and_title_and_excludes_unknown_keys(): void
    {
        $validated = $this->validated($this->validPayload([
            'responsible' => '  Ada Lovelace  ',
            'title' => '  Daily  ',
            'extra' => 'ignore',
        ]));

        $this->assertSame('Ada Lovelace', $validated['responsible']);
        $this->assertSame('Daily', $validated['title']);
        $this->assertSame(1, $validated['room_id']);
        $this->assertSame(
            ['room_id', 'responsible', 'title', 'starts_at', 'ends_at', 'participants'],
            array_keys($validated),
        );
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'room_id' => 1,
            'responsible' => 'Ada Lovelace',
            'title' => 'Daily',
            'starts_at' => '2026-09-21 10:00:00',
            'ends_at' => '2026-09-21 10:30:00',
            'participants' => 2,
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function validated(array $payload): array
    {
        $request = $this->makeRequest($payload);
        $request->validateResolved();

        return $request->validated();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, list<string>>
     */
    private function validationErrors(array $payload): array
    {
        try {
            $this->validated($payload);
            $this->fail('Expected validation to fail');
        } catch (ValidationException $exception) {
            return $exception->errors();
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function makeRequest(array $payload): StoreReservationRequest
    {
        $request = StoreReservationRequest::create('/reservations', 'POST', $payload);
        $request->setContainer($this->app);
        $request->setRedirector($this->app->make('redirect'));

        return $request;
    }
}
