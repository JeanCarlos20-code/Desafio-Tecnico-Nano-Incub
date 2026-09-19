<?php

namespace Tests\Unit\Reservation;

use App\Modules\Reservation\Infra\Http\Requests\IndexReservationRequest;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class IndexReservationRequestTest extends TestCase
{
    public function test_it_rejects_malformed_date_or_room_id(): void
    {
        $this->assertSame(['Informe uma data válida.'], $this->validationErrors([
            'date' => '21/09/2026',
        ])['date']);
        $this->assertSame(['Selecione uma sala válida.'], $this->validationErrors([
            'room_id' => 'not-a-uuid',
        ])['room_id']);
    }

    public function test_it_accepts_valid_optional_filters(): void
    {
        $validated = $this->validated([
            'room_id' => '018f2b5c-6a7f-7b12-9d6f-2f8a4e0c9c11',
            'date' => '2026-09-21',
        ]);

        $this->assertSame('018f2b5c-6a7f-7b12-9d6f-2f8a4e0c9c11', $validated['room_id']);
        $this->assertSame('2026-09-21', $validated['date']);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function validated(array $payload): array
    {
        $request = IndexReservationRequest::create('/reservations', 'GET', $payload);
        $request->setContainer($this->app);
        $request->setRedirector($this->app->make('redirect'));
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
}
