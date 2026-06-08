<?php

namespace Zr\PaidAccess\Enum;

final class ModuleLogLevel
{
    public const DEBUG = 'debug';
    public const INFO = 'info';
    public const WARNING = 'warning';
    public const ERROR = 'error';

    public const ALL = [
        self::DEBUG,
        self::INFO,
        self::WARNING,
        self::ERROR,
    ];
}
