<?php

namespace Tests\Unit\Reservation;

use App\Modules\Reservation\Infra\Http\Requests\IndexReservationRequest;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class IndexReservationRequestTest extends TestCase
{
    public function test_it_accepts_status_all_active_cancelled_omits_when_absent_and_rejects_unknown(): void
    {
        foreach (['all', 'active', 'cancelled'] as $status) {
            $validated = $this->validated(['status' => $status]);
            $this->assertSame($status, $validated['status']);
        }

        $omitted = $this->validated([]);
        $this->assertArrayNotHasKey('status', $omitted);

        $this->assertSame(['Informe um status válido.'], $this->validationErrors(['status' => 'weekend'])['status']);
        $this->assertSame(['Informe um status válido.'], $this->validationErrors(['status' => 'completed'])['status']);
    }

    public function test_it_accepts_period_enum_and_complete_ymd_range(): void
    {
        foreach (['all', 'today', 'tomorrow', 'week'] as $period) {
            $validated = $this->validated(['period' => $period]);
            $this->assertSame($period, $validated['period']);
            $this->assertArrayNotHasKey('status', $validated);
        }

        $validated = $this->validated([
            'room_id' => 1,
            'period' => 'today',
            'starts_on' => '2026-09-22',
            'ends_on' => '2026-09-23',
            'status' => 'cancelled',
        ]);

        $this->assertSame(1, $validated['room_id']);
        $this->assertSame('today', $validated['period']);
        $this->assertSame('2026-09-22', $validated['starts_on']);
        $this->assertSame('2026-09-23', $validated['ends_on']);
        $this->assertSame('cancelled', $validated['status']);
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

    public function test_it_accepts_omitted_page_and_limit_and_valid_page_and_limit_bounds(): void
    {
        $omitted = $this->validated([]);
        $this->assertArrayNotHasKey('page', $omitted);
        $this->assertArrayNotHasKey('limit', $omitted);

        foreach ([1, 2] as $page) {
            foreach ([1, 20, 100] as $limit) {
                $validated = $this->validated(['page' => $page, 'limit' => $limit]);
                $this->assertSame($page, $validated['page']);
                $this->assertSame($limit, $validated['limit']);
            }
        }
    }

    public function test_it_rejects_page_below_one_or_non_integer_with_portuguese_message(): void
    {
        foreach ([0, 'abc'] as $page) {
            $this->assertSame(['Informe uma página válida.'], $this->validationErrors(['page' => $page])['page']);
        }
    }

    public function test_it_rejects_limit_outside_one_to_one_hundred_or_non_integer_with_portuguese_message(): void
    {
        foreach ([0, 101, 'abc'] as $limit) {
            $this->assertSame(['Informe um limite válido.'], $this->validationErrors(['limit' => $limit])['limit']);
        }
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
