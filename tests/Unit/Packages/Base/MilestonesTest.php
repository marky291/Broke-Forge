<?php

namespace Tests\Unit\Packages\Base;

use App\Packages\Base\Milestones;
use Tests\TestCase;

class MilestonesTest extends TestCase
{
    public function test_count_labels_returns_correct_count(): void
    {
        $milestones = new ConcreteMilestones;
        $this->assertEquals(10, $milestones->countLabels());
    }

    public function test_different_milestones_implementations_return_different_counts(): void
    {
        $milestones1 = new ConcreteMilestones;
        $milestones2 = new AlternativeMilestones;

        $this->assertEquals(10, $milestones1->countLabels());
        $this->assertEquals(5, $milestones2->countLabels());
    }

    public function test_milestones_is_abstract_class(): void
    {
        $reflection = new \ReflectionClass(Milestones::class);
        $this->assertTrue($reflection->isAbstract());
    }

    public function test_count_labels_is_abstract_method(): void
    {
        $reflection = new \ReflectionClass(Milestones::class);
        $method = $reflection->getMethod('countLabels');
        $this->assertTrue($method->isAbstract());
    }

    public function test_count_labels_return_type_is_int(): void
    {
        $reflection = new \ReflectionClass(Milestones::class);
        $method = $reflection->getMethod('countLabels');
        $returnType = $method->getReturnType();

        $this->assertNotNull($returnType);
        $this->assertEquals('int', $returnType->getName());
    }
}

/**
 * Concrete implementation for testing
 */
class ConcreteMilestones extends Milestones
{
    public const STEP_1 = 'step_1';

    public const STEP_2 = 'step_2';

    public const STEP_3 = 'step_3';

    public const STEP_4 = 'step_4';

    public const STEP_5 = 'step_5';

    public const STEP_6 = 'step_6';

    public const STEP_7 = 'step_7';

    public const STEP_8 = 'step_8';

    public const STEP_9 = 'step_9';

    public const STEP_10 = 'step_10';

    public function countLabels(): int
    {
        return 10;
    }
}

/**
 * Alternative implementation for testing
 */
class AlternativeMilestones extends Milestones
{
    public function countLabels(): int
    {
        return 5;
    }
}
