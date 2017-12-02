<?php

namespace spec\Prewk\Snapper\Ingredients;

use Prewk\Snapper\BookKeeper;
use Prewk\Snapper\Ingredients\Value;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class ValueSpec extends ObjectBehavior
{
    function it_is_initializable()
    {
        $this->shouldHaveType(Value::class);
    }

    function it_doesnt_have_deps()
    {
        $this->getDeps("foo", ["bar" => "foo"], false)->shouldBe([]);
        $this->getDeps("foo", ["bar" => "foo"], true)->shouldBe([]);
    }

    function it_serializes_into_some_value(BookKeeper $books)
    {
        $this->serialize("foo", ["bar" => "foo"], $books, false)->unwrap()->shouldBe("foo");
        $this->serialize("foo", ["bar" => "foo"], $books, true)->unwrap()->shouldBe("foo");
    }

    function it_deserializes_into_some_value(BookKeeper $books)
    {
        $this->deserialize("foo", ["bar" => "foo"], $books)->unwrap()["value"]->shouldBe("foo");
    }

    function it_doesnt_have_extra_fields()
    {
        $this->getRequiredExtraFields()->shouldBe([]);
    }
}
