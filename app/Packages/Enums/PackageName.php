<?php

namespace App\Packages\Enums;

enum PackageName: string
{
    case Nginx = 'nginx';
    case MySql80 = 'mysql80';
    case MariaDb = 'mariadb';
    case PostgreSql = 'postgresql';
    case Git = 'git';
    case Site = 'site';
    case Command = 'command';
    case Php81 = 'php81';
    case Php82 = 'php82';
    case Php83 = 'php83';
    case Php84 = 'php84';
    case FirewallUfw = 'firewall-ufw';
    case Deployment = 'deployment';
    case Monitoring = 'monitoring';
    case Scheduler = 'scheduler';
    case ScheduledTask = 'scheduled-task';
    case Supervisor = 'supervisor';
    case SupervisorTask = 'supervisor-task';
}
