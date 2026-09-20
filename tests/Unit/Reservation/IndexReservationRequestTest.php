<?php

namespace Tests\Unit\Reservation;

use App\Modules\Reservation\Infra\Http\Requests\IndexReservationRequest;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class IndexReservationRequestTest extends TestCase
{
    public function test_it_accepts_period_enum_and_complete_ymd_range(): void
    {
        foreach (['all', 'today', 'tomorrow', 'week'] as $period) {
            $validated = $this->validated(['period' => $period]);
            $this->assertSame($period, $validated['period']);
        }

        $validated = $this->validated([
            'room_id' => '018f2b5c-6a7f-7b12-9d6f-2f8a4e0c9c11',
            'period' => 'today',
            'starts_on' => '2026-09-22',
            'ends_on' => '2026-09-23',
        ]);

        $this->assertSame('018f2b5c-6a7f-7b12-9d6f-2f8a4e0c9c11', $validated['room_id']);
        $this->assertSame('today', $validated['period']);
        $this->assertSame('2026-09-22', $validated['starts_on']);
        $this->assertSame('2026-09-23', $validated['ends_on']);
    }

    public function test_it_rejects_unknown_period_one_sided_inverted_and_malformed_dates(): void
    {
        $this->assertArrayHasKey('period', $this->validationErrors(['period' => 'weekend']));
        $this->assertArrayHasKey('ends_on', $this->validationErrors(['starts_on' => '2026-09-21']));
        $this->assertArrayHasKey('starts_on', $this->validationErrors(['ends_on' => '2026-09-21']));
        $this->assertArrayHasKey('ends_on', $this->validationErrors([
            'starts_on' => '2026-09-23',
            'ends_on' => '2026-09-22',
        ]));
        $this->assertSame(['Informe uma data inicial válida.'], $this->validationErrors([
            'starts_on' => '21/09/2026',
            'ends_on' => '2026-09-22',
        ])['starts_on']);
        $this->assertContains('Informe uma data final válida.', $this->validationErrors([
            'starts_on' => '2026-09-21',
            'ends_on' => '21/09/2026',
        ])['ends_on']);
        $this->assertSame(['Selecione uma sala válida.'], $this->validationErrors([
            'room_id' => 'not-a-uuid',
        ])['room_id']);
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
