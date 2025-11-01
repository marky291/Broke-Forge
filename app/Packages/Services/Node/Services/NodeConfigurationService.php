<?php

namespace App\Packages\Services\Node\Services;

class NodeConfigurationService
{
    /**
     * Get available Node.js versions
     */
    public static function getAvailableVersions(): array
    {
        return [
            '22' => 'Node.js 22 (Current)',
            '20' => 'Node.js 20 LTS (Iron)',
            '18' => 'Node.js 18 LTS (Hydrogen)',
            '16' => 'Node.js 16 LTS (Gallium)',
        ];
    }

    /**
     * Get validation rules for Node installation
     */
    public static function getValidationRules(): array
    {
        $versions = array_keys(self::getAvailableVersions());

        return [
            'version' => 'required|in:'.implode(',', $versions),
            'is_default' => 'nullable|boolean',
        ];
    }
}
