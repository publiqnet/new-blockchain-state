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
        $popularPublications = $em->getRepository(Publication::class)->getPopularPublications();
        $articlesSummary = $em->getRepository(ContentUnit::class)->getArticlesSummary();
        $popularAuthors = $em->getRepository(Account::class)->getPopularAuthors(10);
        $authorsSummary = $em->getRepository(Account::class)->getAuthorsCount();

        $popularAuthors = $this->get('serializer')->normalize($popularAuthors, null, ['groups' => ['accountBase', 'accountEmail', 'accountStats']]);
        $popularPublications = $this->get('serializer')->normalize($popularPublications, null, ['groups' => ['publication', 'publicationStats']]);

        return $this->render('admin/stats.html.twig', [
            'publicationsSummary' => $publicationsSummary[0],
            'articlesSummary' => $articlesSummary[0],
            'authorSummary' => $authorsSummary[0],
            'popularAuthors' => $popularAuthors,
            'popularPublications' => $popularPublications,
        ]);
    }
}