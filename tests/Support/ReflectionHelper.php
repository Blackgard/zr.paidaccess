<?php

namespace Zr\PaidAccess\Tests\Support;

final class ReflectionHelper
{
    /**
     * @param mixed ...$args
     * @return mixed
     */
    public static function invokeStatic(string $class, string $method, ...$args)
    {
        $reflection = new \ReflectionMethod($class, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs(null, $args);
    }
}
