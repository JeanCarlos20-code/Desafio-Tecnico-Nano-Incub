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

    public function test_it_rejects_missing_capacity_with_informe_a_capacidade_da_sala(): void
    {
        $errors = $this->validationErrors([
            'name' => 'Sala Azul',
        ]);

        $this->assertSame(['Informe a capacidade da sala.'], $errors['capacity']);
    }

    public function test_it_rejects_non_integer_capacity_with_a_capacidade_deve_ser_um_numero_inteiro(): void
    {
        $errors = $this->validationErrors([
            'name' => 'Sala Azul',
            'capacity' => 'doze',
        ]);

        $this->assertSame(['A capacidade deve ser um número inteiro.'], $errors['capacity']);
    }

    public function test_it_rejects_decimal_capacity_as_non_integer(): void
    {
        $errors = $this->validationErrors([
            'name' => 'Sala Azul',
            'capacity' => 1.5,
        ]);

        $this->assertSame(['A capacidade deve ser um número inteiro.'], $errors['capacity']);
    }

    public function test_it_rejects_capacity_less_than_one_with_a_capacidade_deve_ser_de_pelo_menos_1_pessoa(): void
    {
        $errors = $this->validationErrors([
            'name' => 'Sala Azul',
            'capacity' => 0,
        ]);

        $this->assertSame(['A capacidade deve ser de pelo menos 1 pessoa.'], $errors['capacity']);
    }

    public function test_it_trims_name_and_excludes_is_active_from_validated_data(): void
    {
        $validated = $this->validated([
            'name' => '  Sala Azul  ',
            'capacity' => 8,
            'is_active' => false,
        ]);

        $this->assertSame('Sala Azul', $validated['name']);
        $this->assertSame(8, $validated['capacity']);
        $this->assertSame(['name', 'capacity'], array_keys($validated));
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
