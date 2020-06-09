<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 1/10/20
 * Time: 2:01 PM
 */

namespace App\Event;

use App\Entity\ContentUnit;
use Symfony\Component\EventDispatcher\Event;

class ArticleNewEvent extends Event
{
    const NAME = 'article.new';

    private $article;

    public function __construct(ContentUnit $article)
    {
        $this->article = $article;
    }

    /**
     * @return ContentUnit
     */
    public function getArticle()
    {
        return $this->article;
    }
}