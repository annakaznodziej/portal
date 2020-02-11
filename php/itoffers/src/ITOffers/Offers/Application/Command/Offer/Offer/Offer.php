<?php

declare(strict_types=1);

/*
 * This file is part of the itoffers.online project.
 *
 * (c) Norbert Orzechowicz <norbert@orzechowicz.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ITOffers\Offers\Application\Command\Offer\Offer;

final class Offer
{
    /**
     * @var Company
     */
    private $company;

    /**
     * @var Position
     */
    private $position;

    /**
     * @var Location
     */
    private $location;

    /**
     * @var Salary|null
     */
    private $salary;

    /**
     * @var Contract
     */
    private $contract;

    /**
     * @var Description
     */
    private $description;

    /**
     * @var Contact
     */
    private $contact;

    public function __construct(
        Company $company,
        Position $position,
        Location $location,
        ?Salary $salary,
        Contract $contract,
        Description $description,
        Contact $contact
    ) {
        $this->company = $company;
        $this->position = $position;
        $this->location = $location;
        $this->salary = $salary;
        $this->contract = $contract;
        $this->description = $description;
        $this->contact = $contact;
    }

    public function company() : Company
    {
        return $this->company;
    }

    public function position() : Position
    {
        return $this->position;
    }

    public function location() : Location
    {
        return $this->location;
    }

    public function salary() : ?Salary
    {
        return $this->salary;
    }

    public function contract() : Contract
    {
        return $this->contract;
    }

    public function description() : Description
    {
        return $this->description;
    }

    public function contact() : Contact
    {
        return $this->contact;
    }
}