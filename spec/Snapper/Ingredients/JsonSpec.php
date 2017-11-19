<?php

namespace spec\Prewk\Snapper\Ingredients;

use Prewk\Snapper\BookKeeper;
use Prewk\Snapper\Exceptions\RecipeException;
use Prewk\Snapper\Ingredients\Json;
use PhpSpec\ObjectBehavior;
use Prewk\Snapper\Ingredients\Json\JsonRecipe;
use Prewk\Snapper\Ingredients\Json\MatchedJson;
use Prewk\Snapper\Ingredients\Json\PatternReplacer;
use Prophecy\Argument;

class JsonSpec extends ObjectBehavior
{
    function it_is_initializable()
    {
        $this->beConstructedWith(function() {});
        $this->shouldHaveType(Json::class);
    }

    function it_returns_no_deps_if_non_string_field()
    {
        $this->beConstructedWith(function(JsonRecipe $json) {
            return $json;
        });

        $this->getDeps(123, ["bar" => 123], false)->shouldBe([]);
    }

    function it_throws_if_JsonRecipe_isnt_returned()
    {
        $this->beConstructedWith(function() {});
        $this->getDeps(123, ["bar" => 123], false)->shouldThrow(RecipeException::class);
    }

    function it_gets_dependencies()
    {
        $this->beConstructedWith(function(JsonRecipe $json) {
            return $json
                ->path("foo.foo_id", function(MatchedJson $matched) {
                    return $matched
                        ->ref("foos");
                })
                ->path("bar.bar_id", function(MatchedJson $matched) {
                    return $matched
                        ->ref("bars")->optional(null);
                })
                ->path("baz.baz_id", function(MatchedJson $matched) {
                    return $matched
                        ->pattern("/id:<(.*?)>/", function(PatternReplacer $replacer, string $replacement) {
                            return $replacer
                                ->replace("bazes", 1, "id:<$replacement>");
                        });
                })
                ->pattern("/qux/", function(MatchedJson $matched) {
                    return $matched
                        ->ref("quxes");
                });
        });

        $value = json_encode([
            "foo" => ["foo_id" => 1],
            "bar" => ["bar_id" => null],
            "baz" => ["baz_id" => "Lorem id:<2> ipsum id:<3>"],
            "a_qux_a" => 4,
            "b_qux_b" => 5,
        ], JSON_UNESCAPED_SLASHES);

        $this->getDeps($value, ["value" => $value], false)->shouldBe([
            ["foos", 1],
            ["bazes", "2"],
            ["bazes", "3"],
            ["quxes", 4],
            ["quxes", 5],
        ]);
    }

    function it_serializes_into_some_value(BookKeeper $books)
    {
        $this->beConstructedWith(function(JsonRecipe $json) {
            return $json
                ->path("foo.foo_id", function(MatchedJson $matched) {
                    return $matched
                        ->ref("foos");
                })
                ->path("bar.bar_id", function(MatchedJson $matched) {
                    return $matched
                        ->ref("bars")->optional(null);
                })
                ->path("baz.baz_id", function(MatchedJson $matched) {
                    return $matched
                        ->pattern("/id:<(\\d+)>/", function(PatternReplacer $replacer, string $replacement) {
                            return $replacer
                                ->replace("bazes", 1, "id:<$replacement>");
                        });
                })
                ->pattern("/qux/", function(MatchedJson $matched) {
                    return $matched
                        ->ref("quxes");
                });
        });

        $value = json_encode([
            "foo" => ["foo_id" => 1],
            "bar" => ["bar_id" => null],
            "baz" => ["baz_id" => "Lorem id:<2> ipsum id:<3>"],
            "a_qux_a" => 4,
            "b_qux_b" => 5,
        ], JSON_UNESCAPED_SLASHES);

        $books->resolveId("foos", 1)->willReturn("PARSED_1");
        $books->resolveId("bazes", 2)->willReturn("PARSED_2");
        $books->resolveId("bazes", 3)->willReturn("PARSED_3");
        $books->resolveId("quxes", 4)->willReturn("PARSED_4");
        $books->resolveId("quxes", 5)->willReturn("PARSED_5");

        $this->serialize($value, ["value" => $value], $books, false)->unwrap()->shouldBe(json_encode([
            "foo" => ["foo_id" => "PARSED_1"],
            "bar" => ["bar_id" => null],
            "baz" => ["baz_id" => "Lorem id:<PARSED_2> ipsum id:<PARSED_3>"],
            "a_qux_a" => "PARSED_4",
            "b_qux_b" => "PARSED_5",
        ], JSON_UNESCAPED_SLASHES));
    }

    function it_deserializes_by_getting_deps_and_string_replacing(BookKeeper $books)
    {
        $this->beConstructedWith(function(JsonRecipe $json) {
            return $json
                ->path("foo.foo_id", function(MatchedJson $matched) {
                    return $matched
                        ->ref("foos");
                })
                ->path("bar.bar_id", function(MatchedJson $matched) {
                    return $matched
                        ->ref("bars")->optional(null);
                })
                ->path("baz.baz_id", function(MatchedJson $matched) {
                    return $matched
                        ->pattern("/id:<(.*?)>/", function(PatternReplacer $replacer, string $replacement) {
                            return $replacer
                                ->replace("bazes", 1, "id:<$replacement>");
                        });
                })
                ->pattern("/qux/", function(MatchedJson $matched) {
                    return $matched
                        ->ref("quxes");
                });
        });

        $value = json_encode([
            "foo" => ["foo_id" => "PARSED_1"],
            "bar" => ["bar_id" => null],
            "baz" => ["baz_id" => "Lorem id:<PARSED_2> ipsum id:<PARSED_3>"],
            "a_qux_a" => "PARSED_4",
            "b_qux_b" => "PARSED_5",
        ], JSON_UNESCAPED_SLASHES);

        $books->resolveId("foos", "PARSED_1")->willReturn(1);
        $books->resolveId("bazes", "PARSED_2")->willReturn(2);
        $books->resolveId("bazes", "PARSED_3")->willReturn(3);
        $books->resolveId("quxes", "PARSED_4")->willReturn(4);
        $books->resolveId("quxes", "PARSED_5")->willReturn(5);

        $this->deserialize($value, ["value" => $value], $books)->unwrap()->shouldBe(json_encode([
            "foo" => ["foo_id" => 1],
            "bar" => ["bar_id" => null],
            "baz" => ["baz_id" => "Lorem id:<2> ipsum id:<3>"],
            "a_qux_a" => 4,
            "b_qux_b" => 5,
        ], JSON_UNESCAPED_SLASHES));
    }

    function it_requires_no_extra_fields()
    {
        $this->beConstructedWith(function() {});
        $this->getRequiredExtraFields()->shouldBe([]);
    }
}
