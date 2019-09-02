<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 3/19/19
 * Time: 7:02 PM
 */

namespace App\Controller;

use App\Entity\Account;
use App\Entity\ContentUnit;
use App\Entity\Publication;
use App\Entity\PublicationMember;
use App\Entity\Subscription;
use App\Entity\Tag;
use App\Event\PublicationInvitationAcceptEvent;
use App\Event\PublicationInvitationCancelEvent;
use App\Event\PublicationInvitationRejectEvent;
use App\Event\PublicationInvitationRequestEvent;
use App\Event\PublicationMembershipCancelEvent;
use App\Event\PublicationMembershipRequestAcceptEvent;
use App\Event\PublicationMembershipRequestCancelEvent;
use App\Event\PublicationMembershipRequestEvent;
use App\Event\PublicationMembershipRequestRejectEvent;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Swagger\Annotations as SWG;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Tooleks\Php\AvgColorPicker\Gd\AvgColorPicker;
use App\Service\ContentUnit as CUService;

/**
 * @package App\Controller
 * @Route("/api/publication")
 */
class PublicationApiController extends Controller
{
    /**
     * @Route("/create", methods={"POST"})
     * @SWG\Post(
     *     summary="Create publication",
     *     consumes={"multipart/form-data"},
     *     produces={"application/json"},
     *     @SWG\Parameter(name="title", in="formData", type="string", description="Title"),
     *     @SWG\Parameter(name="description", in="formData", type="string", description="Description"),
     *     @SWG\Parameter(name="listView", in="formData", type="boolean", description="List view"),
     *     @SWG\Parameter(name="hideCover", in="formData", type="boolean", description="Hide cover"),
     *     @SWG\Parameter(name="tags", in="formData", type="array", items={"type": "string"}, description="Tags"),
     *     @SWG\Parameter(name="logo", in="formData", type="file", description="Logo"),
     *     @SWG\Parameter(name="cover", in="formData", type="file", description="Cover"),
     *     @SWG\Parameter(name="X-API-TOKEN", in="header", required=true, type="string")
     * )
     * @SWG\Response(response=200, description="Success")
     * @SWG\Response(response=401, description="Unauthorized user")
     * @SWG\Response(response=409, description="Error - see description for more information")
     * @SWG\Tag(name="Publication")
     * @param Request $request
     * @param ValidatorInterface $validator
     * @return JsonResponse
     */
    public function createPublication(Request $request, ValidatorInterface $validator)
    {
        $em = $this->getDoctrine()->getManager();

        /**
         * @var Account $account
         */
        $account = $this->getUser();

        //  get data from submitted data
        $contentType = $request->getContentType();
        if ($contentType == 'application/json' || $contentType == 'json') {
            $content = $request->getContent();
            $content = json_decode($content, true);

            $title = $content['title'];
            $description = $content['description'];
            $listView = $content['listView'];
            $hideCover = $content['hideCover'];
            $tags = $content['tags'];
        } else {
            $title = $request->request->get('title');
            $description = $request->request->get('description');
            $listView = $request->request->get('listView');
            $hideCover = $request->request->get('hideCover');
            $tags = $request->request->get('tags');
        }

        try {
            $publication = new Publication();
            $publication->setTitle($title);
            $publication->setDescription($description);
            $publication->setListView($listView);
            $publication->setHideCover($hideCover);

            //  relate with Tags
            if ($tags) {
                $tags = explode(',', $tags);
                foreach ($tags as $tag) {
                    $tag = trim($tag);
                    $tagEntity = $em->getRepository(Tag::class)->findOneBy(['name' => $tag]);
                    if (!$tagEntity) {
                        $tagEntity = new Tag();
                        $tagEntity->setName($tag);

                        $em->persist($tagEntity);
                    }

                    $publication->addTag($tagEntity);
                }
            }

            //  local function to move uploaded files
            $moveFile = function (UploadedFile $file, string $path) {
                $fileName = md5(uniqid()) . '.' . $file->guessExtension();
                $file->move($path, $fileName);
                return $path . '/' . $fileName;
            };

            //  get upload path from configs
            $publicationsPath = $this->getParameter('publications_path');

            //  create folder for publication
            $currentPublicationPath = $publicationsPath . '/' . $publication->getSlug();
            mkdir($currentPublicationPath);

            /**
             * @var UploadedFile $logo
             */
            $logo = $request->files->get('logo');
            if ($logo instanceof UploadedFile) {
                $publication->setLogo($moveFile($logo, $currentPublicationPath));

                $imageAvgRgbColor = (new AvgColorPicker)->getImageAvgRgbByPath($publication->getLogo());
                $color = sprintf('%02X%02X%02X', $imageAvgRgbColor[0], $imageAvgRgbColor[1], $imageAvgRgbColor[2]);
                $publication->setColor($color);
            }

            /**
             * @var UploadedFile $cover
             */
            $cover = $request->files->get('cover');
            if ($cover instanceof UploadedFile) {
                $publication->setCover($moveFile($cover, $currentPublicationPath));
            }

            $errors = $validator->validate($publication);
            if (count($errors) > 0) {
                return new JsonResponse(['message' => 'system_error', 'content' => $errors->get(0)->getMessage()], Response::HTTP_CONFLICT);
            }

            //  add user as owner of publication
            $publicationMember = new PublicationMember();
            $publicationMember->setPublication($publication);
            $publicationMember->setMember($account);
            $publicationMember->setStatus(PublicationMember::TYPES['owner']);

            $em->persist($publication);
            $em->persist($publicationMember);
            $em->flush();

            //  prepare return data
            $publication = $this->get('serializer')->normalize($publication, null, ['groups' => ['publication', 'tag']]);

            return new JsonResponse($publication);
        } catch (\Exception $e) {
            return new JsonResponse(['message' => $e->getMessage()], Response::HTTP_CONFLICT);
        }
    }

