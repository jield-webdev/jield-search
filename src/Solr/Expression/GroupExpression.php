<?php

declare(strict_types=1);

namespace Jield\Search\Solr\Expression;

/**
 * Group expression class
 *
 * Class representing expressions grouped together in the like of (term1 term2).
 */
class GroupExpression extends CompositeExpression
{
    public function __toString(): string
    {
        $part = parent::__toString();

        if (!$part) {
            return $part;
        }

        return '(' . $part . ')';
    }
}
