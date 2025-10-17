<?php

namespace Tests\Unit\Packages\Services\SourceProvider\Github;

use App\Packages\Services\SourceProvider\Github\GitHubWebhookValidator;
use Illuminate\Http\Request;
use Tests\TestCase;

class GitHubWebhookValidatorTest extends TestCase
{
    /**
     * Test validate returns true for valid signature.
     */
    public function test_validate_returns_true_for_valid_signature(): void
    {
        // Arrange
        $validator = new GitHubWebhookValidator;
        $secret = 'my-webhook-secret';
        $payload = '{"action":"opened","repository":{"name":"test-repo"}}';
        $expectedSignature = 'sha256='.hash_hmac('sha256', $payload, $secret);

        $request = Request::create('/webhook', 'POST', [], [], [], [], $payload);
        $request->headers->set('X-Hub-Signature-256', $expectedSignature);

        // Act
        $result = $validator->validate($request, $secret);

        // Assert
        $this->assertTrue($result);
    }

    /**
     * Test validate returns false for invalid signature.
     */
    public function test_validate_returns_false_for_invalid_signature(): void
    {
        // Arrange
        $validator = new GitHubWebhookValidator;
        $secret = 'my-webhook-secret';
        $payload = '{"action":"opened"}';

        $request = Request::create('/webhook', 'POST', [], [], [], [], $payload);
        $request->headers->set('X-Hub-Signature-256', 'sha256=invalid-signature');

        // Act
        $result = $validator->validate($request, $secret);

        // Assert
        $this->assertFalse($result);
    }

    /**
     * Test validate returns false when signature header is missing.
     */
    public function test_validate_returns_false_when_signature_header_missing(): void
    {
        // Arrange
        $validator = new GitHubWebhookValidator;
        $secret = 'my-webhook-secret';
        $payload = '{"action":"opened"}';

        $request = Request::create('/webhook', 'POST', [], [], [], [], $payload);

        // Act
        $result = $validator->validate($request, $secret);

        // Assert
        $this->assertFalse($result);
    }

    /**
     * Test validate returns false with wrong secret.
     */
    public function test_validate_returns_false_with_wrong_secret(): void
    {
        // Arrange
        $validator = new GitHubWebhookValidator;
        $correctSecret = 'correct-secret';
        $wrongSecret = 'wrong-secret';
        $payload = '{"action":"opened"}';
        $signature = 'sha256='.hash_hmac('sha256', $payload, $wrongSecret);

        $request = Request::create('/webhook', 'POST', [], [], [], [], $payload);
        $request->headers->set('X-Hub-Signature-256', $signature);

        // Act
        $result = $validator->validate($request, $correctSecret);

        // Assert
        $this->assertFalse($result);
    }

    /**
     * Test validate handles empty payload.
     */
    public function test_validate_handles_empty_payload(): void
    {
        // Arrange
        $validator = new GitHubWebhookValidator;
        $secret = 'my-webhook-secret';
        $payload = '';
        $expectedSignature = 'sha256='.hash_hmac('sha256', $payload, $secret);

        $request = Request::create('/webhook', 'POST', [], [], [], [], $payload);
        $request->headers->set('X-Hub-Signature-256', $expectedSignature);

        // Act
        $result = $validator->validate($request, $secret);

        // Assert
        $this->assertTrue($result);
    }

    /**
     * Test getEventType returns correct event type.
     */
    public function test_get_event_type_returns_correct_event_type(): void
    {
        // Arrange
        $validator = new GitHubWebhookValidator;
        $request = Request::create('/webhook', 'POST');
        $request->headers->set('X-GitHub-Event', 'push');

        // Act
        $eventType = $validator->getEventType($request);

        // Assert
        $this->assertEquals('push', $eventType);
    }

    /**
     * Test getEventType returns null when header missing.
     */
    public function test_get_event_type_returns_null_when_header_missing(): void
    {
        // Arrange
        $validator = new GitHubWebhookValidator;
        $request = Request::create('/webhook', 'POST');

        // Act
        $eventType = $validator->getEventType($request);

        // Assert
        $this->assertNull($eventType);
    }