    /**
     * @Route("/{slug}", methods={"POST"})
     * @SWG\Post(
     *     summary="Update publication",
     *     consumes={"multipart/form-data"},
     *     produces={"application/json"},
     *     @SWG\Parameter(name="title", in="formData", type="string", description="Title"),
     *     @SWG\Parameter(name="description", in="formData", type="string", description="Description"),
     *     @SWG\Parameter(name="listView", in="formData", type="boolean", description="List view"),
     *     @SWG\Parameter(name="hideCover", in="formData", type="boolean", description="Hide cover"),
     *     @SWG\Parameter(name="tags", in="formData", type="array", items={"type": "string"}, description="Tags"),
     *     @SWG\Parameter(name="deleteLogo", in="formData", type="boolean", description="Delete logo"),
     *     @SWG\Parameter(name="deleteCover", in="formData", type="boolean", description="Delete cover"),
     *     @SWG\Parameter(name="logo", in="formData", type="file", description="Logo"),
     *     @SWG\Parameter(name="cover", in="formData", type="file", description="Cover"),
     *     @SWG\Parameter(name="X-API-TOKEN", in="header", required=true, type="string")
     * )
     * @SWG\Response(response=200, description="Success")
     * @SWG\Response(response=401, description="Unauthorized user")
     * @SWG\Response(response=403, description="Forbidden for user")
     * @SWG\Response(response=404, description="Publication not found")
     * @SWG\Response(response=409, description="Error - see description for more information")
     * @SWG\Tag(name="Publication")
     * @param Request $request
     * @param ValidatorInterface $validator
     * @param $slug
     * @return JsonResponse
     */
    public function updatePublication(Request $request, ValidatorInterface $validator, $slug)
    {
        $em = $this->getDoctrine()->getManager();

        /**
         * @var Account $account
         */
        $account = $this->getUser();

        /**
         * @var Publication $publication
         */
        $publication = $em->getRepository(Publication::class)->findOneBy(['slug' => $slug]);
        if (!$publication) {
            return new JsonResponse(null, Response::HTTP_NOT_FOUND);
        }

        //  check if user has permission - only owner has
        $publicationMember = $em->getRepository(PublicationMember::class)->findOneBy(['publication' => $publication, 'member' => $account]);
        if (!$publicationMember || $publicationMember->getStatus() !== PublicationMember::TYPES['owner']) {
            return new JsonResponse(null, Response::HTTP_FORBIDDEN);
        }

        //  get data from submitted data
        $contentType = $request->getContentType();
        if ($contentType == 'application/json' || $contentType == 'json') {
            $content = $request->getContent();
            $content = json_decode($content, true);

            $title = $content['title'];
            $description = $content['description'];
            $listView = $content['listView'];
            $hideCover = $content['hideCover'];
            $tags = $content['tags'];
            $deleteLogo = $content['deleteLogo'];
            $deleteCover = $content['deleteCover'];
        } else {
            $title = $request->request->get('title');
            $description = $request->request->get('description');
            $listView = $request->request->get('listView');
            $hideCover = $request->request->get('hideCover');
            $tags = $request->request->get('tags');
            $deleteLogo = $request->request->get('deleteLogo');
            $deleteCover = $request->request->get('deleteCover');
        }

        try {
            $publication->setTitle($title);
            $publication->setDescription($description);
            $publication->setListView($listView);
            $publication->setHideCover($hideCover);

            //  delete tag relation
            $publication->removeAllTags();

            //  relate with Tags
            if ($tags) {
                $tags = explode(',', $tags);
                foreach ($tags as $tag) {
                    $tag = trim($tag);
                    $tagEntity = $em->getRepository(Tag::class)->findOneBy(['name' => $tag]);
                    if (!$tagEntity) {
                        $tagEntity = new Tag();
                        $tagEntity->setName($tag);

                        $em->persist($tagEntity);
                    }

                    $publication->addTag($tagEntity);
                }
            }

            //  local function to move uploaded files
            $moveFile = function (UploadedFile $file, string $path) {
                $fileName = md5(uniqid()) . '.' . $file->guessExtension();
                $file->move($path, $fileName);
                return $path . '/' . $fileName;
            };

            //  get upload path from configs
            $publicationsPath = $this->getParameter('publications_path');
            $currentPublicationPath = $publicationsPath . '/' . $publication->getSlug();

            /**
             * @var UploadedFile $logo
             */
            $logo = $request->files->get('logo');
            if ($logo instanceof UploadedFile) {
                $oldLogo = $publication->getLogo();
                if ($oldLogo && file_exists($oldLogo)) {
                    unlink($oldLogo);
                }
                $publication->setLogo($moveFile($logo, $currentPublicationPath));

                $imageAvgRgbColor = (new AvgColorPicker)->getImageAvgRgbByPath($publication->getLogo());
                $color = sprintf('%02X%02X%02X', $imageAvgRgbColor[0], $imageAvgRgbColor[1], $imageAvgRgbColor[2]);
                $publication->setColor($color);
            } elseif ($deleteLogo) {
                $oldLogo = $publication->getLogo();
                if ($oldLogo && file_exists($oldLogo)) {
                    unlink($oldLogo);
                }
                $publication->setLogo('');
                $publication->setColor(null);
            }

            /**
             * @var UploadedFile $cover
             */
            $cover = $request->files->get('cover');
            if ($cover instanceof UploadedFile) {
                $oldCover = $publication->getCover();
                if ($oldCover && file_exists($oldCover)) {
                    unlink($oldCover);
                }
                $publication->setCover($moveFile($cover, $currentPublicationPath));
            } elseif ($deleteCover) {
                $oldCover = $publication->getCover();
                if ($oldCover && file_exists($oldCover)) {
                    unlink($oldCover);
                }
                $publication->setCover('');
            }

            $errors = $validator->validate($publication);
            if (count($errors) > 0) {
                return new JsonResponse(['message' => 'system_error', 'content' => $errors->get(0)->getMessage()], Response::HTTP_CONFLICT);
            }

            $em->persist($publication);
            $em->flush();

            //  prepare return data
            $publication = $this->get('serializer')->normalize($publication, null, ['groups' => ['publication', 'tag']]);

            return new JsonResponse($publication);
        } catch (\Exception $e) {
            return new JsonResponse(['message' => $e->getMessage()], Response::HTTP_CONFLICT);
        }
    }

    /**
     * @Route("/{slug}", methods={"DELETE"})
     * @SWG\Delete(
     *     summary="Delete publication",
     *     consumes={"application/json"},
     *     @SWG\Parameter(name="X-API-TOKEN", in="header", required=true, type="string")
     * )
     * @SWG\Response(response=204, description="Success")
     * @SWG\Response(response=401, description="Unauthorized user")
     * @SWG\Response(response=403, description="Forbidden for user")
     * @SWG\Response(response=404, description="Publication not found")
     * @SWG\Response(response=409, description="Error - see description for more information")
     * @SWG\Tag(name="Publication")
     * @param $slug
     * @return Response
     */
    public function deletePublication($slug)
    {
        $em = $this->getDoctrine()->getManager();

        /**
         * @var Account $account
         */
        $account = $this->getUser();

        /**
         * @var Publication $publication
         */
        $publication = $em->getRepository(Publication::class)->findOneBy(['slug' => $slug]);
        if (!$publication) {
            return new JsonResponse(null, Response::HTTP_NOT_FOUND);
        }

        //  check if user has permission - only owner has
        $publicationMember = $em->getRepository(PublicationMember::class)->findOneBy(['publication' => $publication, 'member' => $account]);
        if (!$publicationMember || $publicationMember->getStatus() !== PublicationMember::TYPES['owner']) {
            return new JsonResponse(null, Response::HTTP_FORBIDDEN);
        }

        try {
            $logo = $publication->getLogo();
            if ($logo && file_exists($logo)) {
                unlink($logo);
            }

            $cover = $publication->getCover();
            if ($cover && file_exists($cover)) {
                unlink($cover);
            }

            $em->remove($publication);
            $em->flush();

            return new JsonResponse(null, Response::HTTP_NO_CONTENT);
        } catch (\Exception $e) {
            return new JsonResponse(['message' => $e->getMessage()], Response::HTTP_CONFLICT);
        }
    }

