<?php

namespace spec\Prewk\Snapper\Ingredients;

use Prewk\Option\Some;
use Prewk\Snapper\BookKeeper;
use Prewk\Snapper\Ingredients\Circular;
use PhpSpec\ObjectBehavior;
use Prewk\Snapper\Ingredients\Ingredient;
use Prophecy\Argument;

class CircularSpec extends ObjectBehavior
{
    function it_is_initializable(Ingredient $ingredient)
    {
        $this->beConstructedWith($ingredient, $ingredient);
        $this->shouldHaveType(Circular::class);
    }

    function its_dependencies_depend_on_the_circular_argument(Ingredient $ingredient, Ingredient $fallback)
    {
        $this->beConstructedWith($ingredient, $fallback);

        $ingredient->getDeps("foo", ["bar" => "foo"], false)->willReturn([["INGREDIENT_TYPE", 1]]);
        $this->getDeps("foo", ["bar" => "foo"], true)->shouldBe([["INGREDIENT_TYPE", 1]]);

        $fallback->getDeps("foo", ["bar" => "foo"], false)->willReturn([["FALLBACK_TYPE", 1]]);
        $this->getDeps("foo", ["bar" => "foo"], false)->shouldBe([["FALLBACK_TYPE", 1]]);
    }

    function its_serialization_depend_on_the_circular_argument(Ingredient $ingredient, Ingredient $fallback, BookKeeper $books)
    {
        $this->beConstructedWith($ingredient, $fallback);

        $ingredient->serialize("foo", ["bar" => "foo"], $books, false)->willReturn(new Some("INGREDIENT_SERIALIZATION"));
        $this->serialize("foo", ["bar" => "foo"], $books, true)->unwrap()->shouldBe("INGREDIENT_SERIALIZATION");

        $fallback->serialize("foo", ["bar" => "foo"], $books, false)->willReturn(new Some("FALLBACK_SERIALIZATION"));
        $this->serialize("foo", ["bar" => "foo"], $books, false)->unwrap()->shouldBe("FALLBACK_SERIALIZATION");
    }

    function its_deserialization_depend_on_the_circular_argument(Ingredient $ingredient, Ingredient $fallback, BookKeeper $books)
    {
        $this->beConstructedWith($ingredient, $fallback);

        $ingredient->deserialize("foo", ["bar" => "foo"], $books)->willReturn(new Some("FALLBACK_DESERIALIZATION"));
        $this->deserialize("foo", ["bar" => "foo"], $books)->unwrap()->shouldBe("FALLBACK_DESERIALIZATION");
    }

    function its_extra_fields_depend_on_its_sub_ingredient(Ingredient $ingredient, Ingredient $fallback)
    {
        $this->beConstructedWith($ingredient, $fallback);

        $ingredient->getRequiredExtraFields()->willReturn(["SOME_FIELD"]);
        $this->getRequiredExtraFields()->shouldBe(["SOME_FIELD"]);
    }
}
