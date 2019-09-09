<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 9/9/19
 * Time: 6:28 PM
 */

namespace App\Event;

use App\Entity\Publication;
use App\Entity\Account;
use Symfony\Component\EventDispatcher\Event;

class PublicationMembershipLeaveEvent extends Event
{
    const NAME = 'publication.membership.leave';

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