<?php

namespace spec\Prewk\Snapper\Ingredients;

use Prewk\Option\Some;
use Prewk\Snapper\BookKeeper;
use Prewk\Snapper\Exceptions\RecipeException;
use Prewk\Snapper\Ingredients\Ingredient;
use Prewk\Snapper\Ingredients\Match;
use PhpSpec\ObjectBehavior;
use Prewk\Snapper\Ingredients\Match\MatchMapper;
use Prophecy\Argument;

class MatchSpec extends ObjectBehavior
{
    function it_is_initializable()
    {
        $this->beConstructedWith("foo", function() {});
        $this->shouldHaveType(Match::class);
    }

    function it_should_have_no_deps_if_match_field_isnt_present()
    {
        $this->beConstructedWith("foo", function() {});
        $this->getDeps("foo", ["bar" => "foo"], false)->shouldBe([]);
    }

    function it_should_throw_if_the_mapper_isnt_returned()
    {
        $this->beConstructedWith("foo", function() {});
        $this->getDeps("foo", ["bar" => "foo"], false)->shouldThrow(RecipeException::class);
    }

    function its_deps_should_depend_on_the_matcher(Ingredient $exact, Ingredient $pattern, Ingredient $default)
    {
        $this->beConstructedWith("type", function(MatchMapper $mapper) use ($exact, $pattern, $default) {
            return $mapper
                ->on("exact_match", $exact->getWrappedObject())
                ->pattern("/regexp/", $pattern->getWrappedObject())
                ->default($default->getWrappedObject());
        });

        $exact->getDeps("foo", ["bar" => "foo", "type" => "exact_match"])->willReturn([["EXACT_MATCH", 123]]);
        $pattern->getDeps("foo", ["bar" => "foo", "type" => "test regexp test"])->willReturn([["PATTERN_MATCH", 123]]);
        $default->getDeps("foo", ["bar" => "foo", "type" => "other"])->willReturn([["DEFAULT_MATCH", 123]]);

        $this->getDeps("foo", ["bar" => "foo", "type" => "exact_match"], false)->shouldBe([["EXACT_MATCH", 123]]);
        $this->getDeps("foo", ["bar" => "foo", "type" => "test regexp test"], false)->shouldBe([["PATTERN_MATCH", 123]]);
        $this->getDeps("foo", ["bar" => "foo", "type" => "other"], false)->shouldBe([["DEFAULT_MATCH", 123]]);
    }

    function its_serialization_should_depend_on_the_matcher(Ingredient $exact, Ingredient $pattern, Ingredient $default, BookKeeper $books)
    {
        $this->beConstructedWith("type", function(MatchMapper $mapper) use ($exact, $pattern, $default) {
            return $mapper
                ->on("exact_match", $exact->getWrappedObject())
                ->pattern("/regexp/", $pattern->getWrappedObject())
                ->default($default->getWrappedObject());
        });

        $exact->serialize("foo", ["bar" => "foo", "type" => "exact_match"], $books, false)->willReturn(new Some("EXACT"));
        $pattern->serialize("foo", ["bar" => "foo", "type" => "test regexp test"], $books, false)->willReturn(new Some("PATTERN"));
        $default->serialize("foo", ["bar" => "foo", "type" => "other"], $books, false)->willReturn(new Some("DEFAULT"));

        $this->serialize("foo", ["bar" => "foo", "type" => "exact_match"], $books, false)->unwrap()->shouldBe("EXACT");
        $this->serialize("foo", ["bar" => "foo", "type" => "test regexp test"], $books, false)->unwrap()->shouldBe("PATTERN");
        $this->serialize("foo", ["bar" => "foo", "type" => "other"], $books, false)->unwrap()->shouldBe("DEFAULT");
    }

    function its_deserialization_should_depend_on_the_matcher(Ingredient $exact, Ingredient $pattern, Ingredient $default, BookKeeper $books)
    {
        $this->beConstructedWith("type", function(MatchMapper $mapper) use ($exact, $pattern, $default) {
            return $mapper
                ->on("exact_match", $exact->getWrappedObject())
                ->pattern("/regexp/", $pattern->getWrappedObject())
                ->default($default->getWrappedObject());
        });

        $exact->deserialize("foo", ["bar" => "foo", "type" => "exact_match"], $books)->willReturn(new Some("EXACT"));
        $pattern->deserialize("foo", ["bar" => "foo", "type" => "test regexp test"], $books)->willReturn(new Some("PATTERN"));
        $default->deserialize("foo", ["bar" => "foo", "type" => "other"], $books)->willReturn(new Some("DEFAULT"));

        $this->deserialize("foo", ["bar" => "foo", "type" => "exact_match"], $books)->unwrap()->shouldBe("EXACT");
        $this->deserialize("foo", ["bar" => "foo", "type" => "test regexp test"], $books)->unwrap()->shouldBe("PATTERN");
        $this->deserialize("foo", ["bar" => "foo", "type" => "other"], $books)->unwrap()->shouldBe("DEFAULT");
    }

    function it_should_have_a_required_extra_field()
    {
        $this->beConstructedWith("type", function() {});
        $this->getRequiredExtraFields()->shouldBe(["type"]);
    }
}
