<?php

declare(strict_types=1);

namespace Jield\Search\Solr\Expression;

use JetBrains\PhpStorm\Pure;
use Jield\Search\Solr\Util;
use Stringable;

use function array_map;
use function implode;

class ParameterExpression extends Expression implements Stringable
{
    /** @param mixed[] $parameters */
    public function __construct(private readonly array $parameters)
    {
    }

    public function __toString(): string
    {
        $parameters = array_map($this->replaceNull(...), $this->parameters);

        return implode(', ', array_map(Util::sanitize(...), $parameters));
    }

    #[Pure] private function replaceNull(mixed $value): mixed
    {
        return $value ?? new PhraseExpression('');
    }
}
