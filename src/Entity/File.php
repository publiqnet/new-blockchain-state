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
     */
    private $uri;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Account", inversedBy="files")
     */
    private $author;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Transaction", mappedBy="file")
     */
    private $transaction;

    /**
     * @ORM\ManyToMany(targetEntity="App\Entity\ContentUnit", mappedBy="files")
     */
    private $contentUnits;


    public function __construct()
    {
        $this->contentUnits = new ArrayCollection();
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
}