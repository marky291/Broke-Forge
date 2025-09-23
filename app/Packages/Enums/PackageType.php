<?php

namespace App\Packages\Enums;

enum PackageType: string
{
    case ReverseProxy = 'reverse_proxy';
    case Database = 'database';
    case Git = 'git';
    case Site = 'site';
    case Command = 'command';
}