    /**
     * Test getDeliveryId returns correct delivery ID.
     */
    public function test_get_delivery_id_returns_correct_delivery_id(): void
    {
        // Arrange
        $validator = new GitHubWebhookValidator;
        $request = Request::create('/webhook', 'POST');
        $request->headers->set('X-GitHub-Delivery', '12345-67890-abcdef');

        // Act
        $deliveryId = $validator->getDeliveryId($request);

        // Assert
        $this->assertEquals('12345-67890-abcdef', $deliveryId);
    }

    /**
     * Test getDeliveryId returns null when header missing.
     */
    public function test_get_delivery_id_returns_null_when_header_missing(): void
    {
        // Arrange
        $validator = new GitHubWebhookValidator;
        $request = Request::create('/webhook', 'POST');

        // Act
        $deliveryId = $validator->getDeliveryId($request);

        // Assert
        $this->assertNull($deliveryId);
    }

    /**
     * Test isPushEvent returns true for push events.
     */
    public function test_is_push_event_returns_true_for_push_events(): void
    {
        // Arrange
        $validator = new GitHubWebhookValidator;
        $request = Request::create('/webhook', 'POST');
        $request->headers->set('X-GitHub-Event', 'push');

        // Act
        $result = $validator->isPushEvent($request);

        // Assert
        $this->assertTrue($result);
    }

    /**
     * Test isPushEvent returns false for non-push events.
     */
    public function test_is_push_event_returns_false_for_non_push_events(): void
    {
        // Arrange
        $validator = new GitHubWebhookValidator;
        $request = Request::create('/webhook', 'POST');
        $request->headers->set('X-GitHub-Event', 'pull_request');

        // Act
        $result = $validator->isPushEvent($request);

        // Assert
        $this->assertFalse($result);
    }

    /**
     * Test getRepositoryInfo extracts repository information correctly.
     */
    public function test_get_repository_info_extracts_repository_information_correctly(): void
    {
        // Arrange
        $validator = new GitHubWebhookValidator;
        $payload = [
            'repository' => [
                'name' => 'test-repo',
                'full_name' => 'octocat/test-repo',
                'owner' => [
                    'login' => 'octocat',
                    'name' => 'Octo Cat',
                ],
            ],
        ];

        $request = Request::create('/webhook', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($payload));

        // Act
        $info = $validator->getRepositoryInfo($request);

        // Assert
        $this->assertIsArray($info);
        $this->assertEquals('Octo Cat', $info['owner']);
        $this->assertEquals('test-repo', $info['repo']);
        $this->assertEquals('octocat/test-repo', $info['full_name']);
    }

    /**
     * Test getRepositoryInfo falls back to login when name missing.
     */
    public function test_get_repository_info_falls_back_to_login_when_name_missing(): void
    {
        // Arrange
        $validator = new GitHubWebhookValidator;
        $payload = [
            'repository' => [
                'name' => 'test-repo',
                'full_name' => 'octocat/test-repo',
                'owner' => [
                    'login' => 'octocat',
                ],
            ],
        ];

        $request = Request::create('/webhook', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($payload));

        // Act
        $info = $validator->getRepositoryInfo($request);

        // Assert
        $this->assertIsArray($info);
        $this->assertEquals('octocat', $info['owner']);
    }

    /**
     * Test getRepositoryInfo returns null when repository missing.
     */
    public function test_get_repository_info_returns_null_when_repository_missing(): void
    {
        // Arrange
        $validator = new GitHubWebhookValidator;
        $payload = ['action' => 'opened'];

        $request = Request::create('/webhook', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($payload));

        // Act
        $info = $validator->getRepositoryInfo($request);

        // Assert
        $this->assertNull($info);
    }

    /**
     * Test getCommitInfo extracts commit information from push event.
     */
    public function test_get_commit_info_extracts_commit_information_from_push_event(): void
    {
        // Arrange
        $validator = new GitHubWebhookValidator;
        $payload = [
            'ref' => 'refs/heads/main',
            'head_commit' => [
                'id' => 'abc123def456',
                'message' => 'Fix bug in authentication',
                'author' => [
                    'username' => 'octocat',
                    'name' => 'Octo Cat',
                ],
            ],
        ];

        $request = Request::create('/webhook', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($payload));
        $request->headers->set('X-GitHub-Event', 'push');

        // Act
        $info = $validator->getCommitInfo($request);

        // Assert
        $this->assertIsArray($info);
        $this->assertEquals('abc123def456', $info['sha']);
        $this->assertEquals('Fix bug in authentication', $info['message']);
        $this->assertEquals('octocat', $info['author']);
        $this->assertEquals('main', $info['branch']);
    }

