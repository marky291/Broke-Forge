<?php

namespace App\Enums;

enum DatabaseStatus: string
{
    case Installing = 'installing';
    case Active = 'active';
    case Failed = 'failed';
    case Stopped = 'stopped';
}
