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

            // Escape the command for SSH
            $escapedCommand = addcslashes($commandString, '"$`\\');

            return "{$passwordCommand}ssh {$extraOptions} {$target} \"{$escapedCommand}\"";
        }

        // On Unix, use parent's heredoc implementation
        return parent::getExecuteCommand($command);
    }
}
