<?php
/**
 * MatchedJson
 *
 * @author Oskar Thornblad
 */

declare(strict_types=1);

namespace Prewk\Snapper\Ingredients\Json;

use Closure;
use Prewk\Snapper\BookKeeper;
use Prewk\Snapper\Exceptions\RecipeException;

/**
 * MatchedJson
 */
class MatchedJson
{
    const UNDEFINED_MODE = "UNDEFINED_MODE";
    const SINGLE_MODE = "SINGLE_MODE";
    const REGEXP_MODE = "REGEXP_MODE";
    const REPLACER_PLACEHOLDER = "%SnapperTextReplacerPlaceholder%";

    /**
     * @var string
     */
    private $mode = self::UNDEFINED_MODE;

    /**
     * @var string
     */
    private $refType;

    /**
     * @var array
     */
    private $optionalValues = [];

    /**
     * @var array
     */
    private $regexpHandlers = [];

    /**
     * Report the whole field value as a reference
     *
     * @param string $type
     * @return MatchedJson
     * @throws RecipeException
     */
    public function ref(string $type): self
    {
        if ($this->mode !== self::UNDEFINED_MODE) {
            throw new RecipeException("MatchedJson must use 1 call to ref OR {n} calls to regexp, they can't be combined");
        }

        $this->mode = self::SINGLE_MODE;
        $this->refType = $type;

        return $this;
    }

    /**
     * Specify which values should be treated as optional
     *
     * @param array ...$optionalValues
     * @return MatchedJson
     * @throws RecipeException
     */
    public function optional(...$optionalValues): self
    {
        if ($this->mode !== self::SINGLE_MODE) {
            throw new RecipeException("Optional values are only supported after calling MatchedJson#ref");
        }

        $this->optionalValues = $optionalValues;

        return $this;
    }

    /**
     * Parse the field value as text with references contained within
     *
     * @param string $pattern
     * @param Closure $regexpHandler
     * @return MatchedJson
     * @throws RecipeException
     */
    public function regexp(string $pattern, Closure $regexpHandler): self
    {
        if ($this->mode === self::SINGLE_MODE) {
            throw new RecipeException("MatchedJson must use 1 call to ref OR {n} calls to regexp, they can't be combined");
        }

        $this->mode = self::REGEXP_MODE;
        $this->regexpHandlers[$pattern] = $regexpHandler;

        return $this;
    }

    /**
     * Get all dependencies of this ingredient
     *
     * @param mixed $value
     * @return array Array of [type, id] tuples
     * @throws RecipeException
     */
    public function getDeps($value): array
    {
        switch ($this->mode) {
            case self::UNDEFINED_MODE:
                return [];
            case self::SINGLE_MODE:
                foreach ($this->optionalValues as $opt) {
                    if ($opt === $value) return [];
                }

                return isset($this->refType) ? [[$this->refType, $value]] : [];
            case self::REGEXP_MODE:
                $deps = [];

                foreach ($this->regexpHandlers as $pattern => $handler) {
                    $preg = preg_match_all($pattern, $value, $matches, PREG_SET_ORDER);
                    if ($preg === false || $preg === 0) continue;

                    foreach ($matches as $match) {
                        $replacer = $handler(new TextReplacer(self::REPLACER_PLACEHOLDER), $match, self::REPLACER_PLACEHOLDER);

                        if (!($replacer instanceof TextReplacer)) {
                            throw new RecipeException("MatchedJson regexp rule must return a TextReplacer");
                        }

                        $deps = array_merge($deps, $replacer->getDeps());
                    }
                }

                return $deps;
        }
    }

    /**
     * Convert JSON references into uuids
     *
     * @param $value
     * @param BookKeeper $books
     * @return string
     * @throws RecipeException
     */
    public function resolve($value, BookKeeper $books)
    {
        switch ($this->mode) {
            case self::UNDEFINED_MODE:
                return $value;
            case self::SINGLE_MODE:
                foreach ($this->optionalValues as $opt) {
                    if ($opt === $value) return $value;
                }

                return isset($this->refType) ? $books->resolveId($this->refType, $value) : $value;
            case self::REGEXP_MODE:
                $wip = $value;

                foreach ($this->regexpHandlers as $pattern => $handler) {
                    $preg = preg_match_all($pattern, $value, $matches, PREG_SET_ORDER);
                    if ($preg === false || $preg === 0) continue;

                    foreach ($matches as $match) {
                        $replacer = $handler(new TextReplacer(self::REPLACER_PLACEHOLDER), $match, self::REPLACER_PLACEHOLDER);

                        if (!($replacer instanceof TextReplacer)) {
                            throw new RecipeException("MatchedJson regexp rule must return a TextReplacer");
                        }

                        $wip = $replacer->resolve($wip, $books);
                    }
                }

                return $wip;
        }
    }
}