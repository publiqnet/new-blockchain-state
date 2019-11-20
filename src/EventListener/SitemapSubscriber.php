<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 11/20/19
 * Time: 5:45 PM
 */

namespace App\EventListener;

use App\Entity\Account;
use App\Entity\ContentUnit;
use App\Entity\Publication;
use Doctrine\Common\Persistence\ManagerRegistry;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Presta\SitemapBundle\Event\SitemapPopulateEvent;
use Presta\SitemapBundle\Service\UrlContainerInterface;
use Presta\SitemapBundle\Sitemap\Url\UrlConcrete;

class SitemapSubscriber implements EventSubscriberInterface
{
    /**
     * @var UrlGeneratorInterface
     */
    private $urlGenerator;

    /**
     * @var ManagerRegistry
     */
    private $doctrine;
    /**
     * @var Container $container
     */
    private $container;

    /**
     * @param UrlGeneratorInterface $urlGenerator
     * @param ManagerRegistry $doctrine
     * @param ContainerInterface $container
     */
    public function __construct(UrlGeneratorInterface $urlGenerator, ManagerRegistry $doctrine, ContainerInterface $container)
    {
        ini_set('memory_limit', '256M');

        $this->urlGenerator = $urlGenerator;
        $this->doctrine = $doctrine;
        $this->container = $container;
    }

    /**
     * @inheritdoc
     */
    public static function getSubscribedEvents()
    {
        return [
            SitemapPopulateEvent::ON_SITEMAP_POPULATE => 'populate',
        ];
    }

    /**
     * @param SitemapPopulateEvent $event
     */
    public function populate(SitemapPopulateEvent $event): void
    {
        $this->registerStaticPages($event->getUrlContainer());
        $this->registerArticles($event->getUrlContainer());
        $this->registerAuthors($event->getUrlContainer());
        $this->registerPublications($event->getUrlContainer());
    }

    /**
     * @param UrlContainerInterface $urls
     */
    public function registerStaticPages(UrlContainerInterface $urls): void
    {
        $domain = $this->container->getParameter('router.request_context.scheme') . '://' . $this->container->getParameter('router.request_context.host');

        //  HOME PAGE
        $urls->addUrl(
            new UrlConcrete(
                $domain
            ),
            'static'
        );
    }

    /**
     * @param UrlContainerInterface $urls
     */
    public function registerArticles(UrlContainerInterface $urls): void
    {
        $domain = $this->container->getParameter('router.request_context.scheme') . '://' . $this->container->getParameter('router.request_context.host');

        $timezone = new \DateTimeZone('UTC');
        $time = new \DateTime();

        $time->setTimezone($timezone);

        /**
         * @var ContentUnit[] $contentUnits
         */
        $contentUnits = $this->doctrine->getRepository(ContentUnit::class)->findAll();
        foreach ($contentUnits as $contentUnit) {
            if (!$contentUnit->getContent()) {
                continue;
            }
            $timestamp = $contentUnit->getTransaction()->getTimeSigned();
            $time->setTimestamp($timestamp);

            $urls->addUrl(
                new UrlConcrete(
                    $domain . '/s/' . $contentUnit->getUri(),
                    $time
                ),
                'article'
            );
        }
    }

    /**
     * @param UrlContainerInterface $urls
     */
    public function registerAuthors(UrlContainerInterface $urls): void
    {
        $domain = $this->container->getParameter('router.request_context.scheme') . '://' . $this->container->getParameter('router.request_context.host');

        /**
         * @var Account[] $authors
         */
        $authors = $this->doctrine->getRepository(Account::class)->findAll();
        foreach ($authors as $author) {
            if (count($author->getAuthorContentUnits())) {
                $urls->addUrl(
                    new UrlConcrete(
                        $domain . '/a/' . $author->getPublicKey()
                    ),
                    'author'
                );
            }
        }
    }

    /**
     * @param UrlContainerInterface $urls
     */
    public function registerPublications(UrlContainerInterface $urls): void
    {
        $domain = $this->container->getParameter('router.request_context.scheme') . '://' . $this->container->getParameter('router.request_context.host');

        /**
         * @var Publication[] $publications
         */
        $publications = $this->doctrine->getRepository(Publication::class)->findAll();
        foreach ($publications as $publication) {
            $urls->addUrl(
                new UrlConcrete(
                    $domain . '/p/' . $publication->getSlug()
                ),
                'publication'
            );
        }
    }
}