<?php

namespace App\Enums;

enum TaskStatus: string
{
    case Pending = 'pending';
    case Installing = 'installing';
    case Active = 'active';
    case Paused = 'paused';
    case Failed = 'failed';
    case Removing = 'removing';
}
