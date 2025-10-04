<?php

namespace App\Packages\Services;

use Spatie\Ssh\Ssh;
use Symfony\Component\Process\Process;

/**
 * Windows-Compatible SSH Wrapper
 *
 * Extends Spatie's SSH to work properly on Windows by avoiding heredoc syntax
 * which doesn't work reliably in Windows command shells.
 */
class WindowsCompatibleSsh extends Ssh
{
    public function getExecuteCommand($command): string
    {
        $commands = $this->wrapArray($command);
        $commandString = implode(PHP_EOL, $commands);

        if (in_array($this->host, ['local', 'localhost', '127.0.0.1'])) {
            return $commandString;
        }

        // On Windows, use simple command execution instead of heredoc
        if (PHP_OS_FAMILY === 'Windows') {
            $passwordCommand = $this->getPasswordCommand();
            $extraOptions = implode(' ', $this->getExtraOptions());
            $target = $this->getTargetForSsh();

            // Escape the command for SSH on Windows using base64 encoding to avoid all escaping issues
            // This is the most reliable way to pass complex bash commands through Windows -> SSH -> Linux
            $base64Command = base64_encode($commandString);

            return "{$passwordCommand}ssh {$extraOptions} {$target} \"echo {$base64Command} | base64 -d | bash\"";
        }

        // On Unix, use parent's heredoc implementation
        return parent::getExecuteCommand($command);
    }
}
