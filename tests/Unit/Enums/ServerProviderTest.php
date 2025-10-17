<?php

namespace Tests\Unit\Enums;

use App\Enums\ServerProvider;
use Tests\TestCase;

class ServerProviderTest extends TestCase
{
    /**
     * Test label returns correct label for AWS provider.
     */
    public function test_label_returns_correct_label_for_aws_provider(): void
    {
        // Arrange
        $provider = ServerProvider::AWS;

        // Act
        $label = $provider->label();

        // Assert
        $this->assertEquals('Amazon Web Services', $label);
    }

    /**
     * Test label returns correct label for Google Cloud provider.
     */
    public function test_label_returns_correct_label_for_google_cloud_provider(): void
    {
        // Arrange
        $provider = ServerProvider::GoogleCloud;

        // Act
        $label = $provider->label();

        // Assert
        $this->assertEquals('Google Cloud', $label);
    }

    /**
     * Test label returns correct label for Azure provider.
     */
    public function test_label_returns_correct_label_for_azure_provider(): void
    {
        // Arrange
        $provider = ServerProvider::Azure;

        // Act
        $label = $provider->label();

        // Assert
        $this->assertEquals('Microsoft Azure', $label);
    }

    /**
     * Test label returns correct label for DigitalOcean provider.
     */
    public function test_label_returns_correct_label_for_digitalocean_provider(): void
    {
        // Arrange
        $provider = ServerProvider::DigitalOcean;

        // Act
        $label = $provider->label();

        // Assert
        $this->assertEquals('DigitalOcean', $label);
    }

    /**
     * Test label returns correct label for Linode provider.
     */
    public function test_label_returns_correct_label_for_linode_provider(): void
    {
        // Arrange
        $provider = ServerProvider::Linode;

        // Act
        $label = $provider->label();

        // Assert
        $this->assertEquals('Linode', $label);
    }

    /**
     * Test label returns correct label for Vultr provider.
     */
    public function test_label_returns_correct_label_for_vultr_provider(): void
    {
        // Arrange
        $provider = ServerProvider::Vultr;

        // Act
        $label = $provider->label();

        // Assert
        $this->assertEquals('Vultr', $label);
    }

    /**
     * Test label returns correct label for Hetzner provider.
     */
    public function test_label_returns_correct_label_for_hetzner_provider(): void
    {
        // Arrange
        $provider = ServerProvider::Hetzner;

        // Act
        $label = $provider->label();

        // Assert
        $this->assertEquals('Hetzner', $label);
    }

    /**
     * Test label returns correct label for Custom provider.
     */
    public function test_label_returns_correct_label_for_custom_provider(): void
    {
        // Arrange
        $provider = ServerProvider::Custom;

        // Act
        $label = $provider->label();

        // Assert
        $this->assertEquals('Custom/Other', $label);
    }

    /**
     * Test svgBgColor returns white for AWS provider.
     */
    public function test_svg_bg_color_returns_white_for_aws_provider(): void
    {
        // Arrange
        $provider = ServerProvider::AWS;

        // Act
        $color = $provider->svgBgColor();

        // Assert
        $this->assertEquals('#ffffff', $color);
    }

    /**
     * Test svgBgColor returns null for Google Cloud provider.
     */
    public function test_svg_bg_color_returns_null_for_google_cloud_provider(): void
    {
        // Arrange
        $provider = ServerProvider::GoogleCloud;

        // Act
        $color = $provider->svgBgColor();

        // Assert
        $this->assertNull($color);
    }

    /**
     * Test svgBgColor returns null for Azure provider.
     */
    public function test_svg_bg_color_returns_null_for_azure_provider(): void
    {
        // Arrange
        $provider = ServerProvider::Azure;

        // Act
        $color = $provider->svgBgColor();

        // Assert
        $this->assertNull($color);
    }

    /**
     * Test svgBgColor returns null for DigitalOcean provider.
     */
    public function test_svg_bg_color_returns_null_for_digitalocean_provider(): void
    {
        // Arrange
        $provider = ServerProvider::DigitalOcean;

        // Act
        $color = $provider->svgBgColor();

        // Assert
        $this->assertNull($color);
    }

