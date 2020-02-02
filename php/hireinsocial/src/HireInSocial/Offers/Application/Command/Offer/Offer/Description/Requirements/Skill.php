<?php

declare(strict_types=1);

/*
 * This file is part of the Hire in Social project.
 *
 * (c) Norbert Orzechowicz <norbert@orzechowicz.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace HireInSocial\Offers\Application\Command\Offer\Offer\Description\Requirements;

final class Skill
{
    /**
     * @var string
     */
    private $skill;

    /**
     * @var bool
     */
    private $required;

    /**
     * @var int|null
     */
    private $experienceYears;

    public function __construct(string $skill, bool $required, ?int $experienceYears = null)
    {
        $this->skill = $skill;
        $this->required = $required;
        $this->experienceYears = $experienceYears;
    }

    /**
     * @return string
     */
    public function skill() : string
    {
        return $this->skill;
    }

    /**
     * @return bool
     */
    public function required() : bool
    {
        return $this->required;
    }

    /**
     * @return int
     */
    public function experienceYears() : ?int
    {
        return $this->experienceYears;
    }
}