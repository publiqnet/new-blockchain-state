<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 6/30/20
 * Time: 5:42 PM
 */

namespace App\Controller\Admin;

use App\Entity\Account;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Sonata\AdminBundle\Controller\CRUDController as Controller;
use Symfony\Component\HttpFoundation\RedirectResponse;

class AccountController extends Controller
{
    public function enableViewsAction()
    {
        /**
         * @var Account $object
         */
        $object = $this->admin->getSubject();

        if (!$object) {
            throw new NotFoundHttpException('Unable to find the object');
        }

        try {
            $object->setDisableViews(false);
            $this->admin->update($object);

            $this->addFlash('sonata_flash_success', sprintf('%s has excluded', $object->getPublicKey()));
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
            $this->addFlash('sonata_flash_error', $errorMessage);
        }

        return new RedirectResponse($this->admin->generateUrl('list'));
    }

    public function disableViewsAction()
    {
        /**
         * @var Account $object
         */
        $object = $this->admin->getSubject();

        if (!$object) {
            throw new NotFoundHttpException('Unable to find the object');
        }

        try {
            $object->setDisableViews(true);
            $this->admin->update($object);

            $this->addFlash('sonata_flash_success', sprintf('%s has excluded', $object->getPublicKey()));
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
            $this->addFlash('sonata_flash_error', $errorMessage);
        }

        return new RedirectResponse($this->admin->generateUrl('list'));
    }
}