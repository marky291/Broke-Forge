<?php

namespace Tests\Unit\Packages\Contracts;

use App\Packages\Contracts\Remover;
use Tests\TestCase;

class RemoverTest extends TestCase
{
    public function test_remover_is_interface(): void
    {
        $reflection = new \ReflectionClass(Remover::class);
        $this->assertTrue($reflection->isInterface());
    }

    public function test_remover_allows_flexible_execute_signatures(): void
    {
        $reflection = new \ReflectionClass(Remover::class);

        // Interface should be empty to allow flexible execute method signatures
        $methods = $reflection->getMethods();
        $this->assertCount(0, $methods, 'Remover interface should be empty to allow flexible execute signatures');
    }

    public function test_remover_can_be_implemented(): void
    {
        $remover = new ConcreteRemover;
        $this->assertInstanceOf(Remover::class, $remover);
    }

    public function test_concrete_implementation_can_execute(): void
    {
        $remover = new ConcreteRemover;

        $this->assertFalse($remover->wasExecuted());
        $remover->execute();
        $this->assertTrue($remover->wasExecuted());
    }
}

/**
 * Concrete implementation for testing
 */
class ConcreteRemover implements Remover
{
    private bool $executed = false;

    public function execute(): void
    {
        $this->executed = true;
    }

    public function wasExecuted(): bool
    {
        return $this->executed;
    }
}
