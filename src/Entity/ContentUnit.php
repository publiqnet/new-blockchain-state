<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 2/28/19
 * Time: 2:50 PM
 */

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Class ContentUnit
 * @package App\Entity
 *
 * @ORM\Table(name="content_unit")
 * @ORM\Entity
 */
class ContentUnit
{
    /**
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @ORM\Column(name="uri", type="string", length=64, unique=true)
     */
    private $uri;

    /**
     * @ORM\Column(name="blockchain_content_id", type="string", length=64, unique=true)
     */
    private $contentId;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Account", inversedBy="authorContentUnits")
     */
    private $author;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Account", inversedBy="channelContentUnits")
     */
    private $channel;

    /**
     * @ORM\ManyToMany(targetEntity="App\Entity\File", inversedBy="contentUnits")
     */
    private $files;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Content", inversedBy="contentUnits")
     */
    private $content;

    /**
     * @ORM\OneToOne(targetEntity="App\Entity\Transaction", mappedBy="contentUnit")
     */
    private $transaction;


    public function __construct()
    {
        $this->files = new ArrayCollection();
    }

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return mixed
     */
    public function getUri()
    {
        return $this->uri;
    }

    /**
     * @param mixed $uri
     */
    public function setUri($uri)
    {
        $this->uri = $uri;
    }

    /**
     * @return mixed
     */
    public function getContentId()
    {
        return $this->contentId;
    }

    /**
     * @param mixed $contentId
     */
    public function setContentId($contentId)
    {
        $this->contentId = $contentId;
    }

    /**
     * @return mixed
     */
    public function getAuthor()
    {
        return $this->author;
    }

    /**
     * @param mixed $author
     */
    public function setAuthor($author)
    {
        $this->author = $author;
    }

    /**
     * @return mixed
     */
    public function getChannel()
    {
        return $this->channel;
    }

    /**
     * @param mixed $channel
     */
    public function setChannel($channel)
    {
        $this->channel = $channel;
    }

    /**
     * @return mixed
     */
    public function getFiles()
    {
        return $this->files;
    }

    public function addFile(File $file)
    {
        $this->files[] = $file;
    }

    /**
     * @return mixed
     */
    public function getContent()
    {
        return $this->content;
    }

    /**
     * @param mixed $content
     */
    public function setContent($content)
    {
        $this->content = $content;
    }

    /**
     * @return mixed
     */
    public function getTransaction()
    {
        return $this->transaction;
    }

    /**
     * @param mixed $transaction
     */
    public function setTransaction($transaction)
    {
        $this->transaction = $transaction;
    }
}