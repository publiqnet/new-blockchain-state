<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 4/10/19
 * Time: 5:44 PM
 */

namespace App\Event;

use App\Entity\Publication;
use App\Entity\Account;
use Symfony\Component\EventDispatcher\Event;

class PublicationInvitationAcceptEvent extends Event
{
    const NAME = 'publication.invitation.accept';

    private $publication;
    private $performer;
    private $user;

    public function __construct(Publication $publication, Account $performer, Account $user)
    {
        $this->publication = $publication;
        $this->performer = $performer;
        $this->user = $user;
    }

    /**
     * @return Publication
     */
    public function getPublication()
    {
        return $this->publication;
    }

    /**
     * @return Account
     */
    public function getPerformer()
    {
        return $this->performer;
    }

    /**
     * @return Account
     */
    public function getUser()
    {
        return $this->user;
    }
}