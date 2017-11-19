<?php
/**
 * JsonRecipe
 *
 * @author Oskar Thornblad
 */

declare(strict_types=1);

namespace Prewk\Snapper\Ingredients\Json;

use Closure;
use Illuminate\Support\Arr;
use JsonSerializable;
use Prewk\Option;
use Prewk\Option\{None, Some};
use Prewk\Snapper\BookKeeper;
use Prewk\Snapper\Exceptions\RecipeException;

/**
 * JsonRecipe
 */
class JsonRecipe implements JsonSerializable
{
    /**
     * @var array
     */
    private $patterns = [];

    /**
     * @var array
     */
    private $paths = [];

    /**
     * Match references on a given RegExp dot-path
     *
     * @param string $pattern
     * @param Closure $matchedJson
     * @return JsonRecipe
     */
    public function pattern(string $pattern, Closure $matchedJson): self
    {
        $this->patterns[$pattern] = $matchedJson;

        return $this;
    }

    /**
     * Match references on a given dot-path
     *
     * @param string $path
     * @param Closure $matchedJson
     * @return JsonRecipe
     */
    public function path(string $path, Closure $matchedJson): self
    {
        $this->paths[$path] = $matchedJson;

        return $this;
    }

    /**
     * Get all dependencies of this ingredient
     *
     * @param mixed $value
     * @param array $row
     * @param bool $circular
     * @return array Array of [type, id] tuples
     * @throws RecipeException
     */
    public function getDeps($value, array $row, bool $circular = false): array
    {
        if (!is_string($value)) {
            return [];
        }

        $decoded = json_decode($value, true);
        $dot = Arr::dot($decoded);
        $deps = [];

        foreach ($dot as $srcPath => $pathValue) {
            if (array_key_exists($srcPath, $this->paths)) {
                $handler = $this->paths[$srcPath];
                $matchedJson = $handler(new MatchedJson);

                if (!($matchedJson instanceof MatchedJson)) {
                    throw new RecipeException("The JSON path rule must return a MatchedJson");
                }

                $deps = array_merge($deps, $matchedJson->getDeps($pathValue));
            }

            foreach ($this->patterns as $pattern => $handler) {
                if (preg_match($pattern, $srcPath) === 1) {
                    $matchedJson = $handler(new MatchedJson);

                    if (!($matchedJson instanceof MatchedJson)) {
                        throw new RecipeException("The JSON pattern rule must return a MatchedJson");
                    }

                    $deps = array_merge($deps, $matchedJson->getDeps($pathValue));
                }
            }
        }

        return $deps;
    }

    /**
     * Let the ingredient determine the value of the field
     *
     * @param $value
     * @param array $row
     * @param BookKeeper $books
     * @param bool $circular
     * @return Option
     * @throws RecipeException
     */
    public function serialize($value, array $row, BookKeeper $books, bool $circular = false): Option
    {
        if (!is_string($value)) {
            return new None;
        }

        $decoded = json_decode($value, true);
        $dot = Arr::dot($decoded);

        foreach ($dot as $srcPath => $pathValue) {
            if (array_key_exists($srcPath, $this->paths)) {
                $handler = $this->paths[$srcPath];
                $matchedJson = $handler(new MatchedJson);

                if (!($matchedJson instanceof MatchedJson)) {
                    throw new RecipeException("The JSON path rule must return a MatchedJson");
                }

                Arr::set($decoded, $srcPath, $matchedJson->resolve($pathValue, $books));
            }

            foreach ($this->patterns as $pattern => $handler) {
                if (preg_match($pattern, $srcPath) === 1) {
                    $matchedJson = $handler(new MatchedJson);

                    if (!($matchedJson instanceof MatchedJson)) {
                        throw new RecipeException("The JSON pattern rule must return a MatchedJson");
                    }

                    Arr::set($decoded, $srcPath, $matchedJson->resolve($pathValue, $books));
                }
            }
        }

        return new Some(json_encode($decoded, JSON_UNESCAPED_SLASHES));
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
        $paths = [];
        foreach ($this->paths as $path => $handler) {
            $matchedJson = $handler(new MatchedJson);
            $paths[$path] = $matchedJson->jsonSerialize();
        }

        $patterns = [];
        foreach ($this->patterns as $pattern => $handler) {
            $matchedJson = $handler(new MatchedJson);
            $patterns[$pattern] = $matchedJson->jsonSerialize();
        }

        return [
            "paths" => $paths,
            "patterns" => $patterns,
        ];
    }

    /**
     * Create a JsonRecipe from an array, used for creating recipes from JSON
     *
     * @param array $config
     * @return JsonRecipe
     */
    public static function fromArray(array $config): JsonRecipe
    {
        $json = new static;

        foreach ($config["paths"] as $path => $handler) {
            $json->path($path, MatchedJson::fromArray($handler));
        }

        foreach ($config["patterns"] as $pattern => $handler) {
            $json->pattern($pattern, MatchedJson::fromArray($handler));
        }

        return $json;
    }
}