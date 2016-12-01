<?php

namespace spec\Prewk\Snapper\Compiler;

use Prewk\Snapper\Compiler\IdMaker;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class IdMakerSpec extends ObjectBehavior
{
    function it_is_initializable()
    {
        $this->shouldHaveType(IdMaker::class);
    }

    function it_should_make_unique_ids()
    {
        $this->getId("foos", 1)->shouldBe($this->getId("foos", 1));
        $this->getId("foos", 1)->shouldNotBe($this->getId("bars", 1));
    }

    function it_should_translate_using_the_morphTable()
    {
        $this->beConstructedWith(["Foo" => "foos"]);

        $this->getId("Foo", 1)->shouldBe($this->getId("foos", 1));
    }
}
