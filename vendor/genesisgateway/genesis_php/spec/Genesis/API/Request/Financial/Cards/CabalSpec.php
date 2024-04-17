<?php

namespace spec\Genesis\API\Request\Financial\Cards;

use Genesis\API\Request\Financial\Cards\Cabal;
use PhpSpec\ObjectBehavior;
use spec\SharedExamples\Genesis\API\Request\RequestExamples;
use spec\SharedExamples\Genesis\API\Request\Financial\NeighborhoodAttributesExamples;

class CabalSpec extends ObjectBehavior
{
    use RequestExamples, NeighborhoodAttributesExamples;

    public function it_is_initializable()
    {
        $this->shouldHaveType(Cabal::class);
    }

    public function it_should_fail_when_country_is_invalid()
    {
        $this->setRequestParameters();
        $this->setBillingCountry('BG');
        $this->shouldThrow()->during('getDocument');
    }

    protected function setRequestParameters()
    {
        $this->setDefaultRequestParameters();

        $this->setConsumerReference('1234');
        $this->setNationalId('1234');
        $this->setCurrency('USD');
        $this->setBillingCountry('AR');
    }
}
