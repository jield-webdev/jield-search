<?php

declare(strict_types=1);

namespace Jield\Search\Solr\Expression;

use DateTime;
use DateTimeZone;
use JetBrains\PhpStorm\Pure;
use Jield\Search\Solr\Expression\Exception\InvalidArgumentException;
use Jield\Search\Solr\ExpressionInterface;
use Jield\Search\Solr\Util;

use function array_filter;
use function array_pop;
use function array_shift;
use function array_unshift;
use function end;
use function func_get_args;
use function is_array;
use function is_bool;
use function is_iterable;
use function is_numeric;
use function is_object;
use function is_string;
use function trim;

class ExpressionBuilder
{
    private DateTimeZone|string $defaultTimezone = 'UTC';

    /**
     * Set default timezone for the Solr search server
     *
     * The default timezone is used to convert date queries. You can either
     * pass a string (like "Europe/Berlin") or a DateTimeZone object.
     *
     * @throws InvalidArgumentException
     */
    public function setDefaultTimezone(DateTimeZone|string $timezone): void
    {
        if (!is_string(value: $timezone) && !is_object(value: $timezone)) {
            throw InvalidArgumentException::invalidArgument(
                position:    1,
                name:        'timezone',
                expectation: ['string', DateTimeZone::class],
                actual:      $timezone
            );
        }

        $this->defaultTimezone = $timezone;
    }

    /**
     * Create phrase expression: "term1 term2"
     */
    #[Pure] public function phrase(?string $str): ?ExpressionInterface
    {
        if ($this->ignore(expr: $str)) {
            return null;
        }

        return new PhraseExpression(expr: $str);
    }

    private function ignore(mixed $expr): bool
    {
        return $expr === null || (is_string(value: $expr) && trim(string: $expr) === '');
    }

    /**
     * Create boost expression: <expr>^<boost>
     *
     * @param ExpressionInterface|string|null $expr
     */
    #[Pure] public function boost($expr, ?float $boost): ?ExpressionInterface
    {
        if ($this->ignore(expr: $expr) or $this->ignore(expr: $boost)) {
            return null;
        }

        return new BoostExpression(boost: $boost, expr: $expr);
    }

    /**
     * Create proximity match expression: "<word1> <word2>"~<proximity>
     *
     * @param int|mixed $proximity
     */
    public function prx(ExpressionInterface|string|null $word = null, $proximity = null): ?ExpressionInterface
    {
        $arguments        = func_get_args();
        $proximityElement = array_pop(array: $arguments);

        $arguments = $this->flatten(collection: $arguments);

        if (!$arguments) {
            return null;
        }

        return new ProximityExpression(words: $arguments, proximity: $proximityElement);
    }

    private function flatten($collection): array
    {
        $stack  = [$collection];
        $result = [];

        while (!empty($stack)) {
            $item = array_shift(array: $stack);

            if (is_iterable(value: $item)) {
                foreach ($item as $element) {
                    array_unshift($stack, $element);
                }
            } else {
                array_unshift($result, $item);
            }
        }

        return $result;
    }

    /**
     * Create fuzzy expression: <expr>~<similarity>
     *
     * @param ExpressionInterface|string|null $expr
     * @param float $similarity Similarity between 0.0 und 1.0
     */
    #[Pure] public function fzz($expr, ?float $similarity = null): ?ExpressionInterface
    {
        if ($this->ignore(expr: $expr)) {
            return null;
        }

        return new FuzzyExpression(expr: $expr, similarity: $similarity);
    }

