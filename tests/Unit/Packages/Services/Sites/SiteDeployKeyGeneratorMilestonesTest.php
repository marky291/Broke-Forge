<?php

namespace Tests\Unit\Packages\Services\Sites;

use App\Packages\Services\Sites\SiteDeployKeyGeneratorMilestones;
use Tests\TestCase;

class SiteDeployKeyGeneratorMilestonesTest extends TestCase
{
    /**
     * Test labels returns all milestone labels.
     */
    public function test_labels_returns_all_milestone_labels(): void
    {
        // Act
        $labels = SiteDeployKeyGeneratorMilestones::labels();

        // Assert
        $this->assertIsArray($labels);
        $this->assertCount(4, $labels);
        $this->assertArrayHasKey(SiteDeployKeyGeneratorMilestones::GENERATE_KEY, $labels);
        $this->assertArrayHasKey(SiteDeployKeyGeneratorMilestones::SET_PERMISSIONS, $labels);
        $this->assertArrayHasKey(SiteDeployKeyGeneratorMilestones::READ_PUBLIC_KEY, $labels);
        $this->assertArrayHasKey(SiteDeployKeyGeneratorMilestones::COMPLETE, $labels);
    }

    /**
     * Test label returns specific milestone label.
     */
    public function test_label_returns_specific_milestone_label(): void
    {
        // Act & Assert
        $this->assertEquals('Generating SSH key pair', SiteDeployKeyGeneratorMilestones::label(SiteDeployKeyGeneratorMilestones::GENERATE_KEY));
        $this->assertEquals('Setting key permissions', SiteDeployKeyGeneratorMilestones::label(SiteDeployKeyGeneratorMilestones::SET_PERMISSIONS));
        $this->assertEquals('Reading public key', SiteDeployKeyGeneratorMilestones::label(SiteDeployKeyGeneratorMilestones::READ_PUBLIC_KEY));
        $this->assertEquals('Deploy key generation complete', SiteDeployKeyGeneratorMilestones::label(SiteDeployKeyGeneratorMilestones::COMPLETE));
    }

    /**
     * Test label returns null for unknown milestone.
     */
    public function test_label_returns_null_for_unknown_milestone(): void
    {
        // Act
        $label = SiteDeployKeyGeneratorMilestones::label('unknown_milestone');

        // Assert
        $this->assertNull($label);
    }

    /**
     * Test countLabels returns correct count.
     */
    public function test_count_labels_returns_correct_count(): void
    {
        // Arrange
        $milestones = new SiteDeployKeyGeneratorMilestones;

        // Act
        $count = $milestones->countLabels();

        // Assert
        $this->assertEquals(4, $count);
    }

    /**
     * Test milestone constants have expected values.
     */
    public function test_milestone_constants_have_expected_values(): void
    {
        // Assert
        $this->assertEquals('generate_key', SiteDeployKeyGeneratorMilestones::GENERATE_KEY);
        $this->assertEquals('set_permissions', SiteDeployKeyGeneratorMilestones::SET_PERMISSIONS);
        $this->assertEquals('read_public_key', SiteDeployKeyGeneratorMilestones::READ_PUBLIC_KEY);
        $this->assertEquals('complete', SiteDeployKeyGeneratorMilestones::COMPLETE);
    }
}
