<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 6/16/20
 * Time: 1:00 PM
 */

namespace App\Controller;

use App\Entity\Account;
use App\Entity\AccountCustomData;
use App\Entity\CanonicalUrl;
use Doctrine\ORM\EntityManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Swagger\Annotations as SWG;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class SpecialApiController
 * @package AppBundle\Controller
 *
 * @Route("/api/special")
 */
class SpecialApiController extends AbstractController
{
    /**
     * @Route("/canonical", methods={"POST"})
     * @SWG\Post(
     *     summary="Set canonical URL",
     *     consumes={"application/json"},
     *     @SWG\Parameter(
     *         name="body",
     *         in="body",
     *         description="JSON Payload",
     *         required=true,
     *         format="application/json",
     *         @SWG\Schema(
     *             type="object",
     *             @SWG\Property(property="uri", type="string"),
     *             @SWG\Property(property="url", type="string"),
     *         )
     *     ),
     *     @SWG\Parameter(name="X-SPECIAL-TOKEN", in="header", required=true, type="string")
     * )
     * @SWG\Response(response=204, description="Success")
     * @SWG\Response(response=403, description="Permission denied")
     * @SWG\Tag(name="Special")
     * @param Request $request
     * @return JsonResponse
     * @throws \Exception
     */
    public function canonicalUrl(Request $request)
    {
        $specialToken = $request->headers->get('X-SPECIAL-TOKEN');
        if (!$specialToken || $specialToken != $this->getParameter('special_api_key')) {
            return new JsonResponse(null, Response::HTTP_FORBIDDEN);
        }

        /**
         * @var EntityManager $em
         */
        $em = $this->getDoctrine()->getManager();

        //  get data from submitted data
        $contentType = $request->getContentType();
        if ($contentType == 'application/json' || $contentType == 'json') {
            $content = $request->getContent();
            $content = json_decode($content, true);

            $uri = $content['uri'];
            $url = $content['url'];
        } else {
            $uri = $request->request->get('uri');
            $url = $request->request->get('url');
        }

        $canonicalUrl = $em->getRepository(CanonicalUrl::class)->findOneBy(['uri' => $uri]);
        if (!$canonicalUrl) {
            $canonicalUrl = new CanonicalUrl();
            $canonicalUrl->setUri($uri);
        }

        $canonicalUrl->setUrl($url);
        $em->persist($canonicalUrl);
        $em->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @Route("/account", methods={"POST"})
     * @SWG\Post(
     *     summary="Set/get account data",
     *     consumes={"application/json"},
     *     @SWG\Parameter(
     *         name="body",
     *         in="body",
     *         description="JSON Payload",
     *         required=true,
     *         format="application/json",
     *         @SWG\Schema(
     *             type="object",
     *             @SWG\Property(property="publicKey", type="string"),
     *             @SWG\Property(property="privateKey", type="string"),
     *             @SWG\Property(property="brainKey", type="string"),
     *             @SWG\Property(property="email", type="string"),
     *             @SWG\Property(property="slug", type="string"),
     *             @SWG\Property(property="fullName", type="string")
     *         )
     *     ),
     *     @SWG\Parameter(name="X-SPECIAL-TOKEN", in="header", required=true, type="string")
     * )
     * @SWG\Response(response=200, description="Success")
     * @SWG\Response(response=403, description="Permission denied")
     * @SWG\Tag(name="Special")
     * @param Request $request
     * @return JsonResponse
     * @throws \Exception
     */
    public function account(Request $request)
    {
        $specialToken = $request->headers->get('X-SPECIAL-TOKEN');
        if (!$specialToken || $specialToken != $this->getParameter('special_api_key')) {
            return new JsonResponse(null, Response::HTTP_FORBIDDEN);
        }

        /**
         * @var EntityManager $em
         */
        $em = $this->getDoctrine()->getManager();

        //  get data from submitted data
        $contentType = $request->getContentType();
        if ($contentType == 'application/json' || $contentType == 'json') {
            $content = $request->getContent();
            $content = json_decode($content, true);

            $publicKey = $content['publicKey'];
            $privateKey = $content['privateKey'];
            $brainKey = $content['brainKey'];
            $email = $content['email'];
            $slug = $content['slug'];
            $fullName = $content['fullName'];
        } else {
            $publicKey = $request->request->get('publicKey');
            $privateKey = $request->request->get('privateKey');
            $brainKey = $request->request->get('brainKey');
            $email = $request->request->get('email');
            $slug = $request->request->get('slug');
            $fullName = $request->request->get('fullName');
        }

        $accountCustomData = $em->getRepository(AccountCustomData::class)->findOneBy(['slug' => $slug]);
        if (!$accountCustomData) {
            $accountCustomData = new AccountCustomData();
            $accountCustomData->setSlug($slug);
            $accountCustomData->setPrivateKey($privateKey);
            $accountCustomData->setBrainKey($brainKey);

            $account = $em->getRepository(Account::class)->findOneBy(['publicKey' => $publicKey]);
            if (!$account) {
                $fullName = explode(' ', $fullName);
                $firstName = trim($fullName[0]);

                unset($fullName[0]);
                $lastName = trim(implode(' ', $fullName));

                $account = new Account();
                $account->setPublicKey($publicKey);
                $account->setEmail($email);
                $account->setFirstName($firstName);
                $account->setLastName($lastName);
                $account->setWhole(0);
                $account->setFraction(0);
                $em->persist($account);
                $em->flush();
            }

            $accountCustomData->setAccount($account);
            $em->persist($accountCustomData);
            $em->flush();
        } else {
            $account = $accountCustomData->getAccount();
        }

        return new JsonResponse(['publicKey' => $account->getPublicKey(), 'privateKey' => $accountCustomData->getPrivateKey(), 'brainKey' => $accountCustomData->getBrainKey()]);
    }
}