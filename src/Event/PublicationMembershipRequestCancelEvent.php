<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 4/11/19
 * Time: 11:28 AM
 */

namespace App\Event;

use App\Entity\Publication;
use App\Entity\Account;
use Symfony\Component\EventDispatcher\Event;

class PublicationMembershipRequestCancelEvent extends Event
{
    const NAME = 'publication.membership.request.cancel';

    private $publication;
    private $performer;

    public function __construct(Publication $publication, Account $performer)
    {
        $this->publication = $publication;
        $this->performer = $performer;
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
}