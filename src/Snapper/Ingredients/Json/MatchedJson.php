<?php
/**
 * MatchedJson
 *
 * @author Oskar Thornblad
 */

declare(strict_types=1);

namespace Prewk\Snapper\Ingredients\Json;

use Closure;
use JsonSerializable;
use Prewk\Snapper\BookKeeper;
use Prewk\Snapper\Exceptions\RecipeException;
use stdClass;

/**
 * MatchedJson
 */
class MatchedJson implements JsonSerializable
{
    const UNDEFINED_MODE = "UNDEFINED_MODE";
    const SINGLE_MODE = "SINGLE_MODE";
    const PATTERN_MODE = "PATTERN_MODE";
    const REPLACER_PLACEHOLDER = "%SnapperPatternReplacerPlaceholder%";

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
    private $patternHandlers = [];

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
            throw new RecipeException("MatchedJson must use 1 call to ref OR {n} calls to pattern, they can't be combined");
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
     * @param Closure $patternReplacer
     * @return MatchedJson
     * @throws RecipeException
     */
    public function pattern(string $pattern, Closure $patternReplacer): self
    {
        if ($this->mode === self::SINGLE_MODE) {
            throw new RecipeException("MatchedJson must use 1 call to ref OR {n} calls to pattern, they can't be combined");
        }

        $this->mode = self::PATTERN_MODE;
        $this->patternHandlers[$pattern] = $patternReplacer;

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
            case self::PATTERN_MODE:
                $deps = [];

                foreach ($this->patternHandlers as $pattern => $handler) {
                    $preg = preg_match_all($pattern, (string)$value, $matches, PREG_SET_ORDER);
                    if ($preg === false || $preg === 0) continue;

                    $replacer = $handler(new PatternReplacer(self::REPLACER_PLACEHOLDER), self::REPLACER_PLACEHOLDER);

                    if (!($replacer instanceof  PatternReplacer)) {
                        throw new RecipeException("MatchedJson pattern rule must return a PatternReplacer");
                    }

                    foreach ($matches as $match) {
                        $deps = array_merge($deps, $replacer->getDeps($match));
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
            case self::PATTERN_MODE:
                $wip = $value;

                foreach ($this->patternHandlers as $pattern => $handler) {
                    $preg = preg_match_all($pattern, (string)$value, $matches, PREG_SET_ORDER);
                    if ($preg === false || $preg === 0) continue;

                    $replacer = $handler(new PatternReplacer(self::REPLACER_PLACEHOLDER), self::REPLACER_PLACEHOLDER);

                    if (!($replacer instanceof  PatternReplacer)) {
                        throw new RecipeException("MatchedJson pattern rule must return a PatternReplacer");
                    }

                    foreach ($matches as $match) {
                        $wip = $replacer->resolve($wip, $match, $books);
                    }
                }

                return $wip;
        }
    }

    /**
     * Specify data which should be serialized to JSON
     * @link http://php.net/manual/en/jsonserializable.jsonserialize.php
     * @return mixed data which can be serialized by <b>json_encode</b>,
     * which is a value of any type other than a resource.
     * @since 5.4.0
     */
    function jsonSerialize()
    {
        switch ($this->mode) {
            case self::UNDEFINED_MODE;
                return [
                    "mode" => self::UNDEFINED_MODE,
                    "ref_type" => null,
                    "optional_values" => [],
                    "pattern_handlers" => new stdClass,
                ];
            case self::SINGLE_MODE:
                return [
                    "mode" => self::SINGLE_MODE,
                    "ref_type" => $this->refType,
                    "optional_values" => $this->optionalValues,
                    "pattern_handlers" => new stdClass,
                ];
            case self::PATTERN_MODE:
                $handlers = [];
                foreach ($this->patternHandlers as $pattern => $handler) {
                    $replacer = $handler(new PatternReplacer(self::REPLACER_PLACEHOLDER), self::REPLACER_PLACEHOLDER);
                    $handlers[$pattern] = $replacer->jsonSerialize();
                }

                return [
                    "mode" => self::PATTERN_MODE,
                    "ref_type" => null,
                    "optional_values" => $this->optionalValues,
                    "pattern_handlers" => $handlers,
                ];
        }
    }

    /**
     * Create a MatchedJson from an array, used for creating recipes from JSON
     *
     * @param array $config
     * @return MatchedJson
     */
    public function fromArray(array $config): MatchedJson
    {
        switch ($config["mode"]) {
            default:
            case self::UNDEFINED_MODE:
                return new static;
            case self::SINGLE_MODE:
                return (new static)
                    ->ref($config["ref_type"])
                    ->optional(...$config["optional_values"]);
            case self::PATTERN_MODE:
                $matched = (new static)
                    ->optional(...$config["optional_values"]);

                foreach ($config["pattern_handlers"] as $pattern => $handler) {
                    $matched->pattern($pattern, PatternReplacer::fromArray($handler));
                }

                return $matched;
        }
    }
}