<?php

namespace App\Enums;

enum TaskStatus: string
{
    case Active = 'active';
    case Paused = 'paused';
    case Failed = 'failed';
}
