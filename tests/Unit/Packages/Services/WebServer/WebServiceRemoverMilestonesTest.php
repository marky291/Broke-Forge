<?php

namespace Tests\Unit\Packages\Services\WebServer;

use App\Packages\Services\WebServer\WebServiceRemoverMilestones;
use PHPUnit\Framework\TestCase;

class WebServiceRemoverMilestonesTest extends TestCase
{
    private WebServiceRemoverMilestones $milestones;

    protected function setUp(): void
    {
        parent::setUp();
        $this->milestones = new WebServiceRemoverMilestones;
    }

    public function test_extends_milestones_base_class(): void
    {
        $this->assertInstanceOf(\App\Packages\Base\Milestones::class, $this->milestones);
    }

    public function test_count_labels_returns_correct_number(): void
    {
        $this->assertEquals(5, $this->milestones->countLabels());
    }

    public function test_count_matches_actual_constants(): void
    {
        $reflection = new \ReflectionClass(WebServiceRemoverMilestones::class);
        $constants = $reflection->getConstants();

        // Should have 5 milestone constants + 1 LABELS array = 6 total
        $this->assertCount(6, $constants);
        $this->assertEquals(5, $this->milestones->countLabels());
    }

    public function test_class_structure_is_correct(): void
    {
        $reflection = new \ReflectionClass(WebServiceRemoverMilestones::class);

        // Test that it has the required method
        $this->assertTrue($reflection->hasMethod('countLabels'));

        // Test that the method is public
        $method = $reflection->getMethod('countLabels');
        $this->assertTrue($method->isPublic());

        // Test return type
        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertEquals('int', $returnType->getName());
    }
}
