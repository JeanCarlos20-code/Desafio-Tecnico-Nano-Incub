<?php

namespace Tests\Unit\Room;

use App\Modules\Room\Infra\Http\Requests\UpdateRoomRequest;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class UpdateRoomRequestTest extends TestCase
{
    public function test_it_requires_is_active_and_accepts_only_keep_or_cancel_for_scheduled_meetings_action(): void
    {
        $missingStatus = $this->validationErrors([
            'name' => 'Sala Verde',
            'capacity' => 4,
        ]);
        $this->assertSame(['Informe o status da sala.'], $missingStatus['is_active']);

        $invalidAction = $this->validationErrors([
            'name' => 'Sala Verde',
            'capacity' => 4,
            'is_active' => false,
            'scheduled_meetings_action' => 'drop',
        ]);
        $this->assertSame(
            ['Informe se as reuniões programadas devem ser mantidas ou canceladas.'],
            $invalidAction['scheduled_meetings_action'],
        );

        $keep = $this->validated([
            'name' => 'Sala Verde',
            'capacity' => 4,
            'is_active' => false,
            'scheduled_meetings_action' => 'keep',
        ]);
        $this->assertFalse($keep['is_active']);
        $this->assertSame('keep', $keep['scheduled_meetings_action']);

        $cancel = $this->validated([
            'name' => 'Sala Verde',
            'capacity' => 4,
            'is_active' => true,
            'scheduled_meetings_action' => 'cancel',
        ]);
        $this->assertTrue($cancel['is_active']);
        $this->assertSame('cancel', $cancel['scheduled_meetings_action']);
    }

    public function test_it_trims_name_and_rejects_invalid_capacity_with_the_same_messages_as_store(): void
    {
        $missingName = $this->validationErrors([
            'capacity' => 8,
            'is_active' => true,
        ]);
        $this->assertSame(['Informe o nome da sala.'], $missingName['name']);

        $missingCapacity = $this->validationErrors([
            'name' => 'Sala Azul',
            'is_active' => true,
        ]);
        $this->assertSame(['Informe a capacidade da sala.'], $missingCapacity['capacity']);

        $nonInteger = $this->validationErrors([
            'name' => 'Sala Azul',
            'capacity' => 'doze',
            'is_active' => true,
        ]);
        $this->assertSame(['A capacidade deve ser um número inteiro.'], $nonInteger['capacity']);

        $belowMin = $this->validationErrors([
            'name' => 'Sala Azul',
            'capacity' => 0,
            'is_active' => true,
        ]);
        $this->assertSame(['A capacidade deve ser de pelo menos 1 pessoa.'], $belowMin['capacity']);

        $validated = $this->validated([
            'name' => '  Sala Verde  ',
            'capacity' => 4,
            'is_active' => false,
        ]);

        $this->assertSame('Sala Verde', $validated['name']);
        $this->assertSame(4, $validated['capacity']);
        $this->assertFalse($validated['is_active']);
        $this->assertArrayNotHasKey('scheduled_meetings_action', $validated);
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
    private function makeRequest(array $payload): UpdateRoomRequest
    {
        $request = UpdateRoomRequest::create('/rooms/x', 'PUT', $payload);
        $request->setContainer($this->app);
        $request->setRedirector($this->app->make('redirect'));

        return $request;
    }
}
