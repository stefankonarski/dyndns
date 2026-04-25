<?php

declare(strict_types=1);

namespace App\Enum;

enum DdnsResult: string
{
    case Unchanged = 'unchanged';
    case Updated = 'updated';
    case Created = 'created';
    case Deleted = 'deleted';
    case AuthFailed = 'auth_failed';
    case ValidationFailed = 'validation_failed';
    case ConfigError = 'config_error';
    case HetznerError = 'hetzner_error';
    case InternalError = 'internal_error';
}

