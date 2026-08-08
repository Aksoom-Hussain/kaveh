<?php

declare(strict_types=1);

namespace Kaveh\Contracts;

enum EventType: string
{
    case Exception = 'exception';
    case Request = 'request';
    case Query = 'query';
    case Job = 'job';
    case Log = 'log';
    case Custom = 'custom';
}
