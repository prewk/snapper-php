<?php

namespace spec\Prewk\Snapper\Ingredients;

use Prewk\Snapper\BookKeeper;
use Prewk\Snapper\Ingredients\Raw;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class RawSpec extends ObjectBehavior
{
    function it_is_initializable()
    {
        $this->beConstructedWith("qux");
        $this->shouldHaveType(Raw::class);
    }

    function it_should_have_no_deps()
    {
        $this->beConstructedWith("qux");
        $this->getDeps("foo", ["bar" => "foo"], false)->shouldBe([]);
        $this->getDeps("foo", ["bar" => "foo"], true)->shouldBe([]);
    }

    function it_should_serialize_into_some_value(BookKeeper $books)
    {
        $this->beConstructedWith("qux");
        $this->serialize("foo", ["bar" => "foo"], $books, false)->unwrap()->shouldBe("qux");
        $this->serialize("foo", ["bar" => "foo"], $books, true)->unwrap()->shouldBe("qux");
    }

    function it_should_deserialize_into_some_value(BookKeeper $books)
    {
        $this->beConstructedWith("qux");
        $this->deserialize("foo", ["bar" => "foo"], $books, false)->unwrap()->shouldBe("qux");
        $this->deserialize("foo", ["bar" => "foo"], $books, true)->unwrap()->shouldBe("qux");
    }

    function it_should_have_no_extra_fields()
    {
        $this->beConstructedWith("qux");
        $this->getRequiredExtraFields()->shouldBe([]);
    }
}
