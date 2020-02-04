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

namespace HireInSocial\Offers\Infrastructure;

use Doctrine\ORM\EntityManager;
use Facebook\Facebook;
use HireInSocial\Config;
use HireInSocial\Offers\Application\Command\Facebook\PagePostOfferAtGroupHandler;
use HireInSocial\Offers\Application\Command\Offer\ApplyThroughEmailHandler;
use HireInSocial\Offers\Application\Command\Offer\PostOfferHandler;
use HireInSocial\Offers\Application\Command\Offer\RemoveOfferHandler;
use HireInSocial\Offers\Application\Command\Specialization\CreateSpecializationHandler;
use HireInSocial\Offers\Application\Command\Specialization\RemoveFacebookChannelHandler;
use HireInSocial\Offers\Application\Command\Specialization\RemoveTwitterChannelHandler;
use HireInSocial\Offers\Application\Command\Specialization\SetFacebookChannelHandler;
use HireInSocial\Offers\Application\Command\Specialization\SetTwitterChannelHandler;
use HireInSocial\Offers\Application\Command\Twitter\TweetAboutOfferHandler;
use HireInSocial\Offers\Application\Command\User\AddExtraOffersHandler;
use HireInSocial\Offers\Application\Command\User\BlockUserHandler;
use HireInSocial\Offers\Application\Command\User\FacebookConnectHandler;
use HireInSocial\Offers\Application\Command\User\LinkedInConnectHandler;
use HireInSocial\Offers\Application\Facebook\FacebookGroupService;
use HireInSocial\Offers\Application\FeatureToggle;
use HireInSocial\Offers\Application\Offer\EmailFormatter;
use HireInSocial\Offers\Application\Offer\Throttling;
use HireInSocial\Offers\Application\Query\Features\FeatureToggleQuery;
use HireInSocial\Offers\Application\System;
use HireInSocial\Offers\Application\System\CommandBus;
use HireInSocial\Offers\Application\System\Queries;
use HireInSocial\Offers\Infrastructure\Doctrine\DBAL\Application\Facebook\DbalFacebookFacebookQuery;
use HireInSocial\Offers\Infrastructure\Doctrine\DBAL\Application\Offer\DbalApplicationQuery;
use HireInSocial\Offers\Infrastructure\Doctrine\DBAL\Application\Offer\DbalOfferQuery;
use HireInSocial\Offers\Infrastructure\Doctrine\DBAL\Application\Offer\DbalOfferThrottleQuery;
use HireInSocial\Offers\Infrastructure\Doctrine\DBAL\Application\Specialization\DbalSpecializationQuery;
use HireInSocial\Offers\Infrastructure\Doctrine\DBAL\Application\Twitter\DbalTweetsQuery;
use HireInSocial\Offers\Infrastructure\Doctrine\DBAL\Application\User\DbalExtraOffersQuery;
use HireInSocial\Offers\Infrastructure\Doctrine\DBAL\Application\User\DbalUserQuery;
use HireInSocial\Offers\Infrastructure\Doctrine\ORM\Application\Facebook\ORMPosts;
use HireInSocial\Offers\Infrastructure\Doctrine\ORM\Application\Offer\ORMApplications;
use HireInSocial\Offers\Infrastructure\Doctrine\ORM\Application\Offer\ORMOfferPDFs;
use HireInSocial\Offers\Infrastructure\Doctrine\ORM\Application\Offer\ORMOffers;
use HireInSocial\Offers\Infrastructure\Doctrine\ORM\Application\Offer\ORMSlugs;
use HireInSocial\Offers\Infrastructure\Doctrine\ORM\Application\Specialization\ORMSpecializations;
use HireInSocial\Offers\Infrastructure\Doctrine\ORM\Application\System\ORMTransactionManager;
use HireInSocial\Offers\Infrastructure\Doctrine\ORM\Application\Twitter\ORMTweets;
use HireInSocial\Offers\Infrastructure\Doctrine\ORM\Application\User\ORMExtraOffers;
use HireInSocial\Offers\Infrastructure\Doctrine\ORM\Application\User\ORMUsers;
use HireInSocial\Offers\Infrastructure\Facebook\FacebookGraphSDK;
use HireInSocial\Offers\Infrastructure\Flysystem\Application\System\FlysystemStorage;
use HireInSocial\Offers\Infrastructure\PHP\Hash\SHA256Encoder;
use HireInSocial\Offers\Infrastructure\PHP\SystemCalendar\SystemCalendar;
use HireInSocial\Offers\Infrastructure\SwiftMailer\System\SwiftMailer;
use HireInSocial\Offers\Infrastructure\Twitter\OAuthTwitter;
use HireInSocial\Offers\Offers;
use HireInSocial\Tests\Offers\Application\Double\Dummy\DummyFacebook;
use HireInSocial\Tests\Offers\Application\Double\Dummy\DummyTwitter;
use HireInSocial\Tests\Offers\Application\Double\Stub\CalendarStub;
use Psr\Log\LoggerInterface;
use Twig\Environment;

