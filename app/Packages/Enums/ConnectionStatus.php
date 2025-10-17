<?php

namespace App\Packages\Enums;

enum ConnectionStatus: string
{
    case PENDING = 'pending';

    case CONNECTING = 'connecting';

    case CONNECTED = 'connected';

    case FAILED = 'failed';
}
