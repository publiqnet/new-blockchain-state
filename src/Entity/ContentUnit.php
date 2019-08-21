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
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Class ContentUnit
 * @package App\Entity
 *
 * @ORM\Table(name="content_unit")
 * @ORM\Entity(repositoryClass="App\Repository\ContentUnitRepository")
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
     * @Groups({"contentUnit", "contentUnitFull"})
     */
    private $uri;

    /**
     * @ORM\Column(name="blockchain_content_id", type="string", length=64)
     */
    private $contentId;

    /**
     * @ORM\Column(name="title", type="string", length=256, nullable=false)
     * @Groups({"contentUnit", "contentUnitFull"})
     */
    private $title;

    /**
     * @ORM\Column(name="text", type="text", nullable=false)
     * @Groups({"contentUnit", "contentUnitFull"})
     */
    private $text;

    /**
     * @ORM\Column(name="views", type="integer", nullable=true)
     * @Groups({"contentUnit", "contentUnitFull"})
     */
    private $views = 0;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\File", inversedBy="covers")
     * @Groups({"contentUnit", "contentUnitFull"})
     */
    private $cover;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Account", inversedBy="authorContentUnits")
     * @Groups({"contentUnitFull"})
     */
    private $author;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Account", inversedBy="channelContentUnits")
     */
    private $channel;

    /**
     * @ORM\ManyToMany(targetEntity="App\Entity\File", inversedBy="contentUnits")
     * @Groups({"contentUnit", "contentUnitFull"})
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

    /**
     * @var integer
     * @Groups({"contentUnit", "contentUnitFull"})
     */
    private $published;

    /**
     * @var boolean
     * @Groups({"contentUnit", "contentUnitFull"})
     */
    private $boosted;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Publication", inversedBy="contentUnits")
     * @Groups({"contentUnit", "contentUnitFull"})
     */
    private $publication;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\BoostedContentUnit", mappedBy="contentUnit")
     */
    private $boosts;


    public function __construct()
    {
        $this->files = new ArrayCollection();
        $this->boosts = new ArrayCollection();
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
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * @param mixed $title
     */
    public function setTitle($title)
    {
        $this->title = $title;
    }

    /**
     * @return mixed
     */
    public function getText()
    {
        return $this->text;
    }

    /**
     * @param mixed $text
     */
    public function setText($text)
    {
        $this->text = $text;
    }

    /**
     * @return mixed
     */
    public function getViews()
    {
        return $this->views;
    }

    /**
     * @param mixed $views
     */
    public function setViews($views)
    {
        $this->views = $views;
    }

    /**
     * @param integer $views
     */
    public function plusViews($views)
    {
        $this->views += $views;
    }

    /**
     * @param integer $views
     */
    public function minusViews($views)
    {
        $this->views += $views;
    }

    /**
     * @return mixed
     */
    public function getCover()
    {
        return $this->cover;
    }

    /**
     * @param mixed $cover
     */
    public function setCover($cover)
    {
        $this->cover = $cover;
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

    /**
     * @return int
     */
    public function getPublished()
    {
        return $this->published;
    }

    /**
     * @param int $published
     */
    public function setPublished(int $published)
    {
        $this->published = $published;
    }

    /**
     * @return mixed
     */
    public function getPublication()
    {
        return $this->publication;
    }

    /**
     * @param mixed $publication
     */
    public function setPublication($publication)
    {
        $this->publication = $publication;
    }

    /**
     * @return mixed
     */
    public function getBoosts()
    {
        return $this->boosts;
    }

    /**
     * @return bool
     */
    public function isBoosted()
    {
        return $this->boosted;
    }

    /**
     * @param bool $boosted
     */
    public function setBoosted(bool $boosted)
    {
        $this->boosted = $boosted;
    }
}