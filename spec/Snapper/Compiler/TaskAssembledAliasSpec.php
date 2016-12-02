<?php

namespace spec\Prewk\Snapper\Compiler;

use Prewk\Snapper\Compiler\TaskAssembledAlias;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class TaskAssembledAliasSpec extends ObjectBehavior
{
    function it_is_initializable()
    {
        $this->beConstructedWith([]);
        $this->shouldHaveType(TaskAssembledAlias::class);
    }

    function it_should_be_able_to_assemble_parts_into_a_value()
    {
        $parts = [
            ["PART", "NONE", '{"foo":'],
            ["ALIAS", "JSON", 0],
            ["PART", "NONE", ',"bar":"lorem id:'],
            ["ALIAS", "NONE", 1],
            ["PART", "NONE", ' ipsum","baz":'],
            ["ALIAS", "JSON", 2],
            ["PART", "NONE", '}'],
        ];

        $aliasLookup = [
            0 => 123,
            1 => 456,
            2 => 789,
        ];

        $expected = json_encode([
            "foo" => 123,
            "bar" => "lorem id:456 ipsum",
            "baz" => 789,
        ]);

        $this->beConstructedWith($parts);
        $this->getAsValue($aliasLookup)->shouldBe($expected);

    }
}
