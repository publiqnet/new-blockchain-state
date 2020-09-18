<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 9/18/20
 * Time: 5:30 PM
 */

namespace App\Controller;

use Swift_Mailer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Swagger\Annotations as SWG;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class SupportApiController
 * @package App\Controller
 *
 * @Route("/api/support")
 */
class SupportApiController extends AbstractController
{
    /**
     * @Route("", methods={"POST"})
     * @SWG\Post(
     *     summary="Support",
     *     consumes={"application/json"},
     *     @SWG\Parameter(
     *         name="body",
     *         in="body",
     *         description="JSON Payload",
     *         required=true,
     *         format="application/json",
     *         @SWG\Schema(
     *             type="object",
     *             @SWG\Property(property="fullname", type="string"),
     *             @SWG\Property(property="email", type="string"),
     *             @SWG\Property(property="subject", type="string"),
     *             @SWG\Property(property="message", type="string"),
     *         )
     *     )
     * )
     * @SWG\Response(response=200, description="Success")
     * @SWG\Response(response=404, description="Not found")
     * @SWG\Response(response=409, description="Error - see description for more information")
     * @SWG\Tag(name="Support")
     * @param Request $request
     * @param Swift_Mailer $mailer
     * @return JsonResponse
     */
    public function resetPassword(Request $request, Swift_Mailer $mailer)
    {
        //  get data from submitted data
        $contentType = $request->getContentType();
        if ($contentType == 'application/json' || $contentType == 'json') {
            $content = $request->getContent();
            $content = json_decode($content, true);

            $fullname = $content['fullname'];
            $email = $content['email'];
            $subject = $content['subject'];
            $message = $content['message'];
        } else {
            $fullname = $request->request->get('fullname');
            $email = $request->request->get('email');
            $subject = $request->request->get('subject');
            $message = $request->request->get('message');
        }

        try {
            $emailBody = $this->renderView(
                'emails/support.html.twig',
                ['fullname' => $fullname, 'email' => $email, 'subject' => $subject, 'message' => $message]
            );

            $messageObj = (new \Swift_Message('Support'))
                ->setFrom('no-reply@publiq.network', 'SlogMedia')
                ->setTo('grigor@arattauna.com')
                ->setBody($emailBody, 'text/html');
            $mailer->send($messageObj);

            return new JsonResponse(null, Response::HTTP_NO_CONTENT);
        } catch (\Exception $e) {
            return new JsonResponse(['msg' => $e->getMessage()], Response::HTTP_CONFLICT);
        }
    }
}