    /**
     * @Route("s/{count}/{slug}", methods={"GET"})
     * @SWG\Get(
     *     summary="Get publications",
     *     consumes={"application/json"},
     *     @SWG\Parameter(name="X-API-TOKEN", required=false, in="header", type="string")
     * )
     * @SWG\Response(response=200, description="Success")
     * @SWG\Response(response=401, description="Unauthorized user")
     * @SWG\Response(response=409, description="Error - see description for more information")
     * @SWG\Tag(name="Publication")
     * @param int $count
     * @param null $slug
     * @return Response
     */
    public function getPublications($count = 10, $slug = null)
    {
        $em = $this->getDoctrine()->getManager();

        /**
         * @var Account $account
         */
        $account = $this->getUser();

        /**
         * @var Publication $publication
         */
        $publication = $em->getRepository(Publication::class)->findOneBy(['slug' => $slug]);

        $publications = $this->getDoctrine()->getRepository(Publication::class)->getPublications($count + 1, $publication);

        if ($account && $publications) {
            foreach ($publications as $publication) {
                $memberStatus = 0;
                $publicationMember = $em->getRepository(PublicationMember::class)->findOneBy(['member' => $account, 'publication' => $publication]);

                //  if User is a Publication member return Publication info with members
                if ($publicationMember && in_array($publicationMember->getStatus(), [PublicationMember::TYPES['owner'], PublicationMember::TYPES['editor'], PublicationMember::TYPES['contributor']])) {
                    $memberStatus = $publicationMember->getStatus();
                }
                $publication->setMemberStatus($memberStatus);

                $subscription = $em->getRepository(Subscription::class)->findOneBy(['subscriber' => $account, 'publication' => $publication]);
                if ($subscription) {
                    $publication->setSubscribed(true);
                } else {
                    $publication->setSubscribed(false);
                }
            }
        }

        $publications = $this->get('serializer')->normalize($publications, null, ['groups' => ['publication', 'publicationMemberStatus', 'publicationSubscribed', 'tag']]);

        $more = false;
        if (count($publications) > $count) {
            $more = true;
            unset($publications[$count]);
        }

        return new JsonResponse(['publications' => $publications, 'more' => $more]);
    }

    /**
     * @Route("s-related", methods={"GET"})
     * @SWG\Get(
     *     summary="Get user related publications",
     *     consumes={"application/json"},
     *     @SWG\Parameter(name="X-API-TOKEN", required=true, in="header", type="string")
     * )
     * @SWG\Response(response=200, description="Success")
     * @SWG\Response(response=401, description="Unauthorized user")
     * @SWG\Response(response=409, description="Error - see description for more information")
     * @SWG\Tag(name="Publication")
     * @return Response
     */
    public function getRelatedPublications()
    {
        /**
         * @var Account $account
         */
        $account = $this->getUser();

        $owned = $this->getDoctrine()->getRepository(Publication::class)->getUserPublicationsOwner($account);
        $membership = $this->getDoctrine()->getRepository(Publication::class)->getUserPublicationsMember($account);
        $invitations = $this->getDoctrine()->getRepository(Publication::class)->getUserPublicationsInvitations($account);
        $requests = $this->getDoctrine()->getRepository(Publication::class)->getUserPublicationsRequests($account);

        if ($owned) {
            /**
             * @var Publication $publication
             */
            foreach ($owned as $publication) {
                $publicationMembers = $this->getDoctrine()->getRepository(Account::class)->getPublicationMembers($publication);
                $publication->setMembers($publicationMembers);
            }
        }

        if ($invitations) {
            /**
             * @var Publication $publication
             */
            foreach ($invitations as $publication) {
                $publicationMember = $this->getDoctrine()->getRepository(PublicationMember::class)->findOneBy(['publication' => $publication, 'member' => $account]);
                if ($publicationMember && $publicationMember->getInviter()) {
                    $publication->setInviter($publicationMember->getInviter());
                }
            }
        }

        $owned = $this->get('serializer')->normalize($owned, null, ['groups' => ['publication', 'tag', 'publicationMemberStatus', 'publicationMembers', 'accountBase', 'accountMemberStatus']]);
        $membership = $this->get('serializer')->normalize($membership, null, ['groups' => ['publication', 'tag', 'publicationMemberStatus']]);
        $invitations = $this->get('serializer')->normalize($invitations, null, ['groups' => ['publication', 'tag', 'publicationMemberStatus', 'publicationMemberInviter', 'accountBase']]);
        $requests = $this->get('serializer')->normalize($requests, null, ['groups' => ['publication', 'tag', 'publicationMemberStatus']]);

        return new JsonResponse(['owned' => $owned, 'membership' => $membership, 'invitations' => $invitations, 'requests' => $requests]);
    }

    /**
     * @Route("s-related/{type}", methods={"GET"})
     * @SWG\Get(
     *     summary="Get user related publications by type: owned / membership / invitations / requests",
     *     consumes={"application/json"},
     *     @SWG\Parameter(name="X-API-TOKEN", required=true, in="header", type="string")
     * )
     * @SWG\Response(response=200, description="Success")
     * @SWG\Response(response=401, description="Unauthorized user")
     * @SWG\Response(response=409, description="Error - see description for more information")
     * @SWG\Tag(name="Publication")
     * @param string $type
     * @return Response
     */
    public function getRelatedPublicationsByType(string $type)
    {
        /**
         * @var Account $account
         */
        $account = $this->getUser();

        switch ($type) {
            case 'owned';
                $publications = $this->getDoctrine()->getRepository(Publication::class)->getUserPublicationsOwner($account);
                if ($publications) {
                    /**
                     * @var Publication $publication
                     */
                    foreach ($publications as $publication) {
                        $publicationMembers = $this->getDoctrine()->getRepository(Account::class)->getPublicationMembers($publication);
                        $publication->setMembers($publicationMembers);
                    }
                }
                $publications = $this->get('serializer')->normalize($publications, null, ['groups' => ['publication', 'tag', 'publicationMemberStatus', 'publicationMembers', 'accountBase', 'accountMemberStatus']]);
                break;
            case 'membership':
                $publications = $this->getDoctrine()->getRepository(Publication::class)->getUserPublicationsMember($account);
                $publications = $this->get('serializer')->normalize($publications, null, ['groups' => ['publication', 'tag', 'publicationMemberStatus']]);
                break;
            case 'invitations':
                $publications = $this->getDoctrine()->getRepository(Publication::class)->getUserPublicationsInvitations($account);
                if ($publications) {
                    /**
                     * @var Publication $publication
                     */
                    foreach ($publications as $publication) {
                        $publicationMember = $this->getDoctrine()->getRepository(PublicationMember::class)->findOneBy(['publication' => $publication, 'member' => $account]);
                        if ($publicationMember && $publicationMember->getInviter()) {
                            $publication->setInviter($publicationMember->getInviter());
                        }
                    }
                }
                $publications = $this->get('serializer')->normalize($publications, null, ['groups' => ['publication', 'tag', 'publicationMemberStatus', 'publicationMemberInviter', 'accountBase']]);
                break;
            case 'requests':
                $publications = $this->getDoctrine()->getRepository(Publication::class)->getUserPublicationsRequests($account);
                $publications = $this->get('serializer')->normalize($publications, null, ['groups' => ['publication', 'tag', 'publicationMemberStatus']]);
                break;
            default:
                return new JsonResponse(null, Response::HTTP_NO_CONTENT);
        }

        return new JsonResponse($publications);
    }

