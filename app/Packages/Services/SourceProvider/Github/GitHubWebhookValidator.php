<?php

namespace App\Packages\Services\SourceProvider\Github;

use Illuminate\Http\Request;

/**
 * Validates GitHub webhook requests using HMAC SHA256 signatures.
 *
 * Ensures webhook requests are genuinely from GitHub by verifying
 * the X-Hub-Signature-256 header against the webhook secret.
 */
class GitHubWebhookValidator
{
    /**
     * Validate the GitHub webhook signature.
     *
     * @param  string  $secret  The webhook secret used to sign the payload
     */
    public function validate(Request $request, string $secret): bool
    {
        $signature = $request->header('X-Hub-Signature-256');

        if (! $signature) {
            return false;
        }

        $payload = $request->getContent();
        $expectedSignature = 'sha256='.hash_hmac('sha256', $payload, $secret);

        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Get the event type from the webhook request.
     */
    public function getEventType(Request $request): ?string
    {
        return $request->header('X-GitHub-Event');
    }

    /**
     * Get the delivery ID from the webhook request.
     */
    public function getDeliveryId(Request $request): ?string
    {
        return $request->header('X-GitHub-Delivery');
    }

    /**
     * Check if the webhook event is a push event.
     */
    public function isPushEvent(Request $request): bool
    {
        return $this->getEventType($request) === 'push';
    }

    /**
     * Extract repository information from the webhook payload.
     *
     * @return array{owner: string, repo: string, full_name: string}|null
     */
    public function getRepositoryInfo(Request $request): ?array
    {
        $repository = $request->input('repository');

        if (! $repository) {
            return null;
        }

        return [
            'owner' => $repository['owner']['name'] ?? $repository['owner']['login'] ?? null,
            'repo' => $repository['name'] ?? null,
            'full_name' => $repository['full_name'] ?? null,
        ];
    }

    /**
     * Extract commit information from a push event.
     *
     * @return array{sha: string, message: string, author: string, branch: string}|null
     */
    public function getCommitInfo(Request $request): ?array
    {
        if (! $this->isPushEvent($request)) {
            return null;
        }

        $headCommit = $request->input('head_commit');
        $ref = $request->input('ref');

        if (! $headCommit || ! $ref) {
            return null;
        }

        // Extract branch name from ref (refs/heads/main -> main)
        $branch = str_replace('refs/heads/', '', $ref);

        return [
            'sha' => $headCommit['id'] ?? null,
            'message' => $headCommit['message'] ?? null,
            'author' => $headCommit['author']['username'] ?? $headCommit['author']['name'] ?? null,
            'branch' => $branch,
        ];
    }
}
