<?php
/**
 * DeserializerEvent
 *
 * @author Oskar Thornblad
 */

declare(strict_types=1);

namespace Prewk\Snapper\Deserializer;

use Closure;

/**
 * Describes a DeserializerEvent
 */
abstract class DeserializerEvent
{
    /**
     * @var Closure
     */
    private $callback;

    /**
     * DeserializerEvent constructor
     *
     * @param Closure $callback
     */
    public function __construct(Closure $callback)
    {
        $this->callback = $callback;
    }

    /**
     * The deserializer calls the Event which in turn calls the callback
     *
     * @param mixed $data
     * @return mixed
     */
    public function call(...$data)
    {
        $callback = $this->callback;
        return $callback(...$data);
    }
}