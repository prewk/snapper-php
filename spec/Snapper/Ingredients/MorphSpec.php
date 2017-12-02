<?php

namespace spec\Prewk\Snapper\Ingredients;

use Prewk\Snapper\BookKeeper;
use Prewk\Snapper\Ingredients\Morph;
use PhpSpec\ObjectBehavior;
use Prewk\Snapper\Ingredients\Morph\MorphMapper;
use Prophecy\Argument;

class MorphSpec extends ObjectBehavior
{
    function it_is_initializable()
    {
        $this->beConstructedWith("polyable_type", function() {});
        $this->shouldHaveType(Morph::class);
    }

    function it_can_have_a_polymorphic_dependency()
    {
        $this->beConstructedWith("polyable_type", function(MorphMapper $mapper) {
            return $mapper
                ->map("FOO", "foos")
                ->map("BAR", "bars");
        });
        $this->getDeps(123, ["polyable_type" => "BAR", "polyable_id" => 123], false)->shouldBe([["bars", 123]]);
        $this->getDeps(123, ["polyable_type" => "FOO", "polyable_id" => 123], false)->shouldBe([["foos", 123]]);
    }

    function its_polymorphic_dependency_can_be_optional()
    {
        $this->beConstructedWith("polyable_type", function(MorphMapper $mapper) {
            return $mapper
                ->map("FOO", "foos")
                ->map("BAR", "bars");
        });
        $this->optional(null)->getDeps(null, ["polyable_type" => "BAR", "polyable_id" => null], false)->shouldBe([]);
        $this->optional(null)->getDeps(null, ["polyable_type" => null, "polyable_id" => 123], false)->shouldBe([]);
        $this->optional(null)->getDeps(null, ["polyable_type" => null, "polyable_id" => null], false)->shouldBe([]);
    }

    function it_serializes_its_polymorphic_dependency_into_some_resolved_id(BookKeeper $books)
    {
        $books->resolveId("bars", 123)->willReturn("MOCK_ID");

        $this->beConstructedWith("polyable_type", function(MorphMapper $mapper) {
            return $mapper
                ->map("FOO", "foos")
                ->map("BAR", "bars");
        });
        $this->serialize(123, ["polyable_type" => "BAR", "polyable_id" => 123], $books, false)->unwrap()->shouldBe("MOCK_ID");
        $this->serialize(123, ["polyable_type" => "BAR", "polyable_id" => 123], $books, true)->unwrap()->shouldBe("MOCK_ID");
    }

    function it_deserializes_its_polymorphic_dependency_into_some_resolved_id(BookKeeper $books)
    {
        $books->resolveId("bars", 123)->willReturn("MOCK_ID");

        $this->beConstructedWith("polyable_type", function(MorphMapper $mapper) {
            return $mapper
                ->map("FOO", "foos")
                ->map("BAR", "bars");
        });
        $this->deserialize(123, ["polyable_type" => "BAR", "polyable_id" => 123], $books, false)->unwrap()["value"]->shouldBe("MOCK_ID");
        $this->deserialize(123, ["polyable_type" => "BAR", "polyable_id" => 123], $books, true)->unwrap()["value"]->shouldBe("MOCK_ID");
    }

    function it_requires_an_extra_field()
    {
        $this->beConstructedWith("polyable_type", function() {});
        $this->getRequiredExtraFields()->shouldBe(["polyable_type"]);
    }
}
