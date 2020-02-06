<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 2/6/20
 * Time: 6:57 PM
 */

namespace App\Event;

use App\Entity\Account;
use Symfony\Component\EventDispatcher\Event;

class UnsubscribeUserEvent extends Event
{
    const NAME = 'unsubscribe.user';

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