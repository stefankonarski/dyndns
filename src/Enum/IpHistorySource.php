<?php

declare(strict_types=1);

namespace App\Enum;

enum IpHistorySource: string
{
    case Fritzbox = 'fritzbox';
    case Manual = 'manual';
    case Delete = 'delete';
    case Sync = 'sync';
}

