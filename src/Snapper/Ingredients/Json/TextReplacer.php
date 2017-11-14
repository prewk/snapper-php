<?php
/**
 * TextReplacer
 *
 * @author Oskar Thornblad
 */

declare(strict_types=1);

namespace Prewk\Snapper\Ingredients\Json;
use Prewk\Snapper\BookKeeper;
use Prewk\Snapper\Exceptions\RecipeException;

/**
 * TextReplacer
 */
class TextReplacer
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
     * TextReplacer constructor
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
     * @param $id
     * @param string $search
     * @param string $replace
     * @return TextReplacer
     * @throws RecipeException
     */
    public function replace(string $type, $id, string $search, string $replace): self
    {
        if (strpos($replace, $this->placeholder) === false) {
            throw new RecipeException("TextReplacer#replace must include the \$replacement in the replace argument");
        }

        $this->replacements[] = [$type, $id, $search, $replace];

        return $this;
    }

    /**
     * Get gathered refs
     *
     * @return array
     */
    public function getDeps(): array
    {
        return array_map(function(array $replacement) {
            list($type, $id) = $replacement;

            return [$type, $id];
        }, $this->replacements);
    }

    /**
     * Search and replace the value using the previously given replacement data
     *
     * @param string $value
     * @param BookKeeper $books
     * @return string
     */
    public function resolve(string $value, BookKeeper $books): string
    {
        foreach ($this->replacements as list($type, $id, $search, $replace)) {
            $uuid = $books->resolveId($type, $id);
            $replaceUuid = str_replace($this->placeholder, $uuid, $replace);

            $value = str_replace($search, $replaceUuid, $value);
        }

        return $value;
    }
}