<?php

namespace Tests\Unit\Packages\Contracts;

use App\Packages\Contracts\Installer;
use Tests\TestCase;

class InstallerTest extends TestCase
{
    public function test_installer_is_interface(): void
    {
        $reflection = new \ReflectionClass(Installer::class);
        $this->assertTrue($reflection->isInterface());
    }

    public function test_installer_allows_flexible_execute_signatures(): void
    {
        // The Installer interface allows flexible execute method signatures
        // This test verifies the interface doesn't enforce a specific signature
        $reflection = new \ReflectionClass(Installer::class);
        $this->assertFalse($reflection->hasMethod('execute'));
        $this->assertTrue($reflection->isInterface());
    }

    public function test_installer_can_be_implemented(): void
    {
        $installer = new ConcreteInstaller;
        $this->assertInstanceOf(Installer::class, $installer);
    }

    public function test_concrete_implementation_can_execute(): void
    {
        $installer = new ConcreteInstaller;

        $this->assertFalse($installer->wasExecuted());
        $installer->execute();
        $this->assertTrue($installer->wasExecuted());
    }
}

/**
 * Concrete implementation for testing
 */
class ConcreteInstaller implements Installer
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
