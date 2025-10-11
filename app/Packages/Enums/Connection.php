<?php

namespace App\Packages\Enums;

enum Connection: string
{
    case PENDING = 'pending';

    case CONNECTING = 'connecting';

    case CONNECTED = 'connected';

    case FAILED = 'failed';
}
