<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 4/11/19
 * Time: 12:01 PM
 */

namespace App\Event;

use App\Entity\Publication;
use App\Entity\Account;
use Symfony\Component\EventDispatcher\Event;

class PublicationMembershipRequestAcceptEvent extends Event
{
    const NAME = 'publication.membership.request.accept';

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