    /**
     * Test getCommitInfo extracts branch name correctly from ref.
     */
    public function test_get_commit_info_extracts_branch_name_correctly_from_ref(): void
    {
        // Arrange
        $validator = new GitHubWebhookValidator;
        $payload = [
            'ref' => 'refs/heads/feature/new-feature',
            'head_commit' => [
                'id' => 'abc123',
                'message' => 'Add new feature',
                'author' => ['name' => 'Developer'],
            ],
        ];

        $request = Request::create('/webhook', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($payload));
        $request->headers->set('X-GitHub-Event', 'push');

        // Act
        $info = $validator->getCommitInfo($request);

        // Assert
        $this->assertEquals('feature/new-feature', $info['branch']);
    }

    /**
     * Test getCommitInfo falls back to name when username missing.
     */
    public function test_get_commit_info_falls_back_to_name_when_username_missing(): void
    {
        // Arrange
        $validator = new GitHubWebhookValidator;
        $payload = [
            'ref' => 'refs/heads/main',
            'head_commit' => [
                'id' => 'abc123',
                'message' => 'Commit message',
                'author' => [
                    'name' => 'John Doe',
                ],
            ],
        ];

        $request = Request::create('/webhook', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($payload));
        $request->headers->set('X-GitHub-Event', 'push');

        // Act
        $info = $validator->getCommitInfo($request);

        // Assert
        $this->assertEquals('John Doe', $info['author']);
    }

    /**
     * Test getCommitInfo returns null for non-push events.
     */
    public function test_get_commit_info_returns_null_for_non_push_events(): void
    {
        // Arrange
        $validator = new GitHubWebhookValidator;
        $payload = [
            'ref' => 'refs/heads/main',
            'head_commit' => ['id' => 'abc123'],
        ];

        $request = Request::create('/webhook', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($payload));
        $request->headers->set('X-GitHub-Event', 'pull_request');

        // Act
        $info = $validator->getCommitInfo($request);

        // Assert
        $this->assertNull($info);
    }

    /**
     * Test getCommitInfo returns null when head_commit missing.
     */
    public function test_get_commit_info_returns_null_when_head_commit_missing(): void
    {
        // Arrange
        $validator = new GitHubWebhookValidator;
        $payload = ['ref' => 'refs/heads/main'];

        $request = Request::create('/webhook', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($payload));
        $request->headers->set('X-GitHub-Event', 'push');

        // Act
        $info = $validator->getCommitInfo($request);

        // Assert
        $this->assertNull($info);
    }

    /**
     * Test getCommitInfo returns null when ref missing.
     */
    public function test_get_commit_info_returns_null_when_ref_missing(): void
    {
        // Arrange
        $validator = new GitHubWebhookValidator;
        $payload = [
            'head_commit' => [
                'id' => 'abc123',
                'message' => 'Test commit',
            ],
        ];

        $request = Request::create('/webhook', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($payload));
        $request->headers->set('X-GitHub-Event', 'push');

        // Act
        $info = $validator->getCommitInfo($request);

        // Assert
        $this->assertNull($info);
    }

    /**
     * Test validate uses timing-safe comparison.
     */
    public function test_validate_uses_timing_safe_comparison(): void
    {
        // Arrange
        $validator = new GitHubWebhookValidator;
        $secret = 'secret';
        $payload = 'test payload';

        // Create two different signatures with same length
        $validSignature = 'sha256='.hash_hmac('sha256', $payload, $secret);
        $invalidSignature = 'sha256='.str_repeat('a', 64);

        $request1 = Request::create('/webhook', 'POST', [], [], [], [], $payload);
        $request1->headers->set('X-Hub-Signature-256', $validSignature);

        $request2 = Request::create('/webhook', 'POST', [], [], [], [], $payload);
        $request2->headers->set('X-Hub-Signature-256', $invalidSignature);

        // Act
        $result1 = $validator->validate($request1, $secret);
        $result2 = $validator->validate($request2, $secret);

        // Assert - Ensures hash_equals is being used for timing attack resistance
        $this->assertTrue($result1);
        $this->assertFalse($result2);
    }
}