    /**
     * @Route("/{slug}", methods={"GET"}, name="get_publication")
     * @SWG\Get(
     *     summary="Get publication",
     *     consumes={"application/json"},
     *     @SWG\Parameter(name="X-API-TOKEN", required=false, in="header", type="string")
     * )
     * @SWG\Response(response=200, description="Success")
     * @SWG\Response(response=401, description="Unauthorized user")
     * @SWG\Response(response=404, description="Publication not found")
     * @SWG\Response(response=409, description="Error - see description for more information")
     * @SWG\Tag(name="Publication")
     * @param $slug
     * @return Response
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function getPublication($slug)
    {
        $em = $this->getDoctrine()->getManager();

        /**
         * @var Account $account
         */
        $account = $this->getUser();

        /**
         * @var Publication $publication
         */
        $publication = $em->getRepository(Publication::class)->findOneBy(['slug' => $slug]);
        if (!$publication) {
            return new JsonResponse(null, Response::HTTP_NOT_FOUND);
        }

        //  get subscribers
        $subscribers = $em->getRepository(Account::class)->getPublicationSubscribers($publication);

        //  get articles total views
        $totalViews = $em->getRepository(ContentUnit::class)->getPublicationArticlesTotalViews($publication);

        //  if authorized user check if user is owner of Publication
        $memberStatus = 0;
        if ($account) {
            $publicationMember = $em->getRepository(PublicationMember::class)->findOneBy(['member' => $account, 'publication' => $publication]);

            //  if User is a Publication member return Publication info with members
            if ($publicationMember && in_array($publicationMember->getStatus(), [PublicationMember::TYPES['owner'], PublicationMember::TYPES['editor'], PublicationMember::TYPES['contributor']])) {
                $memberStatus = $publicationMember->getStatus();

                $publicationOwner = $this->getDoctrine()->getRepository(Account::class)->getPublicationOwner($publication);
                if ($publicationOwner) {
                    //  check if user subscribed to author
                    $subscribed = $em->getRepository(Subscription::class)->findOneBy(['subscriber' => $account, 'author' => $publicationOwner]);
                    if ($subscribed) {
                        $publicationOwner->setSubscribed(true);
                    } else {
                        $publicationOwner->setSubscribed(false);
                    }
                }
                $publicationOwner = $this->get('serializer')->normalize($publicationOwner, null, ['groups' => ['accountBase', 'accountMemberStatus']]);

                $publicationEditors = $this->getDoctrine()->getRepository(Account::class)->getPublicationEditors($publication);
                if ($publicationEditors) {
                    /**
                     * @var Account $publicationEditor
                     */
                    foreach ($publicationEditors as $publicationEditor) {
                        //  check if user subscribed to author
                        $subscribed = $em->getRepository(Subscription::class)->findOneBy(['subscriber' => $account, 'author' => $publicationEditor]);
                        if ($subscribed) {
                            $publicationEditor->setSubscribed(true);
                        } else {
                            $publicationEditor->setSubscribed(false);
                        }
                    }
                }
                $publicationEditors = $this->get('serializer')->normalize($publicationEditors, null, ['groups' => ['accountBase', 'accountMemberStatus', 'accountSubscribed']]);

                $publicationContributors = $this->getDoctrine()->getRepository(Account::class)->getPublicationContributors($publication);
                if ($publicationContributors) {
                    /**
                     * @var Account $publicationContributor
                     */
                    foreach ($publicationContributors as $publicationContributor) {
                        //  check if user subscribed to author
                        $subscribed = $em->getRepository(Subscription::class)->findOneBy(['subscriber' => $account, 'author' => $publicationContributor]);
                        if ($subscribed) {
                            $publicationContributor->setSubscribed(true);
                        } else {
                            $publicationContributor->setSubscribed(false);
                        }
                    }
                }
                $publicationContributors = $this->get('serializer')->normalize($publicationContributors, null, ['groups' => ['accountBase', 'accountMemberStatus', 'accountSubscribed']]);

                $publicationInvitations = $this->getDoctrine()->getRepository(Account::class)->getPublicationInvitations($publication);
                $publicationInvitations = $this->get('serializer')->normalize($publicationInvitations, null, ['groups' => ['accountBase', 'accountMemberStatus', 'accountEmail']]);

                $publicationRequests = $this->getDoctrine()->getRepository(Account::class)->getPublicationRequests($publication);
                $publicationRequests = $this->get('serializer')->normalize($publicationRequests, null, ['groups' => ['accountBase', 'accountMemberStatus']]);

                $subscribers = $this->get('serializer')->normalize($subscribers, null, ['groups' => ['accountBase']]);

                $publication = $this->get('serializer')->normalize($publication, null, ['groups' => ['publication', 'tag']]);
                $publication['memberStatus'] = $memberStatus;

                $publication['owner'] = $publicationOwner;
                $publication['editors'] = $publicationEditors;
                $publication['contributors'] = $publicationContributors;
                $publication['invitations'] = $publicationInvitations;
                $publication['requests'] = $publicationRequests;
                $publication['subscribers'] = $subscribers;
                $publication['subscribersCount'] = count($subscribers);
                $publication['membersCount'] = count($publicationEditors) + count($publicationContributors);
                $publication['views'] = intval($totalViews[0][1]);

                return new JsonResponse($publication);
            } elseif ($publicationMember) {
                $memberStatus = $publicationMember->getStatus();
            }
        }

        $publicationMembers = $this->getDoctrine()->getRepository(Account::class)->getPublicationMembers($publication);

        if ($memberStatus === PublicationMember::TYPES['invited_editor'] || $memberStatus === PublicationMember::TYPES['invited_contributor']) {
            $publicationMember = $this->getDoctrine()->getRepository(PublicationMember::class)->findOneBy(['publication' => $publication, 'member' => $account]);
            if ($publicationMember && $publicationMember->getInviter()) {
                $publication->setInviter($publicationMember->getInviter());
            }

            $publication = $this->get('serializer')->normalize($publication, null, ['groups' => ['publication', 'publicationMemberInviter', 'accountBase']]);
        } else {
            $publication = $this->get('serializer')->normalize($publication, null, ['groups' => ['publication']]);
        }

        $publication['memberStatus'] = $memberStatus;
        $publication['subscribersCount'] = count($subscribers);
        $publication['membersCount'] = count($publicationMembers);
        $publication['views'] = intval($totalViews[0][1]);

