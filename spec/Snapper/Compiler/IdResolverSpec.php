<?php

namespace spec\Prewk\Snapper\Compiler;

use Prewk\Snapper\Compiler\IdResolver;
use PhpSpec\ObjectBehavior;
use Prewk\Snapper\Errors\CompilerException;
use Prophecy\Argument;

class IdResolverSpec extends ObjectBehavior
{
    function it_is_initializable()
    {
        $this->shouldHaveType(IdResolver::class);
    }

    function it_should_register_listeners()
    {
        $this->listen(0, [1, 2], function() {});

        $this->hasListener(0)->shouldBe(true);
    }

    function it_should_unregister_listeners()
    {
        $unregister = $this->listen(0, [1, 2], function() {});

        $this->hasListener(0)->shouldBe(true);

        $unregister();

        $this->hasListener(0)->shouldBe(false);
    }

    function it_should_detect_circular_deps()
    {
        $this->listen(0, [1, 2], function() {});

        $this->findCircularDeps(1, [0])->shouldBe([0]);
    }

    function it_should_refuse_to_cause_circular_deps()
    {
        $this->listen(0, [1, 2], function() {});

        $this->shouldThrow(CompilerException::class)->duringListen(1, [0], function() {});
    }
}
