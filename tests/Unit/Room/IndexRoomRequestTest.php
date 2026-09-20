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
        $this->assertArrayNotHasKey('page', $omitted->validated());
        $this->assertArrayNotHasKey('limit', $omitted->validated());

        try {
            $this->makeRequest(['status' => 'archived'])->validateResolved();
            $this->fail('Expected validation to fail');
        } catch (ValidationException $exception) {
            $this->assertSame(['Informe um status válido.'], $exception->errors()['status']);
        }
    }

    public function test_it_accepts_omitted_page_and_limit_and_valid_page_and_limit_bounds(): void
    {
        $omitted = $this->makeRequest([]);
        $omitted->validateResolved();
        $this->assertArrayNotHasKey('page', $omitted->validated());
        $this->assertArrayNotHasKey('limit', $omitted->validated());

        foreach ([1, 2] as $page) {
            foreach ([1, 20, 100] as $limit) {
                $request = $this->makeRequest(['page' => $page, 'limit' => $limit]);
                $request->validateResolved();
                $this->assertSame($page, $request->validated('page'));
                $this->assertSame($limit, $request->validated('limit'));
            }
        }
    }

    public function test_it_rejects_page_below_one_or_non_integer_with_portuguese_message(): void
    {
        foreach ([0, 'abc'] as $page) {
            try {
                $this->makeRequest(['page' => $page])->validateResolved();
                $this->fail('Expected validation to fail');
            } catch (ValidationException $exception) {
                $this->assertSame(['Informe uma página válida.'], $exception->errors()['page']);
            }
        }
    }

    public function test_it_rejects_limit_outside_one_to_one_hundred_or_non_integer_with_portuguese_message(): void
    {
        foreach ([0, 101, 'abc'] as $limit) {
            try {
                $this->makeRequest(['limit' => $limit])->validateResolved();
                $this->fail('Expected validation to fail');
            } catch (ValidationException $exception) {
                $this->assertSame(['Informe um limite válido.'], $exception->errors()['limit']);
            }
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