    /**
     * Test svgBgColor returns null for Linode provider.
     */
    public function test_svg_bg_color_returns_null_for_linode_provider(): void
    {
        // Arrange
        $provider = ServerProvider::Linode;

        // Act
        $color = $provider->svgBgColor();

        // Assert
        $this->assertNull($color);
    }

    /**
     * Test svgBgColor returns null for Vultr provider.
     */
    public function test_svg_bg_color_returns_null_for_vultr_provider(): void
    {
        // Arrange
        $provider = ServerProvider::Vultr;

        // Act
        $color = $provider->svgBgColor();

        // Assert
        $this->assertNull($color);
    }

    /**
     * Test svgBgColor returns null for Hetzner provider.
     */
    public function test_svg_bg_color_returns_null_for_hetzner_provider(): void
    {
        // Arrange
        $provider = ServerProvider::Hetzner;

        // Act
        $color = $provider->svgBgColor();

        // Assert
        $this->assertNull($color);
    }

    /**
     * Test svgBgColor returns null for Custom provider.
     */
    public function test_svg_bg_color_returns_null_for_custom_provider(): void
    {
        // Arrange
        $provider = ServerProvider::Custom;

        // Act
        $color = $provider->svgBgColor();

        // Assert
        $this->assertNull($color);
    }

    /**
     * Test all providers have labels.
     */
    public function test_all_providers_have_labels(): void
    {
        // Arrange & Act & Assert
        foreach (ServerProvider::cases() as $provider) {
            $label = $provider->label();

            $this->assertIsString($label, "{$provider->value} should return a string label");
            $this->assertNotEmpty($label, "{$provider->value} should not return an empty label");
        }
    }

    /**
     * Test all providers have svg background color defined or null.
     */
    public function test_all_providers_have_svg_bg_color_defined_or_null(): void
    {
        // Arrange & Act & Assert
        foreach (ServerProvider::cases() as $provider) {
            $color = $provider->svgBgColor();

            // Color should be either null or a string (hex color)
            $this->assertTrue(
                is_null($color) || is_string($color),
                "{$provider->value} should return null or a string for svgBgColor"
            );

            // If it's a string, it should be a valid hex color
            if (is_string($color)) {
                $this->assertMatchesRegularExpression(
                    '/^#[0-9a-f]{6}$/i',
                    $color,
                    "{$provider->value} svgBgColor should be a valid hex color"
                );
            }
        }
    }

    /**
     * Test AWS is the only provider with custom svg background color.
     */
    public function test_aws_is_the_only_provider_with_custom_svg_background_color(): void
    {
        // Arrange & Act
        $providersWithColor = [];
        foreach (ServerProvider::cases() as $provider) {
            if ($provider->svgBgColor() !== null) {
                $providersWithColor[] = $provider;
            }
        }

        // Assert
        $this->assertCount(1, $providersWithColor);
        $this->assertEquals(ServerProvider::AWS, $providersWithColor[0]);
    }

    /**
     * Test enum has exactly eight cases.
     */
    public function test_enum_has_exactly_eight_cases(): void
    {
        // Act
        $cases = ServerProvider::cases();

        // Assert
        $this->assertCount(8, $cases);
    }

    /**
     * Test enum includes all major cloud providers.
     */
    public function test_enum_includes_all_major_cloud_providers(): void
    {
        // Arrange
        $expectedProviders = [
            'aws',
            'google-cloud',
            'azure',
            'digitalocean',
            'linode',
            'vultr',
            'hetzner',
            'custom',
        ];

        // Act
        $actualProviders = array_map(fn ($provider) => $provider->value, ServerProvider::cases());

        // Assert
        $this->assertEquals($expectedProviders, $actualProviders);
    }

    /**
     * Test provider values use kebab-case format.
     */
    public function test_provider_values_use_kebab_case_format(): void
    {
        // Arrange & Act & Assert
        foreach (ServerProvider::cases() as $provider) {
            // Value should be lowercase and may contain hyphens
            $this->assertMatchesRegularExpression(
                '/^[a-z]+(-[a-z]+)*$/',
                $provider->value,
                "{$provider->value} should use kebab-case format"
            );
        }
    }
}
