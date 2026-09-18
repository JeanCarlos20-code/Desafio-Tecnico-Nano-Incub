<?php

namespace Tests\Unit\Room;

use App\Modules\Room\Infra\Http\Requests\StoreRoomRequest;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class StoreRoomRequestTest extends TestCase
{
    public function test_it_rejects_missing_name_with_informe_o_nome_da_sala(): void
    {
        $errors = $this->validationErrors([
            'capacity' => 8,
        ]);

        $this->assertSame(['Informe o nome da sala.'], $errors['name']);
    }

    public function test_it_rejects_missing_capacity(): void
    {
        $errors = $this->validationErrors([
            'name' => 'Sala Azul',
        ]);

        $this->assertSame(['Informe a capacidade da sala.'], $errors['capacity']);
    }

    public function test_it_rejects_non_integer_capacity(): void
    {
        $errors = $this->validationErrors([
            'name' => 'Sala Azul',
            'capacity' => 'doze',
        ]);

        $this->assertSame(['Informe a capacidade da sala.'], $errors['capacity']);
    }

    public function test_it_rejects_capacity_less_than_one(): void
    {
        $errors = $this->validationErrors([
            'name' => 'Sala Azul',
            'capacity' => 0,
        ]);

        $this->assertSame(['A capacidade deve ser no mínimo 1.'], $errors['capacity']);
    }

    public function test_it_trims_name(): void
    {
        $validated = $this->validated([
            'name' => '  Sala Azul  ',
            'capacity' => 8,
        ]);

        $this->assertSame('Sala Azul', $validated['name']);
        $this->assertSame(8, $validated['capacity']);
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
    private function makeRequest(array $payload): StoreRoomRequest
    {
        $request = StoreRoomRequest::create('/rooms', 'POST', $payload);
        $request->setContainer($this->app);
        $request->setRedirector($this->app->make('redirect'));

        return $request;
    }
}
