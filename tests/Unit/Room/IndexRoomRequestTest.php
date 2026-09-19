<?php

namespace Tests\Unit\Room;

use App\Modules\Room\Infra\Http\Requests\IndexRoomRequest;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class IndexRoomRequestTest extends TestCase
{
    public function test_it_accepts_all_active_inactive_and_rejects_unknown_status(): void
    {
        foreach (['all', 'active', 'inactive'] as $status) {
            $request = $this->makeRequest(['status' => $status]);
            $request->validateResolved();
            $this->assertSame($status, $request->validated('status'));
        }

        $omitted = $this->makeRequest([]);
        $omitted->validateResolved();
        $this->assertArrayNotHasKey('status', $omitted->validated());

        try {
            $this->makeRequest(['status' => 'archived'])->validateResolved();
            $this->fail('Expected validation to fail');
        } catch (ValidationException $exception) {
            $this->assertSame(['Informe um status válido.'], $exception->errors()['status']);
        }
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function makeRequest(array $query): IndexRoomRequest
    {
        $request = IndexRoomRequest::create('/rooms', 'GET', $query);
        $request->setContainer($this->app);
        $request->setRedirector($this->app->make('redirect'));

        return $request;
    }
}
