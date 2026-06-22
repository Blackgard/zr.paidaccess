<?php

/**
 * Минимальная заглушка CUser для unit-тестов AccessControl.
 */
class CUser
{
    /** @var array<int, int[]> */
    public static $groupsByUser = [];

    public static function reset(): void
    {
        self::$groupsByUser = [];
    }

    /**
     * @return int[]
     */
    public static function GetUserGroup($userId): array
    {
        return self::$groupsByUser[(int)$userId] ?? [];
    }
}
