<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 1/10/20
 * Time: 2:01 PM
 */

namespace App\Event;

use App\Entity\ContentUnit;
use App\Entity\Account;
use Symfony\Component\EventDispatcher\Event;

class ArticleNewEvent extends Event
{
    const NAME = 'article.new';

    private $publisher;
    private $article;

    public function __construct(Account $publisher, ContentUnit $article)
    {
        $this->publisher = $publisher;
        $this->article = $article;
    }

    /**
     * @return Account
     */
    public function getPublisher()
    {
        return $this->publisher;
    }

    /**
     * @return ContentUnit
     */
    public function getArticle()
    {
        return $this->article;
    }
}