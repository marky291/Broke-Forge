<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Concerns\MocksSshConnections;

abstract class TestCase extends BaseTestCase
{
    use MocksSshConnections;

    protected function setUp(): void
    {
        parent::setUp();

        // Always mock SSH connections to prevent actual remote connections during tests
        $this->mockSuccessfulSshConnections(['mock ssh output']);
    }
}