function offersFacade(Config $config, EntityManager $entityManager, \Swift_Mailer $swiftMailer, Environment $twig, LoggerInterface $logger) : Offers
{
    $dbalConnection = $entityManager->getConnection();
    $mailer = new SwiftMailer(
        $config->getJson(Config::MAILER_CONFIG)['domain'],
        $swiftMailer
    );

    switch ($config->getString(Config::ENV)) {
        case 'prod':
            $calendar = new SystemCalendar(new \DateTimeZone('UTC'));
            $twitter = new OAuthTwitter(
                $config->getString(Config::TWITTER_API_KEY),
                $config->getString(Config::TWITTER_API_SECRET_KEY),
            );
            $facebook = new FacebookGraphSDK(
                new Facebook([
                    'app_id' => $config->getString(Config::FB_APP_ID),
                    'app_secret' => $config->getString(Config::FB_APP_SECRET),
                ]),
                $logger
            );

            break;
        case 'dev':
            $calendar = new CalendarStub(new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
            $twitter = new OAuthTwitter(
                $config->getString(Config::TWITTER_API_KEY),
                $config->getString(Config::TWITTER_API_SECRET_KEY),
            );
            $facebook = new FacebookGraphSDK(
                new Facebook([
                    'app_id' => $config->getString(Config::FB_APP_ID),
                    'app_secret' => $config->getString(Config::FB_APP_SECRET),
                ]),
                $logger
            );

            break;
        case 'test':
            $calendar = new CalendarStub(new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
            $twitter = new DummyTwitter();
            $facebook = new DummyFacebook();

            break;
        default:
            throw new \RuntimeException(sprintf('Unknown environment %s', $config->getString(Config::ENV)));
    }

    $ormSpecializations = new ORMSpecializations($entityManager);

    $throttling = Throttling::createDefault($calendar);
    $ormOffers = new ORMOffers($entityManager);
    $ormUsers = new ORMUsers($entityManager);
    $ormExtraOffers = new ORMExtraOffers($entityManager);
    $ormApplications = new ORMApplications($entityManager);

    $encoder = new SHA256Encoder();
    $emailFormatter = new EmailFormatter($twig);

    $featureToggle = new FeatureToggle(
        new FeatureToggle\PostNewOffersFeature($config->getBool(Config::FEATURE_POST_NEW_OFFERS)),
        new FeatureToggle\PostOfferAtFacebookGroupFeature($config->getBool(Config::FEATURE_POST_OFFER_AT_FACEBOOK)),
        new FeatureToggle\TweetAboutOfferFeature($config->getBool(Config::FEATURE_TWEET_ABOUT_OFFER))
    );

    return new Offers(
        new System(
            new CommandBus(
                new ORMTransactionManager($entityManager),
                new CreateSpecializationHandler(
                    $ormSpecializations
                ),
                new SetFacebookChannelHandler(
                    $ormSpecializations
                ),
                new SetTwitterChannelHandler(
                    $ormSpecializations
                ),
                new RemoveFacebookChannelHandler(
                    $ormSpecializations
                ),
                new RemoveTwitterChannelHandler(
                    $ormSpecializations
                ),
                new PostOfferHandler(
                    $calendar,
                    $ormOffers,
                    $ormExtraOffers,
                    $ormUsers,
                    $throttling,
                    $ormSpecializations,
                    new ORMSlugs($entityManager),
                    new ORMOfferPDFs($entityManager),
                    FlysystemStorage::create($config->getJson(Config::FILESYSTEM_CONFIG))
                ),
                new RemoveOfferHandler(
                    $ormUsers,
                    $ormOffers,
                    $calendar
                ),
                new PagePostOfferAtGroupHandler(
                    $ormOffers,
                    new ORMPosts($entityManager),
                    $ormSpecializations,
                    new FacebookGroupService($facebook)
                ),
                new TweetAboutOfferHandler(
                    $ormOffers,
                    new ORMTweets($entityManager),
                    $ormSpecializations,
                    $twitter
                ),
                new FacebookConnectHandler(
                    $ormUsers,
                    $calendar
                ),
                new LinkedInConnectHandler(
                    $ormUsers,
                    $calendar
                ),
                new BlockUserHandler(
                    $ormUsers,
                    $calendar
                ),
                new AddExtraOffersHandler(
                    $ormUsers,
                    $ormExtraOffers,
                    $calendar
                ),
                new ApplyThroughEmailHandler(
                    $mailer,
                    $ormOffers,
                    $ormApplications,
                    $encoder,
                    $emailFormatter,
                    $calendar
                )
            ),
            new Queries(
                new DbalOfferThrottleQuery($throttling->limit(), $throttling->since(), $dbalConnection, $calendar),
                new DbalOfferQuery($dbalConnection),
                new DbalSpecializationQuery($dbalConnection),
                new DbalUserQuery($dbalConnection),
                new DbalExtraOffersQuery($dbalConnection),
                new DbalApplicationQuery($dbalConnection, $encoder),
                new DbalFacebookFacebookQuery($dbalConnection),
                new DbalTweetsQuery($dbalConnection),
                new FeatureToggleQuery($featureToggle)
            ),
            $featureToggle,
            $logger,
            $calendar
        ),
        $calendar
    );
}
