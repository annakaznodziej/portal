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

namespace App\Offers\Controller;

use App\Offers\Form\Type\OfferFilterType;
use App\Offers\Twig\Extension\OfferExtension;
use HireInSocial\Offers\Application\Query\Offer\OfferFilter;
use HireInSocial\Offers\Offers;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class SpecializationController extends AbstractController
{
    /**
     * @var Offers
     */
    private $offers;

    /**
     * @var ParameterBagInterface
     */
    private $parameterBag;

    public function __construct(
        Offers $offers,
        ParameterBagInterface $parameterBag
    ) {
        $this->offers = $offers;
        $this->parameterBag = $parameterBag;
    }

    public function offersAction(Request $request, string $specSlug, string $seniorityLevel = null) : Response
    {
        $specialization = $this->offers->specializationQuery()->findBySlug($specSlug);

        if (!$specialization) {
            throw $this->createNotFoundException();
        }

        $seniorityLevel = $seniorityLevel
            ? OfferExtension::seniorityLevelFromName($seniorityLevel)
            : null;

        /** @var OfferFilter $offerFilter */
        $offerFilter = OfferFilter::allFor($specialization->slug(), $this->parameterBag->get('his.old_offer_days'))
            ->max(12);

        $form = $this->get('form.factory')->createNamed('offers', OfferFilterType::class)->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($form->get('remote')->getData()) {
                $offerFilter->onlyRemote();
            }

            if ($form->get('with_salary')->getData()) {
                $offerFilter->onlyWithSalary();
            }

            if ($sortBy = $form->get('sort_by')->getData()) {
                $offerFilter->sortBy($sortBy);
            }
        }

        $offersSeniorityLevels = $this->offers->offerQuery()->offersSeniorityLevels($offerFilter);

        if ($seniorityLevel) {
            $offerFilter->onlyFor($seniorityLevel);
        }

        $total = $this->offers->offerQuery()->count($offerFilter);

        if ($request->query->has('after')) {
            $offerFilter->showAfter($request->query->get('after'));
        }

        $offers = $this->offers->offerQuery()->findAll($offerFilter);
        $offerMore = $this->offers->offerQuery()->count($offerFilter);

        return $this->render('@offers/specialization/offers.html.twig', [
            'total' => $total,
            'offers' => $offers,
            'offersMore' => $offerMore,
            'showingOlder' => $request->query->has('after'),
            'specialization' => $specialization,
            'form' => $form->createView(),
            'queryParameters' => $request->query->all(),
            'throttleLimit' => $this->offers->offerThrottleQuery()->limit(),
            'throttleSince' => $this->offers->offerThrottleQuery()->since(),
            'offersSeniorityLevels' => $offersSeniorityLevels,
            'seniorityLevel' => $seniorityLevel,
        ]);
    }
}
