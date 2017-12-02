<?php

namespace spec\Prewk\Snapper\Ingredients;

use Prewk\Option\None;
use Prewk\Snapper\BookKeeper;
use Prewk\Snapper\Ingredients\Ref;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class RefSpec extends ObjectBehavior
{
    function it_is_initializable()
    {
        $this->beConstructedWith("foos");
        $this->shouldHaveType(Ref::class);
    }

    function it_can_have_one_dependency()
    {
        $this->beConstructedWith("foos");
        $this->getDeps("foo", ["bar" => "foo"], false)->shouldBe([["foos", "foo"]]);
    }

    function it_can_be_optional()
    {
        $this->beConstructedWith("foos");
        $this->optional("OPTIONAL")->getDeps("OPTIONAL", ["bar" => "OPTIONAL"], false)->shouldBe([]);
        $this->optional("OPTIONAL", "OTHER_OPTIONAL")->getDeps("OTHER_OPTIONAL", ["bar" => "OTHER_OPTIONAL"], false)->shouldBe([]);
    }

    function it_serializes_one_dep_into_some_resolved_id(BookKeeper $books)
    {
        $books->resolveId("foos", "foo")->willReturn("MOCK_ID");

        $this->beConstructedWith("foos");
        $this->serialize("foo", ["bar" => "foo"], $books, false)->unwrap()->shouldBe("MOCK_ID");
        $this->serialize("foo", ["bar" => "foo"], $books, true)->unwrap()->shouldBe("MOCK_ID");
    }

    function it_serializes_an_optional_value_into_some_optional_value(BookKeeper $books)
    {
        $this->beConstructedWith("foos");
        $this->optional("OPTIONAL")->serialize("OPTIONAL", ["bar" => "OPTIONAL"], $books, false)->unwrap()->shouldBe("OPTIONAL");
        $this->optional("OPTIONAL", "OTHER_OPTIONAL")->serialize("OTHER_OPTIONAL", ["bar" => "OTHER_OPTIONAL"], $books, true)->unwrap()->shouldBe("OTHER_OPTIONAL");
    }

    function it_deserializes_one_dep_into_some_resolved_id(BookKeeper $books)
    {
        $books->resolveId("foos", "foo")->willReturn("MOCK_ID");

        $this->beConstructedWith("foos");
        $this->deserialize("foo", ["bar" => "foo"], $books)->unwrap()["value"]->shouldBe("MOCK_ID");
    }

    function it_deserializes_an_optional_value_into_some_optional_value(BookKeeper $books)
    {
        $this->beConstructedWith("foos");
        $this->optional("OPTIONAL")->deserialize("OPTIONAL", ["bar" => "OPTIONAL"], $books, false)->unwrap()["value"]->shouldBe("OPTIONAL");
        $this->optional("OPTIONAL", "OTHER_OPTIONAL")->deserialize("OTHER_OPTIONAL", ["bar" => "OTHER_OPTIONAL"], $books, true)->unwrap()["value"]->shouldBe("OTHER_OPTIONAL");
    }
}
