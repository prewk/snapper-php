<?php
/**
 * PatternReplacer
 *
 * @author Oskar Thornblad
 */

declare(strict_types=1);

namespace Prewk\Snapper\Ingredients\Json;

use JsonSerializable;
use Prewk\Snapper\BookKeeper;
use Prewk\Snapper\Exceptions\RecipeException;

/**
 * PatternReplacer
 */
class PatternReplacer implements JsonSerializable
{
    /**
     * @var string
     */
    private $placeholder;

    /**
     * @var array
     */
    private $replacements = [];

    /**
     * PatternReplacer constructor
     *
     * @param string $placeholder
     */
    public function __construct(string $placeholder)
    {
        $this->placeholder = $placeholder;
    }

    /**
     * Tell the recipe about a reference in text and teach it how to search and replace it
     *
     * @param string $type
     * @param int $matchIndex
     * @param string $replace
     * @return PatternReplacer
     * @throws RecipeException
     */
    public function replace(string $type, int $matchIndex, string $replace): self
    {
        if (strpos($replace, $this->placeholder) === false) {
            throw new RecipeException("PatternReplacer#replace must include the \$replacement in the replace argument");
        }

        $this->replacements[] = [$type, $matchIndex, $replace];

        return $this;
    }

    /**
     * Get gathered refs
     *
     * @param array $matches
     * @return array
     */
    public function getDeps(array $matches): array
    {
        $deps = [];

        foreach ($this->replacements as list($type, $matchIndex)) {
            if (isset($matches[$matchIndex])) {
                $deps[] = [$type, $matches[$matchIndex]];
            }
        }

        return $deps;
    }

    /**
     * Search and replace the value using the previously given replacement data
     *
     * @param string $value
     * @param array $matches
     * @param BookKeeper $books
     * @return string
     */
    public function resolve(string $value, array $matches, BookKeeper $books): string
    {
        foreach ($this->replacements as list($type, $matchIndex, $replace)) {
            if (isset($matches[$matchIndex])) {
                $id = $matches[$matchIndex];
                $uuid = $books->resolveId($type, $id);
                $replaceUuid = str_replace($this->placeholder, $uuid, $replace);
                $search = str_replace($this->placeholder, $id, $replace);
                $value = str_replace($search, $replaceUuid, $value);
            }
        }

        return $value;
    }

    /**
     * Specify data which should be serialized to JSON
     * @link http://php.net/manual/en/jsonserializable.jsonserialize.php
     * @return mixed data which can be serialized by <b>json_encode</b>,
     * which is a value of any type other than a resource.
     * @since 5.4.0
     */
    public function jsonSerialize()
    {
        return [
            "placeholder" => $this->placeholder,
            "replacements" => $this->replacements,
        ];
    }

    /**
     * Create a PatternReplacer from an array, used for creating recipes from JSON
     *
     * @param array $config
     * @return PatternReplacer
     */
    public function fromArray(array $config): PatternReplacer
    {
        $replacer = new static($config["placeholder"]);

        foreach ($this->replacements as list($type, $matchIndex, $replace)) {
            $replacer->replace($type, $matchIndex, $replace);
        }

        return $replacer;
    }
}