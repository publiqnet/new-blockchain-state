<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 2/21/19
 * Time: 2:11 PM
 */

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Class File
 * @package App\Entity
 *
 * @ORM\Table(name="file")
 * @ORM\Entity
 */
class File
{
    /**
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @ORM\Column(name="uri", type="string", length=64, unique=true)
     * @Groups({"file"})
     */
    private $uri;

    /**
     * @ORM\Column(name="mime_type", type="string", length=128, nullable=true)
     * @Groups({"file"})
     */
    private $mimeType;

    /**
     * @ORM\Column(name="size", type="integer", nullable=true)
     * @Groups({"file"})
     */
    private $size;

    /**
     * @Groups({"file"})
     */
    private $url;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Account", inversedBy="files")
     * @Groups({"file"})
     */
    private $author;

    /**
     * @ORM\OneToOne(targetEntity="App\Entity\Transaction", mappedBy="file")
     */
    private $transaction;

    /**
     * @ORM\ManyToMany(targetEntity="App\Entity\ContentUnit", mappedBy="files")
     */
    private $contentUnits;

    /**
     * @ORM\ManyToMany(targetEntity="App\Entity\Account", mappedBy="storageFiles")
     */
    private $storages;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\ContentUnit", mappedBy="cover")
     */
    private $covers;


    public function __construct()
    {
        $this->contentUnits = new ArrayCollection();
        $this->storages = new ArrayCollection();
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
    public function getMimeType()
    {
        return $this->mimeType;
    }

    /**
     * @param mixed $mimeType
     */
    public function setMimeType($mimeType)
    {
        $this->mimeType = $mimeType;
    }

    /**
     * @return mixed
     */
    public function getSize()
    {
        return $this->size;
    }

    /**
     * @param mixed $size
     */
    public function setSize($size)
    {
        $this->size = $size;
    }

    /**
     * @return mixed
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * @param mixed $url
     */
    public function setUrl($url)
    {
        $this->url = $url;
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
    public function getTransaction()
    {
        return $this->transaction;
    }

    /**
     * @return mixed
     */
    public function getContentUnits()
    {
        return $this->contentUnits;
    }

    /**
     * @return mixed
     */
    public function getStorages()
    {
        return $this->storages;
    }

    /**
     * @return mixed
     */
    public function getCovers()
    {
        return $this->covers;
    }
}