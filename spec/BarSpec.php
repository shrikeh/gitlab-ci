<?php

namespace spec\Foo;

use Foo\Bar;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class BarSpec extends ObjectBehavior
{
    function it_is_initializable()
    {
        $this->shouldHaveType(Bar::class);
    }
}
