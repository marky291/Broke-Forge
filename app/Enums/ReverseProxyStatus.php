<?php

namespace App\Enums;

enum ReverseProxyStatus: string
{
    case Installing = 'installing';
    case Active = 'active';
    case Failed = 'failed';
    case Stopped = 'stopped';
}
