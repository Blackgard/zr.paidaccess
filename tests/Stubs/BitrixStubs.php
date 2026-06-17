<?php

namespace Bitrix\Main\Config;

/**
 * Минимальная заглушка COption для unit-тестов без Bitrix.
 */
class Option
{
    /** @var array<string, string> */
    private static $values = [];

    public static function reset(): void
    {
        self::$values = [];
    }

    public static function set(string $moduleId, string $name, string $value, string $siteId = ''): void
    {
        self::$values[self::key($moduleId, $name, $siteId)] = $value;
    }

    public static function get(string $moduleId, string $name, string $default = '', $siteId = false): string
    {
        $site = is_string($siteId) ? $siteId : '';
        $key = self::key($moduleId, $name, $site);

        return self::$values[$key] ?? $default;
    }

    private static function key(string $moduleId, string $name, string $siteId): string
    {
        return $moduleId . '|' . $siteId . '|' . $name;
    }
}

namespace Bitrix\Main\Type;

/**
 * Минимальная заглушка Bitrix DateTime (не наследует PHP \DateTime).
 */
class DateTime
{
    /** @var int */
    private $timestamp;

    public function __construct($time = null)
    {
        if ($time === null || $time === '') {
            $this->timestamp = time();
        } elseif (is_numeric($time)) {
            $this->timestamp = (int)$time;
        } else {
            $this->timestamp = strtotime((string)$time) ?: time();
        }
    }

    public function getTimestamp(): int
    {
        return $this->timestamp;
    }

    public function format(string $format): string
    {
        return date($format, $this->timestamp);
    }

    public static function createFromTimestamp(int $timestamp): self
    {
        $instance = new self();
        $instance->timestamp = $timestamp;

        return $instance;
    }
}

namespace Bitrix\Main;

class ArgumentException extends \Exception
{
}

class GroupTable
{
    /**
     * @param array<string, mixed> $params
     */
    public static function getList(array $params = [])
    {
        return new class () {
            public function fetch()
            {
                return false;
            }
        };
    }
}

namespace Bitrix\Main\Web;

use Bitrix\Main\ArgumentException;

class Json
{
    /**
     * @param mixed $data
     */
    public static function encode($data, ?int $options = null): string
    {
        $result = json_encode($data, $options ?? JSON_UNESCAPED_UNICODE);

        if ($result === false) {
            throw new ArgumentException(json_last_error_msg());
        }

        return $result;
    }

    /**
     * @return mixed
     */
    public static function decode(string $json)
    {
        $result = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new ArgumentException(json_last_error_msg());
        }

        return $result;
    }
}
