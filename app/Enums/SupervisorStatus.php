<?php

namespace App\Enums;

enum SupervisorStatus: string
{
    case Installing = 'installing';
    case Active = 'active';
    case Failed = 'failed';
    case Uninstalling = 'uninstalling';
    case Uninstalled = 'uninstalled';
}
