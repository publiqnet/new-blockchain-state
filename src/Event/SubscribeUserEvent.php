<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 1/10/20
 * Time: 6:05 PM
 */

namespace App\Event;

use App\Entity\Account;
use Symfony\Component\EventDispatcher\Event;

class SubscribeUserEvent extends Event
{
    const NAME = 'subscribe.user';

    private $performer;
    private $author;

    public function __construct(Account $performer, Account $author)
    {
        $this->performer = $performer;
        $this->author = $author;
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
    public function getAuthor()
    {
        return $this->author;
    }
}