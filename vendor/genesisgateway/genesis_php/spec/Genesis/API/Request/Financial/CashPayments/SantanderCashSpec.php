<?php

namespace spec\Genesis\API\Request\Financial\CashPayments;

use Genesis\API\Request\Financial\CashPayments\SantanderCash;
use PhpSpec\ObjectBehavior;
use spec\SharedExamples\Genesis\API\Request\RequestExamples;

class SantanderCashSpec extends ObjectBehavior
{
    use RequestExamples;

    public function it_is_initializable()
    {
        $this->shouldHaveType(SantanderCash::class);
    }

    public function it_should_fail_when_country_invalid()
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
        $this->setBillingCountry('MX');
    }
}
