<?php

namespace spec\Genesis\Api\Request\Financial\Alternatives\Transaction;

use Genesis\Api\Request\Financial\Alternatives\Transaction\ProductIdentifiers;
use PhpSpec\ObjectBehavior;

class ProductIdentifiersSpec extends ObjectBehavior
{
    public function it_is_initializable()
    {
        $this->shouldHaveType(ProductIdentifiers::class);
    }

    public function it_can_build_structure()
    {
        $this->setRequestParameters();
        $this->toArray()->shouldNotBeEmpty();
    }

    protected function setRequestParameters()
    {
        $faker = \Faker\Factory::create();

        $this->setBrand($faker->name);
        $this->setCategoryPath($faker->name);
        $this->setGlobalTradeItemNumber($faker->randomAscii);
        $this->setManufacturerPartNumber($faker->randomAscii);
    }

    public function getMatchers(): array
    {
        return [
            'beEmpty' => function ($subject) {
                return empty($subject);
            },
        ];
    }
}
