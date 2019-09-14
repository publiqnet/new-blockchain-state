<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 7/26/19
 * Time: 5:40 PM
 */

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\HasLifecycleCallbacks;

/**
 * @ORM\Table(name="publication_article")
 * @ORM\Entity(repositoryClass="App\Repository\PublicationMemberRepository")
 * @HasLifecycleCallbacks
 */
class PublicationArticle
{
    /**
     * @var int
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var Publication
     * @ORM\ManyToOne(targetEntity="App\Entity\Publication", inversedBy="articles")
     * @ORM\JoinColumn(name="publication_id", referencedColumnName="id", nullable=false)
     */
    private $publication;

    /**
     * @var string
     * @ORM\Column(type="string", length=96, nullable=false)
     */
    private $uri;


    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @return Publication
     */
    public function getPublication()
    {
        return $this->publication;
    }

    /**
     * @param Publication $publication
     */
    public function setPublication(Publication $publication)
    {
        $this->publication = $publication;
    }

    /**
     * @return string
     */
    public function getUri()
    {
        return $this->uri;
    }

    /**
     * @param string $uri
     */
    public function setUri(string $uri)
    {
        $this->uri = $uri;
    }
}