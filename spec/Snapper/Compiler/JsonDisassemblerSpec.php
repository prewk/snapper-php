<?php

namespace spec\Prewk\Snapper\Compiler;

use Prewk\Snapper\Compiler\JsonDisassembler;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class JsonDisassemblerSpec extends ObjectBehavior
{
    function it_is_initializable()
    {
        $this->shouldHaveType(JsonDisassembler::class);
    }

    function it_should_disassemble_ints()
    {
        $full = [
            "foo" => 100,
            "bars" => [200, 300],
            "a" => [
                "b" => [
                    "c" => "Lorem ipsum (id:400) dolor a(id:200)met",
                ],
            ],
        ];
        $tokens = [
            100,
            200,
            300,
            400,
        ];

        $expected = [
            "{\"foo\":",
            100,
            ",\"bars\":[",
            200,
            ",",
            300,
            "],\"a\":{\"b\":{\"c\":\"Lorem ipsum (id:",
            400,
            ") dolor a(id:",
            200,
            ")met\"}}}",
        ];

        $this->disassemble($full, $tokens)->shouldBe($expected);
    }

    function it_should_disassemble_strings_without_quotation_marks()
    {
        $full = [
            "foo" => "abc",
            "bars" => ["def", "ghi"],
            "a" => [
                "b" => [
                    "c" => "Lorem ipsum (id:jkl) dolor a(id:def)met",
                ],
            ],
        ];
        $tokens = [
            "abc",
            "def",
            "ghi",
            "jkl",
        ];

        $expected = [
            "{\"foo\":",
            "abc",
            ",\"bars\":[",
            "def",
            ",",
            "ghi",
            "],\"a\":{\"b\":{\"c\":\"Lorem ipsum (id:",
            "jkl",
            ") dolor a(id:",
            "def",
            ")met\"}}}",
        ];

        $this->disassemble($full, $tokens)->shouldBe($expected);
    }
}
