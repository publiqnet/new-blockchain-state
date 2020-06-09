<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 5/26/20
 * Time: 1:30 PM
 */

namespace App\Controller\Admin;

use App\Entity\Account;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Sonata\AdminBundle\Controller\CRUDController as Controller;
use Symfony\Component\HttpFoundation\RedirectResponse;

class ChannelController extends Controller
{
    public function excludeAction()
    {
        /**
         * @var Account $object
         */
        $object = $this->admin->getSubject();

        if (!$object) {
            throw new NotFoundHttpException('Unable to find the object');
        }

        try {
            $object->setExcluded(true);
            $this->admin->update($object);

            $this->addFlash('sonata_flash_success', sprintf('%s has excluded', $object->getPublicKey()));
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
            $this->addFlash('sonata_flash_error', $errorMessage);
        }

        return new RedirectResponse($this->admin->generateUrl('list'));
    }

    public function includeAction()
    {
        /**
         * @var Account $object
         */
        $object = $this->admin->getSubject();

        if (!$object) {
            throw new NotFoundHttpException('Unable to find the object');
        }

        try {
            $object->setExcluded(false);
            $this->admin->update($object);

            $this->addFlash('sonata_flash_success', sprintf('%s has excluded', $object->getPublicKey()));
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
            $this->addFlash('sonata_flash_error', $errorMessage);
        }

        return new RedirectResponse($this->admin->generateUrl('list'));
    }
}