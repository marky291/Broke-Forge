<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Mockery;

abstract class TestCase extends BaseTestCase
{
    protected function tearDown(): void
    {
        // Close Mockery to prevent memory leaks from mock objects
        if (class_exists(Mockery::class)) {
            Mockery::close();
        }

        parent::tearDown();
    }
}
