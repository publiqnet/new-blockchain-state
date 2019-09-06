<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 3/14/19
 * Time: 4:20 PM
 */

namespace App\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Guard\AbstractGuardAuthenticator;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class TokenAuthenticator extends AbstractGuardAuthenticator
{
    /**
     * Called on every request. Return whatever credentials you want to
     * be passed to getUser(). Returning null will cause this authenticator
     * to be skipped.
     */
    public function getCredentials(Request $request)
    {
        $route = $request->get('_route');

        // filter public routes
        if (in_array($route, [
            'nelmio_api_doc_index',
        ])) {
            return;
        }

        if (!$token = $request->headers->get('X-API-TOKEN')) {
            // No token?
            $token = null;
        }

        // What you return here will be passed to getUser() as $credentials
        return array(
            'token' => $token,
        );
    }

    public function getUser($credentials, UserProviderInterface $userProvider)
    {
        $apiKey = $credentials['token'];

        if (null === $apiKey) {
            return;
        }

        // if a User object, checkCredentials() is called
        return $userProvider->loadUserByUsername($apiKey);
    }

    public function checkCredentials($credentials, UserInterface $user)
    {
        // check credentials - e.g. make sure the password is valid
        // check if token expired

        // return true to cause authentication success
        return true;
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, $providerKey)
    {
        // on success, let the request continue
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception)
    {
        $route = $request->get('_route');
        if (in_array($route, [
            'get_content_by_uri',
        ])) {
            return null;
        }

        $data = array(
            'message' => strtr($exception->getMessageKey(), $exception->getMessageData())

            // or to translate this message
            // $this->translator->trans($exception->getMessageKey(), $exception->getMessageData())
        );

        return new JsonResponse($data, Response::HTTP_UNAUTHORIZED);
    }

    /**
     * Called when authentication is needed, but it's not sent
     */
    public function start(Request $request, AuthenticationException $authException = null)
    {
        $data = array(
            // you might translate this message
            'message' => 'Authentication Required'
        );

        return new JsonResponse($data, Response::HTTP_UNAUTHORIZED);
    }

    public function supportsRememberMe()
    {
        return false;
    }

    public function supports(Request $request)
    {
        return $request->headers->has('X-API-TOKEN');
    }
}