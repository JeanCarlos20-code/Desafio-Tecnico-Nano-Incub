<?php

namespace Tests\Unit\Room;

use App\Modules\Room\Infra\Http\Requests\UpdateRoomRequest;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class UpdateRoomRequestTest extends TestCase
{
    public function test_it_accepts_name_and_capacity_without_is_active(): void
    {
        $validated = $this->validated([
            'name' => 'Sala Verde',
            'capacity' => 4,
        ]);

        $this->assertSame('Sala Verde', $validated['name']);
        $this->assertSame(4, $validated['capacity']);
        $this->assertArrayNotHasKey('is_active', $validated);
    }

    public function test_it_trims_name_and_rejects_invalid_capacity_with_the_same_messages_as_store(): void
    {
        $missingName = $this->validationErrors([
            'capacity' => 8,
        ]);
        $this->assertSame(['Informe o nome da sala.'], $missingName['name']);

        $missingCapacity = $this->validationErrors([
            'name' => 'Sala Azul',
        ]);
        $this->assertSame(['Informe a capacidade da sala.'], $missingCapacity['capacity']);

        $nonInteger = $this->validationErrors([
            'name' => 'Sala Azul',
            'capacity' => 'doze',
        ]);
        $this->assertSame(['A capacidade deve ser um número inteiro.'], $nonInteger['capacity']);

        $belowMin = $this->validationErrors([
            'name' => 'Sala Azul',
            'capacity' => 0,
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
