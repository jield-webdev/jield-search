<?php

declare(strict_types=1);

namespace Jield\Search\Solr\Expression;

use Jield\Search\Solr\Util;
use Stringable;

use function array_filter;
use function implode;

/**
 * Composite expression class
 *
 * Class representing multiple expressions with an optional combination type
 */
class CompositeExpression extends Expression implements Stringable
{
    final public const TYPE_AND   = 'AND';
    final public const TYPE_OR    = 'OR';
    final public const TYPE_SPACE = ' ';

    /**
     * Create new group of expression
     *
     * @param mixed[] $expressions
     */
    public function __construct(private readonly array $expressions, private readonly ?string $type = self::TYPE_SPACE)
    {
    }

    public function __toString(): string
    {
        $parts = [];

        foreach ($this->expressions as $expression) {
            if (!$expression) {
                continue;
            }

            $parts[] = Util::sanitize(value: $expression);
        }

        if (!$parts) {
            return '';
        }

        if ($this->type === static::TYPE_OR || $this->type === static::TYPE_AND) {
            $glue = ' ' . $this->type . ' ';
        } else {
            $glue = $this->type;
        }

        return implode(separator: $glue, array: array_filter(array: $parts));
    }

    public static function isValidType(?string $type): bool
    {
        return $type === static::TYPE_OR
            || $type === static::TYPE_AND
            || $type === static::TYPE_SPACE
            || $type === null;
    }
}
