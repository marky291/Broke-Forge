<?php

namespace App\Enums;

enum StorageType: string
{
    case Memory = 'memory';
    case Disk = 'disk';
}
