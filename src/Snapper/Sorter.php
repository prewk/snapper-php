<?php
/**
 * Sorter
 *
 * @author Oskar Thornblad
 */

declare(strict_types=1);

namespace Prewk\Snapper;

use Exception;
use MJS\TopSort\ElementNotFoundException;
use MJS\TopSort\Implementations\StringSort;
use Prewk\Result;
use Prewk\Result\{Err, Ok};
use Prewk\Snapper\Exceptions\IntegrityException;
use Prewk\Snapper\Serializer\SerializationBookKeeper;

/**
 * Sorter
 */
class Sorter extends StringSort
{
    /**
     * Make a new empty Sorter
     *
     * @return Sorter
     */
    public function make(): Sorter
    {
        return new static([], $this->throwCircularDependency);
    }

    /**
     * Sorts dependencies and returns a Result array of strings with sorted elements or a meaningful error
     *
     * @param SerializationBookKeeper $books
     * @return Result Result<Array, IntegrityException>
     */
    public function sortWithBookkeeping(SerializationBookKeeper $books): Result
    {
        try {
            $ordered = self::sort();
        } catch (ElementNotFoundException $e) {
            $sourceUuid = $e->getSource();
            $targetUuid = $e->getTarget();

            $source = $books->getPairByUuid($sourceUuid);
            $target = $books->getPairByUuid($targetUuid);

            if ($source->isSome() && $target->isSome()) {
                list($sourceType, $sourceId) = $source->unwrap();
                list($targetType, $targetId) = $target->unwrap();
                $msg = "A row ($sourceType/$sourceId) required a missing row ($targetType/$targetId)";
            } else if ($source->isSome()) {
                list($sourceType, $sourceId) = $source->unwrap();
                $msg = "A row ($sourceType/$sourceId) required a missing unknown row";
            } else if ($target->isSome()) {
                list($targetType, $targetId) = $target->unwrap();
                $msg = "An unknown row required a row ($targetType/$targetId)";
            } else {
                $msg = "Encountered an unknown row with another unknown row as dependency";
            }

            return new Err(new IntegrityException($msg));
        } catch (Exception $e) {
            return new Err($e);
        }

        return new Ok($ordered);
    }
}