        return new JsonResponse($publication);
    }

    /**
     * @Route("/{slug}/invitation", methods={"POST"})
     * @SWG\Post(
     *     summary="Send invitation to become a member of publication",
     *     consumes={"application/json"},
     *     @SWG\Parameter(
     *         name="body",
     *         in="body",
     *         description="JSON Payload",
     *         required=true,
     *         format="application/json",
     *         @SWG\Schema(
     *             type="object",
     *             @SWG\Property(property="invitations", type="array", items={"type": "object", "properties": {"publicKey": {"type": "string"}, "email": {"type": "string"}, "asEditor": {"type": "boolean"}}}),
     *         )
     *     ),
     *     @SWG\Parameter(name="X-API-TOKEN", in="header", required=true, type="string")
     * )
     * @SWG\Response(response=204, description="Success")
     * @SWG\Response(response=404, description="Publication not found")
     * @SWG\Response(response=409, description="Error - see description for more information")
     * @SWG\Tag(name="Publication")
     * @param Request $request
     * @param string $slug
     * @return JsonResponse
     */
    public function inviteMember(Request $request, string $slug)
    {
        $em = $this->getDoctrine()->getManager();

        /**
         * @var Account $account
         */
        $account = $this->getUser();

        /**
         * @var Publication $publication
         */
        $publication = $em->getRepository(Publication::class)->findOneBy(['slug' => $slug]);
        if (!$publication) {
            return new JsonResponse(null, Response::HTTP_NOT_FOUND);
        }

        //  check if user has permission to add member into Publication
        $userPublicationMember = $em->getRepository(PublicationMember::class)->findOneBy(['publication' => $publication, 'member' => $account]);
        if (!$userPublicationMember || ($userPublicationMember->getStatus() != PublicationMember::TYPES['owner'] && $userPublicationMember->getStatus() != PublicationMember::TYPES['editor'])) {
            return new JsonResponse(null, Response::HTTP_FORBIDDEN);
        }

        //  get data from submitted data
        $contentType = $request->getContentType();
        if ($contentType == 'application/json' || $contentType == 'json') {
            $content = $request->getContent();
            $content = json_decode($content, true);

            $invitations = $content['invitations'];
        } else {
            $invitations = $request->request->get('invitations');
        }

        if (is_array($invitations)) {
            $notProceeded = [];
            foreach ($invitations as $invitation) {
                $asEditor = $invitation['asEditor'];

                if (isset($invitation['publicKey']) && ($publicKey = $invitation['publicKey'])) {
                    /**
                     * @var Account $member
                     */
                    $member = $em->getRepository(Account::class)->findOneBy(['publicKey' => $publicKey]);
                    if (!$member) {
                        $notProceeded[] = $publicKey;
                        continue;
                    }
                } elseif (isset($invitation['email']) && ($email = $invitation['email'])) {
                    /**
                     * @var Account $member
                     */
                    $member = $em->getRepository(Account::class)->findOneBy(['email' => $email]);
                    if (!$member) {
                        $member = new Account();
                        $member->setEmail($email);
                        $member->setWhole(0);
                        $member->setFraction(0);
                        $em->persist($member);
                        $em->flush();
                    }
                } else {
                    continue;
                }

                $publicationMember = $em->getRepository(PublicationMember::class)->findOneBy(['publication' => $publication, 'member' => $member]);
                if (!$publicationMember) {
                    $publicationMember = new PublicationMember();
                    $publicationMember->setPublication($publication);
                    $publicationMember->setMember($member);
                    $publicationMember->setInviter($account);
                    if ($asEditor && $userPublicationMember->getStatus() == PublicationMember::TYPES['owner']) {
                        $publicationMember->setStatus(PublicationMember::TYPES['invited_editor']);
                    } else {
                        $publicationMember->setStatus(PublicationMember::TYPES['invited_contributor']);
                    }

                    $em->persist($publicationMember);
                    $em->flush();

                    // notify invited user
                    $this->container->get('event_dispatcher')->dispatch(
                        PublicationInvitationRequestEvent::NAME,
                        new PublicationInvitationRequestEvent($publication, $account, $member)
                    );
                }
            }

            if (count($notProceeded) > 0) {
                return new JsonResponse($notProceeded);
            } else {
                return new JsonResponse(null, Response::HTTP_NO_CONTENT);
            }
        }

        return new JsonResponse(null, Response::HTTP_CONFLICT);
    }

    /**
     * @Route("/{slug}/invitation/cancel/{identifier}", methods={"DELETE"})
     * @SWG\Delete(
     *     summary="Cancel invitation to become a member of publication",
     *     consumes={"application/json"},
     *     @SWG\Parameter(name="X-API-TOKEN", in="header", required=true, type="string")
     * )
     * @SWG\Response(response=204, description="Success")
     * @SWG\Response(response=403, description="User has no permission to response")
     * @SWG\Response(response=404, description="Publication/User/Invitation not found")
     * @SWG\Response(response=409, description="Error - see description for more information")
     * @SWG\Tag(name="Publication")
     * @param string $slug
     * @param string $identifier
     * @return JsonResponse
     */
    public function cancelInvitation(string $slug, string $identifier)
    {
        $em = $this->getDoctrine()->getManager();

        /**
         * @var Account $account
         */
        $account = $this->getUser();

        /**
         * @var Publication $publication
         */
        $publication = $em->getRepository(Publication::class)->findOneBy(['slug' => $slug]);
        if (!$publication) {
            return new JsonResponse(null, Response::HTTP_NOT_FOUND);
        }

        /**
         * @var Account $member
         */
        $member = $em->getRepository(Account::class)->findOneBy(['publicKey' => $identifier]);
        if (!$member) {
            $member = $em->getRepository(Account::class)->findOneBy(['email' => $identifier]);
        }

        //  check if invitation exist
        $invitation = $em->getRepository(PublicationMember::class)->findOneBy(['publication' => $publication, 'member' => $member]);
        if (!$invitation || ($invitation->getStatus() != PublicationMember::TYPES['invited_editor'] && $invitation->getStatus() != PublicationMember::TYPES['invited_contributor'])) {
            return new JsonResponse(null, Response::HTTP_NOT_FOUND);
        }

        //  get Publication
        $publication = $invitation->getPublication();

        //  get User status in Publication & check permission
        $userStatus = $em->getRepository(PublicationMember::class)->findOneBy(['publication' => $publication, 'member' => $account]);
        if (
            !$userStatus ||
            ($userStatus->getStatus() != PublicationMember::TYPES['owner'] && $userStatus->getStatus() != PublicationMember::TYPES['editor']) ||
            ($userStatus->getStatus() == PublicationMember::TYPES['editor'] && $invitation->getStatus() == PublicationMember::TYPES['invited_editor'])
        ) {
            return new JsonResponse(null, Response::HTTP_FORBIDDEN);
        }

        //  delete invitation
        $em->remove($invitation);
        $em->flush();

        // notify invited user
        $this->container->get('event_dispatcher')->dispatch(
            PublicationInvitationCancelEvent::NAME,
            new PublicationInvitationCancelEvent($publication, $account, $member)
        );

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @Route("/{slug}/invitation/response", methods={"POST"})
     * @SWG\Post(
     *     summary="Accept invitation to become a member of publication",
     *     consumes={"application/json"},
     *     @SWG\Parameter(name="X-API-TOKEN", in="header", required=true, type="string")
     * )
     * @SWG\Response(response=204, description="Success")
     * @SWG\Response(response=403, description="User has no permission to response")
     * @SWG\Response(response=404, description="Publication/User not found")
     * @SWG\Response(response=409, description="Error - see description for more information")
     * @SWG\Tag(name="Publication")
     * @param string $slug
     * @return JsonResponse
     */
    public function acceptInvitationBecomeMember(string $slug)
    {
        $em = $this->getDoctrine()->getManager();

        /**
         * @var Account $account
         */
        $account = $this->getUser();

        /**
         * @var Publication $publication
         */
        $publication = $em->getRepository(Publication::class)->findOneBy(['slug' => $slug]);
        if (!$publication) {
            return new JsonResponse(null, Response::HTTP_NOT_FOUND);
        }

        //  check if invitation exist
        $invitation = $em->getRepository(PublicationMember::class)->findOneBy(['publication' => $publication, 'member' => $account]);
        if (!$invitation || ($invitation->getStatus() !== PublicationMember::TYPES['invited_contributor'] && $invitation->getStatus() !== PublicationMember::TYPES['invited_editor'])) {
            return new JsonResponse(null, Response::HTTP_NOT_FOUND);
        }

        if ($invitation->getStatus() === PublicationMember::TYPES['invited_contributor']) {
            $invitation->setStatus(PublicationMember::TYPES['contributor']);
        } else {
            $invitation->setStatus(PublicationMember::TYPES['editor']);
        }

        $em->persist($invitation);
        $em->flush();

        // notify inviter
        $this->container->get('event_dispatcher')->dispatch(
            PublicationInvitationAcceptEvent::NAME,
            new PublicationInvitationAcceptEvent($publication, $account, $invitation->getInviter())
        );

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @Route("/{slug}/invitation/response", methods={"DELETE"})
     * @SWG\Delete(
     *     summary="Reject invitation to become a member of publication",
     *     consumes={"application/json"},
     *     @SWG\Parameter(name="X-API-TOKEN", in="header", required=true, type="string")
     * )
     * @SWG\Response(response=204, description="Success")
     * @SWG\Response(response=403, description="User has no permission to response")
     * @SWG\Response(response=404, description="Publication/User not found")
     * @SWG\Response(response=409, description="Error - see description for more information")
     * @SWG\Tag(name="Publication")
     * @param string $slug
     * @return JsonResponse
     */
    public function rejectInvitationBecomeMember(string $slug)
    {
        $em = $this->getDoctrine()->getManager();

        /**
         * @var Account $account
         */
        $account = $this->getUser();

        /**
         * @var Publication $publication
         */
        $publication = $em->getRepository(Publication::class)->findOneBy(['slug' => $slug]);
        if (!$publication) {
            return new JsonResponse(null, Response::HTTP_NOT_FOUND);
        }

        //  check if invitation exist
        $invitation = $em->getRepository(PublicationMember::class)->findOneBy(['publication' => $publication, 'member' => $account]);
        if (!$invitation || ($invitation->getStatus() !== PublicationMember::TYPES['invited_contributor'] && $invitation->getStatus() !== PublicationMember::TYPES['invited_editor'])) {
            return new JsonResponse(null, Response::HTTP_NOT_FOUND);
        }

        $em->remove($invitation);
        $em->flush();

        // notify inviter
        $this->container->get('event_dispatcher')->dispatch(
            PublicationInvitationRejectEvent::NAME,
            new PublicationInvitationRejectEvent($publication, $account, $invitation->getInviter())
        );

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @Route("/{slug}/request", methods={"POST"})
     * @SWG\Post(
     *     summary="Send request to become a member of publication",
     *     consumes={"application/json"},
     *     @SWG\Parameter(name="X-API-TOKEN", in="header", required=true, type="string")
     * )
     * @SWG\Response(response=204, description="Success")
     * @SWG\Response(response=404, description="Publication not found")
     * @SWG\Response(response=409, description="Error - see description for more information")
     * @SWG\Tag(name="Publication")
     * @param string $slug
     * @return JsonResponse
     */
    public function becomeMember(string $slug)
    {
        $em = $this->getDoctrine()->getManager();

        /**
         * @var Account $account
         */
        $account = $this->getUser();

        /**
         * @var Publication $publication
         */
        $publication = $em->getRepository(Publication::class)->findOneBy(['slug' => $slug]);
        if (!$publication) {
            return new JsonResponse(null, Response::HTTP_NOT_FOUND);
        }

        //  check if user is a member of Publication
        $publicationMember = $em->getRepository(PublicationMember::class)->findOneBy(['publication' => $publication, 'member' => $account]);
        if (!$publicationMember) {
            $publicationMember = new PublicationMember();
            $publicationMember->setPublication($publication);
            $publicationMember->setMember($account);
            $publicationMember->setStatus(PublicationMember::TYPES['requested_contributor']);

            $em->persist($publicationMember);
            $em->flush();

            // notify owner & editors
            $this->container->get('event_dispatcher')->dispatch(
                PublicationMembershipRequestEvent::NAME,
                new PublicationMembershipRequestEvent($publication, $account)
            );
        }

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @Route("/{slug}/request", methods={"DELETE"})
     * @SWG\Delete(
     *     summary="Cancel request to become a member of publication",
     *     consumes={"application/json"},
     *     @SWG\Parameter(name="X-API-TOKEN", in="header", required=true, type="string")
     * )
     * @SWG\Response(response=204, description="Success")
     * @SWG\Response(response=404, description="Publication not found")
     * @SWG\Response(response=409, description="Error - see description for more information")
     * @SWG\Tag(name="Publication")
     * @param string $slug
     * @return JsonResponse
     */
    public function membershipCancel(string $slug)
    {
        $em = $this->getDoctrine()->getManager();

        /**
         * @var Account $account
         */
        $account = $this->getUser();

        /**
         * @var Publication $publication
         */
        $publication = $em->getRepository(Publication::class)->findOneBy(['slug' => $slug]);
        if (!$publication) {
            return new JsonResponse(null, Response::HTTP_NOT_FOUND);
        }

        //  check if user has an unanswered request to become a member of Publication
        $publicationMember = $em->getRepository(PublicationMember::class)->findOneBy(['publication' => $publication, 'member' => $account]);
        if ($publicationMember && $publicationMember->getStatus() === PublicationMember::TYPES['requested_contributor']) {
            //  delete request
            $em->remove($publicationMember);
            $em->flush();

            // notify owner & editors
            $this->container->get('event_dispatcher')->dispatch(
                PublicationMembershipRequestCancelEvent::NAME,
                new PublicationMembershipRequestCancelEvent($publication, $account)
            );
        }

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @Route("/{slug}/request/response/{publicKey}", methods={"POST"})
     * @SWG\Post(
     *     summary="Accept request to become a member of publication",
     *     consumes={"application/json"},
     *     @SWG\Parameter(name="X-API-TOKEN", in="header", required=true, type="string")
     * )
     * @SWG\Response(response=204, description="Success")
     * @SWG\Response(response=403, description="User has no permission to response")
     * @SWG\Response(response=404, description="Publication/User not found")
     * @SWG\Response(response=409, description="Error - see description for more information")
     * @SWG\Tag(name="Publication")
     * @param string $slug
     * @param string $publicKey
     * @return JsonResponse
     */
    public function acceptRequestBecomeMember(string $slug, string $publicKey)
    {
        $em = $this->getDoctrine()->getManager();

        /**
         * @var Account $account
         */
        $account = $this->getUser();

        /**
         * @var Publication $publication
         */
        $publication = $em->getRepository(Publication::class)->findOneBy(['slug' => $slug]);
        if (!$publication) {
            return new JsonResponse(null, Response::HTTP_NOT_FOUND);
        }

        /**
         * @var Account $member
         */
        $member = $em->getRepository(Account::class)->findOneBy(['publicKey' => $publicKey]);
        if (!$member) {
            return new JsonResponse(null, Response::HTTP_NOT_FOUND);
        }

        //  check if request exist
        $request = $em->getRepository(PublicationMember::class)->findOneBy(['publication' => $publication, 'member' => $member]);
        if (!$request || $request->getStatus() !== PublicationMember::TYPES['requested_contributor']) {
            return new JsonResponse(null, Response::HTTP_NOT_FOUND);
        }

        //  check if user has permission - only owner and editor have
        $publicationMember = $em->getRepository(PublicationMember::class)->findOneBy(['publication' => $publication, 'member' => $account]);
        if ($publicationMember && ($publicationMember->getStatus() === PublicationMember::TYPES['owner'] || $publicationMember->getStatus() === PublicationMember::TYPES['editor'])) {
            $request->setStatus(PublicationMember::TYPES['contributor']);

            $em->persist($request);
            $em->flush();

            // notify member
            $this->container->get('event_dispatcher')->dispatch(
                PublicationMembershipRequestAcceptEvent::NAME,
                new PublicationMembershipRequestAcceptEvent($publication, $account, $member)
            );

            return new JsonResponse(null, Response::HTTP_NO_CONTENT);
        } else {
            return new JsonResponse(null, Response::HTTP_FORBIDDEN);
        }
    }

    /**
     * @Route("/{slug}/request/response/{publicKey}", methods={"DELETE"})
     * @SWG\Delete(
     *     summary="Reject request to become a member of publication",
     *     consumes={"application/json"},
     *     @SWG\Parameter(name="X-API-TOKEN", in="header", required=true, type="string")
     * )
     * @SWG\Response(response=204, description="Success")
     * @SWG\Response(response=403, description="User has no permission to response")
     * @SWG\Response(response=404, description="Publication/User not found")
     * @SWG\Response(response=409, description="Error - see description for more information")
     * @SWG\Tag(name="Publication")
     * @param string $slug
     * @param string $publicKey
     * @return JsonResponse
     */
    public function rejectRequestBecomeMember(string $slug, string $publicKey)
    {
        $em = $this->getDoctrine()->getManager();

        /**
         * @var Account $account
         */
        $account = $this->getUser();

        /**
         * @var Publication $publication
         */
        $publication = $em->getRepository(Publication::class)->findOneBy(['slug' => $slug]);
        if (!$publication) {
            return new JsonResponse(null, Response::HTTP_NOT_FOUND);
        }

        /**
         * @var Account $member
         */
        $member = $em->getRepository(Account::class)->findOneBy(['publicKey' => $publicKey]);
        if (!$member) {
            return new JsonResponse(null, Response::HTTP_NOT_FOUND);
        }

        //  check if request exist
        $request = $em->getRepository(PublicationMember::class)->findOneBy(['publication' => $publication, 'member' => $member]);
        if (!$request || $request->getStatus() !== PublicationMember::TYPES['requested_contributor']) {
            return new JsonResponse(null, Response::HTTP_NOT_FOUND);
        }

        //  check if user has permission - only owner and editor have
        $publicationMember = $em->getRepository(PublicationMember::class)->findOneBy(['publication' => $publication, 'member' => $account]);
        if ($publicationMember && ($publicationMember->getStatus() === PublicationMember::TYPES['owner'] || $publicationMember->getStatus() === PublicationMember::TYPES['editor'])) {
            $em->remove($request);
            $em->flush();

            // notify member
            $this->container->get('event_dispatcher')->dispatch(
                PublicationMembershipRequestRejectEvent::NAME,
                new PublicationMembershipRequestRejectEvent($publication, $account, $member)
            );

            return new JsonResponse(null, Response::HTTP_NO_CONTENT);
        } else {
            return new JsonResponse(null, Response::HTTP_FORBIDDEN);
        }
    }

    /**
     * @Route("/{slug}/change-member-status", methods={"POST"})
     * @SWG\Post(
     *     summary="Change member status within publication",
     *     consumes={"application/json"},
     *     produces={"application/json"},
     *     @SWG\Parameter(
     *         name="body",
     *         in="body",
     *         description="JSON Payload",
     *         required=true,
     *         format="application/json",
     *         @SWG\Schema(
     *             type="object",
     *             @SWG\Property(property="publicKey", type="string"),
     *             @SWG\Property(property="status", type="integer"),
     *         )
     *     ),
     *     @SWG\Parameter(name="X-API-TOKEN", in="header", required=true, type="string")
     * )
     * @SWG\Response(response=204, description="Success")
     * @SWG\Response(response=400, description="Incorrect status")
     * @SWG\Response(response=401, description="Unauthorized user")
     * @SWG\Response(response=403, description="Permission denied")
     * @SWG\Response(response=409, description="Error - see description for more information")
     * @SWG\Tag(name="Publication")
     * @param Request $request
     * @param string $slug
     * @return JsonResponse
     */
    public function changeMemberStatus(Request $request, string $slug)
    {
        $em = $this->getDoctrine()->getManager();

        /**
         * @var Account $account
         */
        $account = $this->getUser();

        /**
         * @var Publication $publication
         */
        $publication = $em->getRepository(Publication::class)->findOneBy(['slug' => $slug]);
        if (!$publication) {
            return new JsonResponse(null, Response::HTTP_NOT_FOUND);
        }

        //  get data from submitted data
        $contentType = $request->getContentType();
        if ($contentType == 'application/json' || $contentType == 'json') {
            $content = $request->getContent();
            $content = json_decode($content, true);

            $publicKey = $content['publicKey'];
            $status = $content['status'];
        } else {
            $publicKey = $request->request->get('publicKey');
            $status = $request->request->get('status');
        }

        if ($status !== PublicationMember::TYPES['editor'] && $status !== PublicationMember::TYPES['contributor']) {
            return new JsonResponse(null, Response::HTTP_BAD_REQUEST);
        }

        /**
         * @var Account $member
         */
        $member = $em->getRepository(Account::class)->findOneBy(['publicKey' => $publicKey]);
        if (!$member) {
            return new JsonResponse(null, Response::HTTP_NOT_FOUND);
        }

        //  check if user has permission - only owner has
        $publicationMember = $em->getRepository(PublicationMember::class)->findOneBy(['publication' => $publication, 'member' => $account]);
        if (!$publicationMember || $publicationMember->getStatus() !== PublicationMember::TYPES['owner']) {
            return new JsonResponse(null, Response::HTTP_FORBIDDEN);
        }

        $publicationMember = $em->getRepository(PublicationMember::class)->findOneBy(['publication' => $publication, 'member' => $member]);
        if (!$publicationMember || ($publicationMember->getStatus() !== PublicationMember::TYPES['editor'] && $publicationMember->getStatus() !== PublicationMember::TYPES['contributor'])) {
            return new JsonResponse(null, Response::HTTP_NOT_FOUND);
        }

        $publicationMember->setStatus($status);
        $em->persist($publicationMember);
        $em->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @Route("/{slug}/delete-member/{publicKey}", methods={"DELETE"})
     * @SWG\Delete(
     *     summary="Delete member from publication",
     *     consumes={"application/json"},
     *     produces={"application/json"},
     *     @SWG\Parameter(name="X-API-TOKEN", in="header", required=true, type="string")
     * )
     * @SWG\Response(response=204, description="Success")
     * @SWG\Response(response=401, description="Unauthorized user")
     * @SWG\Response(response=403, description="Permission denied")
     * @SWG\Response(response=409, description="Error - see description for more information")
     * @SWG\Tag(name="Publication")
     * @param string $slug
     * @param string $publicKey
     * @return JsonResponse
     */
    public function deleteMember(string $slug, string $publicKey)
    {
        $em = $this->getDoctrine()->getManager();

        /**
         * @var Account $account
         */
        $account = $this->getUser();

        /**
         * @var Publication $publication
         */
        $publication = $em->getRepository(Publication::class)->findOneBy(['slug' => $slug]);
        if (!$publication) {
            return new JsonResponse(null, Response::HTTP_NOT_FOUND);
        }

        /**
         * @var Account $member
         */
        $member = $em->getRepository(Account::class)->findOneBy(['publicKey' => $publicKey]);
        if (!$member) {
            return new JsonResponse(null, Response::HTTP_NOT_FOUND);
        }

        //  check if user has permission - only owner has
        $publicationMember = $em->getRepository(PublicationMember::class)->findOneBy(['publication' => $publication, 'member' => $account]);
        if (!$publicationMember || $publicationMember->getStatus() !== PublicationMember::TYPES['owner']) {
            return new JsonResponse(null, Response::HTTP_FORBIDDEN);
        }

        $publicationMember = $em->getRepository(PublicationMember::class)->findOneBy(['publication' => $publication, 'member' => $member]);
        if (!$publicationMember || ($publicationMember->getStatus() !== PublicationMember::TYPES['editor'] && $publicationMember->getStatus() !== PublicationMember::TYPES['contributor'])) {
            return new JsonResponse(null, Response::HTTP_NOT_FOUND);
        }

        $em->remove($publicationMember);
        $em->flush();

        // notify member
        $this->container->get('event_dispatcher')->dispatch(
            PublicationMembershipCancelEvent::NAME,
            new PublicationMembershipCancelEvent($publication, $account, $member)
        );

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @Route("/{slug}/subscribe", methods={"POST"})
     * @SWG\Post(
     *     summary="Subscribe to Publication",
     *     consumes={"application/json"},
     *     @SWG\Parameter(name="X-API-TOKEN", in="header", required=true, type="string")
     * )
     * @SWG\Response(response=204, description="Success")
     * @SWG\Response(response=404, description="Publication not found")
     * @SWG\Response(response=409, description="Error - see description for more information")
     * @SWG\Tag(name="Publication")
     * @param string $slug
     * @return JsonResponse
     */
    public function subscribe(string $slug)
    {
        $em = $this->getDoctrine()->getManager();

        /**
         * @var Account $account
         */
        $account = $this->getUser();

        /**
         * @var Publication $publication
         */
        $publication = $em->getRepository(Publication::class)->findOneBy(['slug' => $slug]);
        if (!$publication) {
            return new JsonResponse(null, Response::HTTP_NOT_FOUND);
        }

        //  check if user is already subscribed
        $subscription = $em->getRepository(Subscription::class)->findOneBy(['publication' => $publication, 'subscriber' => $account]);
        if (!$subscription) {
            $subscription = new Subscription();
            $subscription->setPublication($publication);
            $subscription->setSubscriber($account);

            $em->persist($subscription);
            $em->flush();
        }

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @Route("/{slug}/subscribe", methods={"DELETE"})
     * @SWG\Delete(
     *     summary="Unsubscribe from Publication",
     *     consumes={"application/json"},
     *     @SWG\Parameter(name="X-API-TOKEN", in="header", required=true, type="string")
     * )
     * @SWG\Response(response=204, description="Success")
     * @SWG\Response(response=404, description="Publication not found")
     * @SWG\Response(response=409, description="Error - see description for more information")
     * @SWG\Tag(name="Publication")
     * @param string $slug
     * @return JsonResponse
     */
    public function unsubscribe(string $slug)
    {
        $em = $this->getDoctrine()->getManager();

        /**
         * @var Account $account
         */
        $account = $this->getUser();

        /**
         * @var Publication $publication
         */
        $publication = $em->getRepository(Publication::class)->findOneBy(['slug' => $slug]);
        if (!$publication) {
            return new JsonResponse(null, Response::HTTP_NOT_FOUND);
        }

        //  check if user is already subscribed
        $subscription = $em->getRepository(Subscription::class)->findOneBy(['publication' => $publication, 'subscriber' => $account]);
        if ($subscription) {
            $em->remove($subscription);
            $em->flush();
        }

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @Route("/{slug}/contents/{count}/{boostedCount}/{fromUri}", methods={"GET"})
     * @SWG\Get(
     *     summary="Get Publication contents",
     *     consumes={"application/json"},
     *     produces={"application/json"},
     * )
     * @SWG\Response(response=200, description="Success")
     * @SWG\Response(response=404, description="Publication not found")
     * @SWG\Response(response=409, description="Error - see description for more information")
     * @SWG\Tag(name="Publication")
     * @param string $slug
     * @param int $count
     * @param int $boostedCount
     * @param string $fromUri
     * @param CUService $contentUnitService
     * @return JsonResponse
     */
    public function contents(string $slug, int $count, int $boostedCount, string $fromUri, CUService $contentUnitService)
    {
        $em = $this->getDoctrine()->getManager();

        /**
         * @var Publication $publication
         */
        $publication = $em->getRepository(Publication::class)->findOneBy(['slug' => $slug]);
        if (!$publication) {
            return new JsonResponse(null, Response::HTTP_NOT_FOUND);
        }

        $fromContentUnit = null;
        if ($fromUri) {
            $fromContentUnit = $em->getRepository(ContentUnit::class)->findOneBy(['uri' => $fromUri]);
        }

        $contentUnits = $em->getRepository(ContentUnit::class)->getPublicationArticles($publication, $count + 1, $fromContentUnit);

        //  prepare data to return
        if ($contentUnits) {
            try {
                $contentUnits = $contentUnitService->prepare($contentUnits);
            } catch (Exception $e) {
                return new JsonResponse($e->getMessage(), Response::HTTP_CONFLICT);
            }
        }

        $boostedContentUnits = $em->getRepository(ContentUnit::class)->getBoostedArticles($boostedCount, $contentUnits);
        if ($boostedContentUnits) {
            try {
                $boostedContentUnits = $contentUnitService->prepare($boostedContentUnits, true);
            } catch (Exception $e) {
                return new JsonResponse($e->getMessage(), Response::HTTP_CONFLICT);
            }
        }

        $contentUnits = $this->get('serializer')->normalize($contentUnits, null, ['groups' => ['contentUnitFull', 'file', 'accountBase', 'publication']]);
        $boostedContentUnits = $this->get('serializer')->normalize($boostedContentUnits, null, ['groups' => ['contentUnitFull', 'file', 'accountBase', 'publication']]);

        //  check if more content exist
        $more = false;
        if (count($contentUnits) > $count) {
            unset($contentUnits[$count]);
            $more = true;
        }

        //  add boosted articles into random positions of main articles list
        for ($i = 0; $i < count($boostedContentUnits); $i++) {
            $aaa = [$boostedContentUnits[$i]];
            array_splice($contentUnits, rand(0, count($contentUnits) - 1), 0, $aaa);
        }

        return new JsonResponse(['data' => $contentUnits, 'more' => $more]);
    }
}