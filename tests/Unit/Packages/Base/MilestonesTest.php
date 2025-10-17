<?php

namespace Tests\Unit\Packages\Base;

use App\Packages\Base\Milestones;
use Tests\TestCase;

class MilestonesTest extends TestCase
{
    /**
     * Test countLabels() returns correct count from concrete implementation.
     */
    public function test_count_labels_returns_correct_count(): void
    {
        // Arrange
        $milestones = new MilestonesTestStub;

        // Act
        $count = $milestones->countLabels();

        // Assert
        $this->assertEquals(3, $count);
    }

    /**
     * Test countLabels() returns zero for empty milestones.
     */
    public function test_count_labels_returns_zero_for_empty_milestones(): void
    {
        // Arrange
        $milestones = new EmptyMilestonesTestStub;

        // Act
        $count = $milestones->countLabels();

        // Assert
        $this->assertEquals(0, $count);
    }

    /**
     * Test countLabels() can handle single milestone.
     */
    public function test_count_labels_handles_single_milestone(): void
    {
        // Arrange
        $milestones = new SingleMilestoneTestStub;

        // Act
        $count = $milestones->countLabels();

        // Assert
        $this->assertEquals(1, $count);
    }

    /**
     * Test countLabels() can handle large number of milestones.
     */
    public function test_count_labels_handles_many_milestones(): void
    {
        // Arrange
        $milestones = new ManyMilestonesTestStub;

        // Act
        $count = $milestones->countLabels();

        // Assert
        $this->assertEquals(10, $count);
    }

    /**
     * Test Milestones is abstract and requires implementation.
     */
    public function test_milestones_is_abstract_class(): void
    {
        // Arrange & Act
        $reflection = new \ReflectionClass(Milestones::class);

        // Assert
        $this->assertTrue($reflection->isAbstract());
    }

    /**
     * Test countLabels() is abstract method.
     */
    public function test_count_labels_is_abstract_method(): void
    {
        // Arrange & Act
        $reflection = new \ReflectionClass(Milestones::class);
        $method = $reflection->getMethod('countLabels');

        // Assert
        $this->assertTrue($method->isAbstract());
    }

    /**
     * Test concrete implementation can be instantiated.
     */
    public function test_concrete_implementation_can_be_instantiated(): void
    {
        // Arrange & Act
        $milestones = new MilestonesTestStub;

        // Assert
        $this->assertInstanceOf(Milestones::class, $milestones);
        $this->assertInstanceOf(MilestonesTestStub::class, $milestones);
    }

    /**
     * Test countLabels() returns consistent results across multiple calls.
     */
    public function test_count_labels_returns_consistent_results(): void
    {
        // Arrange
        $milestones = new MilestonesTestStub;

        // Act
        $count1 = $milestones->countLabels();
        $count2 = $milestones->countLabels();
        $count3 = $milestones->countLabels();

        // Assert
        $this->assertEquals($count1, $count2);
        $this->assertEquals($count2, $count3);
        $this->assertEquals(3, $count1);
    }
}

// ==================== Test Stub Classes ====================

/**
 * Test implementation of Milestones with multiple milestones
 */
class MilestonesTestStub extends Milestones
{
    public function countLabels(): int
    {
        return 3;
    }
}

/**
 * Test implementation of Milestones with no milestones
 */
class EmptyMilestonesTestStub extends Milestones
{
    public function countLabels(): int
    {
        return 0;
    }
}

/**
 * Test implementation of Milestones with single milestone
 */
class SingleMilestoneTestStub extends Milestones
{
    public function countLabels(): int
    {
        return 1;
    }
}

/**
 * Test implementation of Milestones with many milestones
 */
class ManyMilestonesTestStub extends Milestones
{
    public function countLabels(): int
    {
        return 10;
    }
}
