<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 12/20/19
 * Time: 4:59 PM
 */

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\HasLifecycleCallbacks;

/**
 * @ORM\Table(name="draft_file")
 * @ORM\Entity
 * @HasLifecycleCallbacks
 */
class DraftFile
{
    /**
     * @var int
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var Draft
     * @ORM\ManyToOne(targetEntity="App\Entity\Draft", inversedBy="files")
     * @ORM\JoinColumn(name="draft_id", referencedColumnName="id", nullable=false)
     */
    private $draft;

    /**
     * @var string
     * @ORM\Column(type="string", length=96, nullable=false)
     */
    private $uri;

    /**
     * @var string
     * @ORM\Column(type="string", length=128, nullable=false)
     */
    private $path;


    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @return Draft
     */
    public function getDraft()
    {
        return $this->draft;
    }

    /**
     * @param Draft $draft
     */
    public function setDraft(Draft $draft)
    {
        $this->draft = $draft;
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

    /**
     * @return string
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * @param string $path
     */
    public function setPath(string $path)
    {
        $this->path = $path;
    }
}