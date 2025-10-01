<?php

namespace App\Enums;

enum PhpStatus: string
{
    case Installing = 'installing';
    case Active = 'active';
    case Failed = 'failed';
    case Inactive = 'inactive';
}