    /**
     * Range query expression (exclusive start/end): {start TO end}
     */
    #[Pure] public function btwnRange(
        ExpressionInterface|float|int|string|null $start = null,
        ExpressionInterface|float|int|string|null $end = null
    ): ExpressionInterface {
        return new RangeExpression(start: $start, end: $end, inclusive: false);
    }

    /**
     * Create wildcard expression: <prefix>?, <prefix>*, <prefix>?<suffix> or <prefix>*<suffix>
     *
     * @param ExpressionInterface|string $prefix
     */
    public function wild(
        ?string $wildcard = '*',
        ExpressionInterface|string $suffix = '*'
    ): ?ExpressionInterface {
        $wildcard = strtolower(string: (string)$wildcard);
        //$wildcard = str_replace(' ', '\ ', $wildcard);

        $wildcard = Util::escape(value: $wildcard);
        $prefix   = '*';

        if (($this->ignore(expr: $prefix) && $this->ignore(expr: $suffix)) || $this->ignore(expr: $wildcard)) {
            return null;
        }

        return new WildcardExpression(wildcard: $wildcard, prefix: $prefix, suffix: $suffix);
    }

    /**
     * Create bool, prohibited expression using the NOT notation, usable in OR/AND expressions:
     * (*:* NOT <expr>), e.g. (*:* NOT fieldName:*)
     *
     * @param ExpressionInterface|string|null $expr
     * @return ExpressionInterface|null
     */
    #[Pure] public function not($expr): BooleanExpression|ExpressionInterface|null
    {
        if ($this->ignore(expr: $expr)) {
            return null;
        }

        return new BooleanExpression(
            operator: BooleanExpression::OPERATOR_PROHIBITED, expr: $expr, useNotNotation: true
        );
    }

    /**
     * Create bool expression
     *
     *      true => required (+)
     *      false => prohibited (-)
     *      null => neutral (<empty>)
     *
     * @param ExpressionInterface|string|null $expr
     * @param bool|null $operator @codingStandardsIgnoreLine
     * @return ExpressionInterface|null
     */
    public function bool(
        $expr,
        $operator
    ): BooleanExpression|ExpressionInterface|string|null { // @codingStandardsIgnoreLine
        if ($operator === null) {
            return $expr;
        }

        if ($operator) {
            return $this->req(expr: $expr);
        } else {
            return $this->prhb(expr: $expr);
        }
    }

    /**
     * Create bool, required expression: +<expr>
     *
     * @param ExpressionInterface|string|null $expr
     * @return ExpressionInterface|null
     */
    #[Pure] public function req($expr): BooleanExpression|ExpressionInterface|null
    {
        if ($this->ignore(expr: $expr)) {
            return null;
        }

        return new BooleanExpression(operator: BooleanExpression::OPERATOR_REQUIRED, expr: $expr);
    }

    /**
     * Create bool, prohibited expression: -<expr>
     *
     * @param ExpressionInterface|string|null $expr
     * @return ExpressionInterface|null
     */
    #[Pure] public function prhb($expr): BooleanExpression|ExpressionInterface|null
    {
        if ($this->ignore(expr: $expr)) {
            return null;
        }

        return new BooleanExpression(operator: BooleanExpression::OPERATOR_PROHIBITED, expr: $expr);
    }

    /**
     * Create AND grouped expression: (<expr1> AND <expr2> AND <expr3>)
     *
     * @param ExpressionInterface[]|string[] $args
     */
    public function andX(...$args): ?ExpressionInterface
    {
        $args = $this->parseCompositeArgs(args: $args)[0];

        if (!$args) {
            return null;
        }

        return new GroupExpression(expressions: $args, type: CompositeExpression::TYPE_AND);
    }

    /**
     * @param mixed[] $args
     * @return mixed[]
     */
    private function parseCompositeArgs(array $args): array
    {
        $args = $this->flatten(collection: $args);
        $type = CompositeExpression::TYPE_SPACE;

        if (CompositeExpression::isValidType(type: end(array: $args))) {
            $type = array_pop(array: $args);
        }

        $args = array_filter(array: $args, callback: $this->permit(...));

        if (!$args) {
            return [false, $type];
        }

        return [$args, $type];
    }

    /**
     * Create OR grouped expression: (<expr1> OR <expr2> OR <expr3>)
     *
     * @param array|ExpressionInterface[]|string[] $args
     */
    public function orX(...$args): ?ExpressionInterface
    {
        $args = $this->parseCompositeArgs(args: $args)[0];

        if (!$args) {
            return null;
        }

        return new GroupExpression(expressions: $args, type: CompositeExpression::TYPE_OR);
    }

    /**
     * Returns a query "*:*" which means find all if $expr is empty
     *
     * @param ExpressionInterface|string|null $expr
     */
    public function all($expr = null): mixed
    {
        if ($this->permit(expr: $expr)) {
            return $expr;
        }

        return $this->field(field: $this->lit(expr: '*'), expr: $this->lit(expr: '*'));
    }

    #[Pure] private function permit(mixed $expr): bool
    {
        return !$this->ignore(expr: $expr);
    }

    /**
     * Create field expression: <field>:<expr>
     * of in an array $expr is given: <field>:(<expr1> <expr2> <expr3>...)
     */
    #[Pure] public function field(
        ExpressionInterface|string $field,
        array|ExpressionInterface|string|null|int|bool $expr
    ): ?ExpressionInterface {
        if (is_array(value: $expr)) {
            $expr = $this->grp(expr: $expr);
        } elseif ($this->ignore(expr: $expr)) {
            return null;
        }

        return new FieldExpression(field: $field, expr: $expr);
    }

    /**
     * Create grouped expression: (<expr1> <expr2> <expr3>)
     *
     * @param ExpressionInterface|string|null $expr
     * @param string|mixed $type
     */
    #[Pure] public function grp($expr = null, $type = CompositeExpression::TYPE_SPACE): ?ExpressionInterface
    {
        if (empty($expr)) {
            return null;
        }

        return new GroupExpression(expressions: $expr, type: $type);
    }

    /**
     * Return string treated as literal (unescaped, unquoted)
     *
     * @param ExpressionInterface|string|null $expr
     */
    #[Pure] public function lit($expr): ?ExpressionInterface
    {
        if ($this->ignore(expr: $expr)) {
            return null;
        }

        return new Expression(expr: $expr);
    }

    #[Pure] public function number($field, $expr): ?ExpressionInterface
    {
        if (!is_numeric(value: $expr)) {
            return null;
        }

        return new FieldExpression(field: $field, expr: $this->eq(expr: $expr));
    }

    /**
     * Create term expression: <expr>
     *
     * @param ExpressionInterface|string|null $expr
     */
    #[Pure] public function eq($expr): ?ExpressionInterface
    {
        if ($this->ignore(expr: $expr)) {
            return null;
        }

        if ($expr instanceof ExpressionInterface) {
            return $expr;
        }

        return new PhraseExpression(expr: $expr);
    }

    /**
     * Create a date expression for a specific day
     *
     * @param DateTime|mixed $date
     */
    #[Pure] public function day($date = null): ?ExpressionInterface
    {
        if (!$date instanceof DateTime) {
            return null;
        }

        return $this->range(start: $this->startOfDay(date: $date), end: $this->endOfDay(date: $date));
    }

    /**
     * Range query expression (inclusive start/end): [start TO end]
     */
    #[Pure] public function range(
        ExpressionInterface|float|int|string|null $start = null,
        ExpressionInterface|float|int|string|null $end = null,
        bool $inclusive = true
    ): ExpressionInterface {
        return new RangeExpression(start: $start, end: $end, inclusive: $inclusive);
    }

    /**
     * Expression for the start of the given date
     */
    #[Pure] public function startOfDay(?DateTime $date = null, bool|string $timezone = false): ?ExpressionInterface
    {
        if ($date === null) {
            return null;
        }

        return new DateTimeExpression(
            date:     $date,
            format:   DateTimeExpression::FORMAT_START_OF_DAY,
            timezone: $timezone === false ? $this->defaultTimezone : $timezone
        );
    }

    /**
     * Expression for the end of the given date
     */
    #[Pure] public function endOfDay(?DateTime $date = null, bool|string $timezone = false): ?ExpressionInterface
    {
        if (!$date) {
            return null;
        }

        return new DateTimeExpression(
            date:     $date,
            format:   DateTimeExpression::FORMAT_END_OF_DAY,
            timezone: $timezone === false ? $this->defaultTimezone : $timezone
        );
    }

    /**
     * Create a range between two dates (one side may be unlimited which is indicated by passing null)
     */
    #[Pure] public function dateRange(
        ?DateTime $from = null,
        ?DateTime $to = null,
        bool $inclusive = true,
        bool|string $timezone = 'Europe/Amsterdam'
    ): ?ExpressionInterface {
        if ($from === null && $to === null) {
            return null;
        }

        return $this->range(
            start:     $this->lit(expr: $this->date(date: $from, timezone: $timezone)),
            end:       $this->lit(expr: $this->date(date: $to, timezone: $timezone)),
            inclusive: $inclusive
        );
    }

    #[Pure] public function date(?DateTime $date = null, bool|string $timezone = false): ExpressionInterface
    {
        if ($date === null) {
            return $this->lit(expr: '*');
        }

        return new DateTimeExpression(
            date:     $date,
            format:   DateTimeExpression::FORMAT_DEFAULT,
            timezone: $timezone === false ? $this->defaultTimezone : $timezone
        );
    }

    /**
     * Create a function expression of name $function
     *
     * You can either pass an array of parameters, a single parameter or a ParameterExpression
     *
     * @param array|string|null $parameters
     */
    #[Pure] public function func(string $function, null|array|string $parameters = null): ExpressionInterface
    {
        return new FunctionExpression(function: $function, parameters: $parameters);
    }

    /**
     * Create a function parameters expression
     */
    public function params(mixed ...$parameters): ExpressionInterface
    {
        $parameters = $this->flatten(collection: $parameters);

        return new ParameterExpression(parameters: $parameters);
    }

    /**
     * @param mixed[]|mixed $params
     * @param bool|mixed $shortForm
     */
    public function localParams(string $type, $params = [], $shortForm = true): ?ExpressionInterface
    {
        $additional = null;

        if (!is_bool(value: $shortForm)) {
            $additional = $shortForm;
            $shortForm  = true;
        } elseif (!is_array(value: $params)) {
            $additional = $params;
            $params     = [];
        }

        if ($additional !== null) {
            return $this->comp(
                expr: new LocalParamsExpression(type: $type, params: $params, shortForm: $shortForm),
                type: $additional
            );
        }

        return new LocalParamsExpression(type: $type, params: $params, shortForm: $shortForm);
    }

    /**
     * Create composite expression: <expr1> <expr2> <expr3>
     *
     * @param ExpressionInterface|string|null $expr
     */
    public function comp($expr = null, ?string $type = CompositeExpression::TYPE_SPACE): ?ExpressionInterface
    {
        [$args, $type] = $this->parseCompositeArgs(args: func_get_args());

        if (!$args) {
            return null;
        }

        return new CompositeExpression(expressions: $args, type: $type);
    }

    /** @param mixed[] $additionalParams */
    #[Pure] public function geofilt(
        string $field,
        ?GeolocationExpression $geolocation = null,
        ?int $distance = null,
        array $additionalParams = []
    ): ExpressionInterface {
        return new GeofiltExpression(
            field:            $field,
            geolocation:      $geolocation,
            distance:         $distance,
            additionalParams: $additionalParams
        );
    }

    /**
     * Create a geo location expression: "<latitude>,<longitude>" using the given precision
     */
    #[Pure] public function latLong(float $latitude, float $longitude, int $precision = 12): ExpressionInterface
    {
        return new GeolocationExpression(latitude: $latitude, longitude: $longitude, precision: $precision);
    }

    /** @param string|ExpressionInterface|null $expr */
    public function noCache($expr = null): ?ExpressionInterface
    {
        if ($this->ignore(expr: $expr)) {
            return null;
        }

        return $this->comp(expr: [$this->shortLocalParams(tag: 'cache', value: false), $expr], type: null);
    }

    #[Pure] private function shortLocalParams(ExpressionInterface|string $tag, mixed $value): LocalParamsExpression
    {
        return new LocalParamsExpression(type: $tag, params: [$tag => $value], shortForm: true);
    }

    /** @param string|ExpressionInterface|null $expr */
    public function tag(string $tagName, $expr = null): ?ExpressionInterface
    {
        if ($this->ignore(expr: $expr)) {
            return null;
        }

        return $this->comp(expr: [$this->shortLocalParams(tag: 'tag', value: $tagName), $expr], type: null);
    }

    /** @param string|ExpressionInterface|null $expr */
    public function excludeTag(string $tagName, $expr = null): ?ExpressionInterface
    {
        if ($this->ignore(expr: $expr)) {
            return null;
        }

        return $this->comp(expr: [$this->shortLocalParams(tag: 'ex', value: $tagName), $expr], type: null);
    }

    /**
     * Create a search query expression following Google-like rules:
     * - Strings between quotes are searched as full phrases
     * - Non-quoted parts are searched as individual words
     *
     * @param string $searchQuery The search query string
     * @return ExpressionInterface|null The resulting expression
     */
    public function searchQuery(string $searchQuery): ?ExpressionInterface
    {
        if ($this->ignore(expr: $searchQuery)) {
            return new Expression(expr: '*');
        }

        $searchQuery = trim(string: $searchQuery);

        if ($searchQuery === '*') {
            return new Expression(expr: '*');
        }

        // If the entire query is already quoted and doesn't contain any other quotes, treat it as a phrase
        if (strlen($searchQuery) >= 2 && $searchQuery[0] === '"' && $searchQuery[strlen(
                $searchQuery
            ) - 1] === '"' && substr_count($searchQuery, '"') === 2) {
            $content = substr($searchQuery, 1, -1);
            if (empty(trim($content))) {
                return null;
            }
            return new SearchQueryPhraseExpression(expr: $content);
        }

        // Use a regex-based approach to handle quoted phrases and individual words
        $expressions = [];

        // First, handle quoted phrases (including unclosed quotes)
        $pattern = '/"([^"]*)"?/';
        preg_match_all($pattern, $searchQuery, $matches);

        // Add each quoted phrase as a SearchQueryPhraseExpression
        foreach ($matches[1] as $phrase) {
            if (!empty(trim($phrase))) {
                $expressions[] = new SearchQueryPhraseExpression(expr: $phrase);
            }
        }

        // Remove all quoted phrases from the search query
        $remainingText = preg_replace($pattern, '', $searchQuery);

        // Handle remaining non-quoted words
        $words = preg_split('/\s+/', trim($remainingText), -1, PREG_SPLIT_NO_EMPTY);
        foreach ($words as $word) {
            // Escape special characters according to SOLR rules
            $escapedWord   = Util::escape(value: $word);
            $expressions[] = new Expression(expr: $escapedWord);
        }

        // If no expressions were created, return null
        if (empty($expressions)) {
            return null;
        }

        // If only one expression, return it directly
        if (count($expressions) === 1) {
            return $expressions[0];
        }

        // Otherwise, combine expressions with OR
        return new GroupExpression(expressions: $expressions, type: CompositeExpression::TYPE_OR);
    }

    /**
     * Add words from a string to the expressions array
     *
     * @param array $expressions Array to add the resulting expressions to
     * @param string $text The text to process
     */
    private function addWordsToExpressions(array &$expressions, string $text): void
    {
        $words = preg_split('/\s+/', trim($text), -1, PREG_SPLIT_NO_EMPTY);

        foreach ($words as $word) {
            if (!empty($word)) {
                // Escape special characters according to SOLR rules
                $escapedWord   = Util::escape(value: $word);
                $expressions[] = new Expression(expr: $escapedWord);
            }
        }
    }

    /**
     * Create an advanced search query expression with field-specific search, wildcard options, and boosting
     *
     * @param string $searchQuery The search query string
     * @param array $fields Array of fields to search on, with optional boosting values as keys
     * @param bool $useWildcards Whether to use wildcards for non-phrase terms
     * @param array $boostFields Array of fields to boost with boost values
     * @return ExpressionInterface|null The resulting expression
     */
    public function advancedSearchQuery(
        ?string $searchQuery,
        array $fields = [],
        array $boostFields = []
    ): ?ExpressionInterface {
        // First, get the base search query expression
        $baseExpression = $this->searchQuery(searchQuery: $searchQuery ?? '*');


        if ($baseExpression === null) {
            return null;
        }

        // If no fields specified, return the base expression
        if (empty($fields)) {
            return $baseExpression;
        }

        // Create field-specific expressions
        $fieldExpressions = [];

        foreach ($fields as $field) {
            // If the field is a numeric key, it means no boost was specified
            $fieldBoost = $boostFields[$field] ?? null;

            // Check for _sort suffix - use wildcards and medium boost (between 1 and 3)
            // If no boost was specified, use a medium boost value (2)
            if (str_ends_with($field, '_sort') && $fieldBoost === null) {
                $fieldBoost = 2.0;
            } // Check for _search suffix - use wildcards but no boost

            // Create a copy of the base expression for this field
            $fieldExpression = $this->processExpressionForField(
                $baseExpression,
                $field,
                $fieldBoost
            );

            if ($fieldExpression !== null) {
                $fieldExpressions[] = $fieldExpression;
            }
        }

        // If no field expressions were created, return null
        if (empty($fieldExpressions)) {
            return null;
        }

        // If only one field expression, return it directly
        if (count($fieldExpressions) === 1) {
            return $fieldExpressions[0];
        }

        // Otherwise, combine field expressions with OR
        return new GroupExpression(expressions: $fieldExpressions, type: CompositeExpression::TYPE_OR);
    }

    /**
     * Process an expression for a specific field, applying wildcards and boosting as needed
     *
     * @param ExpressionInterface $expression The expression to process
     * @param string $field The field to search on
     * @param bool $useWildcards Whether to use wildcards
     * @param float|null $boost The boost value to apply
     * @return ExpressionInterface|null The processed expression
     */
    private function processExpressionForField(
        ExpressionInterface $expression,
        string $field,
        ?float $boost = null
    ): ?ExpressionInterface {
        // Handle different expression types
        if ($expression instanceof GroupExpression) {
            // For group expressions, process each sub-expression
            $subExpressions = [];

            // Get the expressions from the group
            $groupExprStr = (string)$expression;
            $groupType    = (str_contains(
                $groupExprStr,
                ' OR '
            ) ? CompositeExpression::TYPE_OR : CompositeExpression::TYPE_AND);

            // Extract expressions from the group
            if (preg_match('/^\((.*)\)$/', $groupExprStr, $matches)) {
                $content = $matches[1];

                // Split by the group type
                $separator = $groupType === CompositeExpression::TYPE_OR ? ' OR ' :
                    ($groupType === CompositeExpression::TYPE_AND ? ' AND ' : ' ');

                $parts = explode($separator, $content);

                foreach ($parts as $part) {
                    if (trim($part) === '') {
                        continue;
                    }

                    // Create appropriate expression based on the part
                    if (strpos($part, '"') === 0 && strrpos($part, '"') === strlen($part) - 1) {
                        // It's a phrase
                        $subExpr = new SearchQueryPhraseExpression(expr: substr($part, 1, -1));
                    } else {
                        // It's a regular expression
                        $subExpr = new Expression(expr: $part);
                    }

                    // Apply wildcards if needed for non-phrase expressions
                    if (!($subExpr instanceof SearchQueryPhraseExpression)) {
                        $exprStr = (string)$subExpr;


                        $subExpr = new WildcardExpression(wildcard: $exprStr, prefix: '*', suffix: '*');
                    }

                    // Create a field expression for this sub-expression
                    $subExpressions[] = new FieldExpression(field: $field, expr: $subExpr);
                }
            }

            // If no sub-expressions were created, return null
            if (empty($subExpressions)) {
                return null;
            }

            // Create a new group with the processed sub-expressions
            $fieldExpression = new GroupExpression(expressions: $subExpressions, type: $groupType);

            // Apply boost if specified
            if ($boost !== null) {
                return new BoostExpression(boost: $boost, expr: $fieldExpression);
            }

            return $fieldExpression;
        }

        if ($expression instanceof SearchQueryPhraseExpression) {
            // For phrase expressions, create a field expression with the phrase
            $fieldExpression = new FieldExpression(field: $field, expr: $expression);
            // Apply boost if specified

        } else {
            // For regular expressions, apply wildcards if needed
            // Create a wildcard expression
            $exprStr         = (string)$expression;
            $wildcardExpr    = new WildcardExpression(wildcard: $exprStr, prefix: '*', suffix: '*');
            $fieldExpression = new FieldExpression(field: $field, expr: $wildcardExpr);
        }
        if ($boost !== null) {
            return new BoostExpression(boost: $boost, expr: $fieldExpression);
        }
        return $fieldExpression;
    }
}
