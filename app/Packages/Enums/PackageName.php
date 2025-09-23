<?php

namespace App\Packages\Enums;

enum PackageName: string
{
    case Nginx = 'nginx';
    case MySql80 = 'mysql80';
    case Git = 'git';
    case Site = 'site';
    case Command = 'command';
}
