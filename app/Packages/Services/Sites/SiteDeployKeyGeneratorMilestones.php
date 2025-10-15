<?php

namespace App\Packages\Services\Sites;

use App\Packages\Base\Milestones;

class SiteDeployKeyGeneratorMilestones extends Milestones
{
    public const GENERATE_KEY = 'generate_key';

    public const SET_PERMISSIONS = 'set_permissions';

    public const READ_PUBLIC_KEY = 'read_public_key';

    public const COMPLETE = 'complete';

    private const LABELS = [
        self::GENERATE_KEY => 'Generating SSH key pair',
        self::SET_PERMISSIONS => 'Setting key permissions',
        self::READ_PUBLIC_KEY => 'Reading public key',
        self::COMPLETE => 'Deploy key generation complete',
    ];

    public static function labels(): array
    {
        return self::LABELS;
    }

    public static function label(string $milestone): ?string
    {
        return self::LABELS[$milestone] ?? null;
    }

    public function countLabels(): int
    {
        return count(self::LABELS);
    }
}
