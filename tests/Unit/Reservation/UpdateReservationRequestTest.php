<?php

namespace Tests\Unit\Reservation;

use App\Modules\Reservation\Infra\Http\Requests\UpdateReservationRequest;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class UpdateReservationRequestTest extends TestCase
{
    public function test_it_requires_title_and_responsible_with_store_portuguese_messages_and_trims_both(): void
    {
        $missing = $this->validationErrors([]);

        $this->assertSame(['Informe o título da reserva.'], $missing['title']);
        $this->assertSame(['Informe o responsável.'], $missing['responsible']);

        $this->assertSame(['Informe o título da reserva.'], $this->validationErrors($this->validPayload([
            'title' => '   ',
        ]))['title']);
        $this->assertSame(['Informe o responsável.'], $this->validationErrors($this->validPayload([
            'responsible' => '   ',
        ]))['responsible']);

        $this->assertArrayHasKey('title', $this->validationErrors($this->validPayload([
            'title' => str_repeat('a', 256),
        ])));
        $this->assertArrayHasKey('responsible', $this->validationErrors($this->validPayload([
            'responsible' => str_repeat('b', 256),
        ])));

        $both = $this->validationErrors([
            'starts_at' => '2026-09-22 11:00:00',
        ]);
        $this->assertArrayHasKey('title', $both);
        $this->assertArrayHasKey('responsible', $both);
        $this->assertArrayHasKey('starts_at', $both);

        $validated = $this->validated($this->validPayload([
            'title' => '  Daily revisada  ',
            'responsible' => '  Ada Lovelace  ',
            'extra' => 'ignore',
        ]));

        $this->assertSame('Daily revisada', $validated['title']);
        $this->assertSame('Ada Lovelace', $validated['responsible']);
        $this->assertSame(['title', 'responsible'], array_keys($validated));
    }

    public function test_it_rejects_a_payload_that_includes_occupancy_or_cancelled_keys(): void
    {
        $errors = $this->validationErrors($this->validPayload([
            'starts_at' => '2026-09-22 11:00:00',
            'ends_at' => '2026-09-22 11:30:00',
            'room_id' => 99,
            'participants' => 8,
            'cancelled_at' => '2026-09-21 09:00:00',
        ]));

        $this->assertArrayHasKey('starts_at', $errors);
        $this->assertArrayHasKey('ends_at', $errors);
        $this->assertArrayHasKey('room_id', $errors);
        $this->assertArrayHasKey('participants', $errors);
        $this->assertArrayHasKey('cancelled_at', $errors);
        $this->assertSame(['O início da reserva não pode ser alterado.'], $errors['starts_at']);
        $this->assertSame(['O término da reserva não pode ser alterado.'], $errors['ends_at']);
        $this->assertSame(['A sala da reserva não pode ser alterada.'], $errors['room_id']);
        $this->assertSame(['Os participantes não podem ser alterados.'], $errors['participants']);
        $this->assertSame(['O cancelamento da reserva não pode ser alterado neste formulário.'], $errors['cancelled_at']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Daily',
            'responsible' => 'Ada Lovelace',
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
    private function makeRequest(array $payload): UpdateReservationRequest
    {
        $request = UpdateReservationRequest::create('/reservations/1', 'PUT', $payload);
        $request->setContainer($this->app);
        $request->setRedirector($this->app->make('redirect'));

        return $request;
    }
}
