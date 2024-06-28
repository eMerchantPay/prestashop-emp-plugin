<?php

namespace spec\Genesis\Exceptions;

use PhpSpec\ObjectBehavior;

class InvalidArgumentSpec extends ObjectBehavior
{
    public function it_is_initializable()
    {
        $this->shouldHaveType('Genesis\Exceptions\InvalidArgument');
    }
}
