<?php

declare(strict_types=1);

namespace Jield\Search\Solr\Expression;

use Jield\Search\Solr\Util;
use Stringable;

/**
 * Class for search query phrases
 *
 * This class is specifically designed for handling quoted phrases in search queries.
 * It preserves spaces within the phrase and only escapes special characters.
 */
class SearchQueryPhraseExpression extends Expression implements Stringable
{
    public function __toString(): string
    {
        // Create a custom version of the phrase that preserves spaces
        $expr = $this->expr;

        // Escape special characters except spaces
        $search = [
            '\\', '+', '-', '&', '|', '!', '(', ')', '{', '}', '[', ']', '^', '"', '~', '*', '?', ':', '/'
        ];
        $replace = [
            '\\\\', '\+', '\-', '\&', '\|', '\!', '\(', '\)', '\{', '\}', '\[', '\]', '\^', '\"', '\~', '\*', '\?', '\:', '\/'
        ];

        // Remove any existing quotes to avoid double-quoting
        $expr = str_replace('"', '', $expr);

        $escaped = str_replace($search, $replace, $expr);

        // Return the phrase in quotes
        return '"' . $escaped . '"';
    }
}
