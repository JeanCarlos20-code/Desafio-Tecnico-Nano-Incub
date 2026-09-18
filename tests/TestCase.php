<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

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
}
