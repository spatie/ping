<?php

namespace Spatie\Ping\Enums;

enum PingError: string
{
    case HostnameNotFound = 'hostnameNotFound';
    case HostUnreachable = 'hostUnreachable';
    case PermissionDenied = 'permissionDenied';
    case Timeout = 'timeout';
    case UnknownError = 'unknownError';
}
