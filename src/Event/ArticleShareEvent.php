<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 1/10/20
 * Time: 6:42 PM
 */

namespace App\Event;

use App\Entity\ContentUnit;
use Symfony\Component\EventDispatcher\Event;

class ArticleShareEvent extends Event
{
    const NAME = 'article.share';

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