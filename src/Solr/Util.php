<?php

declare(strict_types=1);

namespace Jield\Search\Solr;

use function defined;
use function gettype;
use function ini_get;
use function is_string;
use function number_format;
use function str_replace;

final class Util
{
    /** @var array */
    private static array $search = [
        ' ',
        '\\',
        '+',
        '-',
        '&',
        '|',
        '!',
        '(',
        ')',
        '{',
        '}',
        '[',
        ']',
        '^',
        '"',
        '~',
        '*',
        '?',
        ':',
        '/',
    ];

    /** * @var array */
    private static array $replace = [
        '\+',
        '\\\\',
        '\+',
        '\-',
        '\&',
        '\|',
        '\!',
        '\(',
        '\)',
        '\{',
        '\}',
        '\[',
        '\]',
        '\^',
        '\"',
        '\~',
        '\*',
        '\?',
        '\:',
        '\/',
    ];

    /**
     * Quote a given string
     */
    public static function quote(mixed $value): ExpressionInterface|string
    {
        if ($value instanceof ExpressionInterface) {
            return $value;
        }

        if (!is_string(value: $value)) {
            return (string)$value;
        }

        return '"' . str_replace(search: self::$search, replace: self::$replace, subject: $value) . '"';
    }

    /**
     * Sanitizes a string
     *
     * Puts quotes around a string, treats everything else as a term
     *
     * @param mixed $value
     * @return string|ExpressionInterface
     */
    public static function sanitize(mixed $value): string
    {
        $type = gettype($value);

        if ($type === 'string') {
            if ($value !== '') {
                return '"' . str_replace(search: self::$search, replace: self::$replace, subject: (string)$value) . '"';
            }

            return $value;
        }

        if ($type === 'integer') {
            return (string)$value;
        }

        if ($type === 'double') {
            static $precision;

            if (!$precision) {
                $precision = defined(constant_name: 'HHVM_VERSION') ? 14 : ini_get(option: 'precision');
            }

            return number_format(num: $value, decimals: $precision, decimal_separator: '.', thousands_separator: '');
        }

        if ($type === 'boolean') {
            return $value ? 'true' : 'false';
        }

        if ($value instanceof ExpressionInterface) {
            return (string)$value;
        }

        if (empty($value)) {
            return '';
        }

        return $value;
    }

    /**
     * Escape a string to be safe for Solr queries
     */
    public static function escape(mixed $value): ExpressionInterface|string
    {
        if ($value instanceof ExpressionInterface) {
            return $value;
        }

        if (!is_string(value: $value)) {
            return (string)$value;
        }

        return str_replace(search: self::$search, replace: self::$replace, subject: $value);
    }
}
