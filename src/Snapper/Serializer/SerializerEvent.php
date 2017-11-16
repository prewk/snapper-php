<?php
/**
 * SerializerEvent
 *
 * @author Oskar Thornblad
 */

declare(strict_types=1);

namespace Prewk\Snapper\Serializer;

use Closure;

/**
 * Describes a SerializerEvent
 */
abstract class SerializerEvent
{
    /**
     * @var Closure
     */
    private $callback;

    /**
     * SerializerEvent constructor
     *
     * @param Closure $callback
     */
    public function __construct(Closure $callback)
    {
        $this->callback = $callback;
    }

    /**
     * The serializer calls the Event which in turn calls the callback
     *
     * @param array $data
     * @return mixed
     */
    public function call(...$data)
    {
        $callback = $this->callback;
        return $callback(...$data);
    }
}