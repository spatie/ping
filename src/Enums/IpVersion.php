<?php

namespace Spatie\Ping\Enums;

enum IpVersion: string
{
    case IPv4 = 'ipv4';
    case IPv6 = 'ipv6';
    case Auto = 'auto';
}
