<?php

namespace App\Packages\Enums;

enum PackageName: string
{
    case Nginx = 'nginx';
    case MySql80 = 'mysql80';
    case Git = 'git';
    case Site = 'site';
    case Command = 'command';
    case Php83 = 'php83';
    case FirewallUfw = 'firewall-ufw';
}
