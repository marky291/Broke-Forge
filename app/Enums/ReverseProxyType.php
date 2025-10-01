<?php

namespace App\Enums;

enum ReverseProxyType: string
{
    case Nginx = 'nginx';
    case Apache = 'apache';
    case Caddy = 'caddy';
}
