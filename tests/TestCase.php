<?php

namespace Tests;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\PendingCommand;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $cachedConfig = dirname(__DIR__).'/bootstrap/cache/config.php';

        if (is_file($cachedConfig)) {
            unlink($cachedConfig);
        }

        return parent::createApplication();
    }

    /**
     * @param  string  $command
     * @param  array<string, mixed>  $parameters
     * @return PendingCommand|int
     */
    public function artisan($command, $parameters = [])
    {
        if ($command !== 'migrate:fresh') {
            return parent::artisan($command, $parameters);
        }

        $previous = $this->mockConsoleOutput;
        $this->mockConsoleOutput = false;

        try {
            return $this->callMigrateFresh($parameters);
        } finally {
            $this->mockConsoleOutput = $previous;
        }
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    private function callMigrateFresh(array $parameters): int
    {
        try {
            return parent::artisan('migrate:fresh', $parameters);
        } catch (QueryException $exception) {
            if (! str_contains($exception->getMessage(), 'already exists')) {
                throw $exception;
            }

            Schema::dropAllTables();

            return parent::artisan('migrate:fresh', $parameters);
        }
    }
}
