<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 9/9/19
 * Time: 3:48 PM
 */

namespace App\Event;

use App\Entity\ContentUnit;
use App\Entity\Account;
use Symfony\Component\EventDispatcher\Event;

class UserPreferenceEvent extends Event
{
    const NAME = 'user.preference';

    private $user;
    private $article;

    public function __construct(Account $user, ContentUnit $article)
    {
        $this->user = $user;
        $this->article = $article;
    }

    /**
     * @return Account
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * @return ContentUnit
     */
    public function getArticle()
    {
        return $this->article;
    }
}