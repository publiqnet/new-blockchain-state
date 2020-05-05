<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 5/5/2020
 * Time: 5:02 PM
 */

namespace App\Event;

use App\Entity\ContentUnit;
use App\Entity\Account;
use Symfony\Component\EventDispatcher\Event;

class ArticleBoostedByOtherEvent extends Event
{
    const NAME = 'article.boosted_by_other';

    private $performer;
    private $article;

    public function __construct(Account $performer, ContentUnit $article)
    {
        $this->performer = $performer;
        $this->article = $article;
    }

    /**
     * @return Account
     */
    public function getPerformer()
    {
        return $this->performer;
    }

    /**
     * @return ContentUnit
     */
    public function getArticle()
    {
        return $this->article;
    }
}