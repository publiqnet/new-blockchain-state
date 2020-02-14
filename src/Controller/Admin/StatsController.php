<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 2/14/20
 * Time: 4:34 PM
 */

namespace App\Controller\Admin;

use App\Entity\Account;
use App\Entity\ContentUnit;
use App\Entity\Publication;
use Doctrine\ORM\EntityManager;
use Sonata\AdminBundle\Controller\CRUDController;

class StatsController extends CRUDController
{
    /**
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Symfony\Component\Serializer\Exception\ExceptionInterface
     */
    public function listAction()
    {
        /**
         * @var EntityManager $em
         */
        $em = $this->getDoctrine()->getManager();

        $publicationsSummary = $em->getRepository(Publication::class)->getPublicationsSummary();
        $popularPublications = $em->getRepository(Publication::class)->getPopularPublications(10);
        $articlesSummary = $em->getRepository(ContentUnit::class)->getArticlesSummary();
        $popularAuthorsViews = $em->getRepository(Account::class)->getPopularAuthors(10);
        $popularAuthorsArticles = $em->getRepository(Account::class)->getPopularAuthors(10, null, 'totalArticles');
        $authorsSummary = $em->getRepository(Account::class)->getAuthorsCount();

        $popularAuthorsViews = $this->get('serializer')->normalize($popularAuthorsViews, null, ['groups' => ['accountBase', 'accountEmail', 'accountStats']]);
        $popularAuthorsArticles = $this->get('serializer')->normalize($popularAuthorsArticles, null, ['groups' => ['accountBase', 'accountEmail', 'accountStats']]);
        $popularPublications = $this->get('serializer')->normalize($popularPublications, null, ['groups' => ['publication', 'publicationStats']]);

        return $this->render('admin/stats.html.twig', [
            'publicationsSummary' => $publicationsSummary[0],
            'articlesSummary' => $articlesSummary[0],
            'authorSummary' => $authorsSummary[0],
            'popularAuthorsViews' => $popularAuthorsViews,
            'popularAuthorsArticles' => $popularAuthorsArticles,
            'popularPublications' => $popularPublications,
        ]);
    }
}