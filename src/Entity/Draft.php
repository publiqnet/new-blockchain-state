<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 3/19/19
 * Time: 4:12 PM
 */

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\HasLifecycleCallbacks;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @HasLifecycleCallbacks
 * @ORM\Table(name="draft")
 * @ORM\Entity
 */
class Draft
{
    /**
     * @var int
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @Groups({"draft", "draftList"})
     */
    private $id;

    /**
     * @var Account
     * @ORM\ManyToOne(targetEntity="App\Entity\Account", inversedBy="drafts")
     */
    private $account;

    /**
     * @var string
     * @ORM\Column(name="title", type="string", length=255)
     * @Groups({"draft", "draftList"})
     */
    private $title;

    /**
     * @var string
     * @ORM\Column(name="headline", type="text", length=300, nullable=true)
     * @Groups({"draft"})
     */
    private $headline;

    /**
     * @var string
     * @ORM\Column(name="content", type="text", nullable=false)
     * @Groups({"draft"})
     */
    private $content;

    /**
     * @var boolean
     * @ORM\Column(name="for_adults", type="boolean", options={"default":0})
     * @Groups({"draft"})
     */
    private $forAdults = 0;

    /**
     * @var string
     * @ORM\Column(name="reference", type="string", length=256,  nullable=true)
     * @Groups({"draft"})
     */
    private $reference;

    /**
     * @var string
     * @ORM\Column(name="source_of_material", type="string", length=256, nullable=true)
     * @Groups({"draft"})
     */
    private $sourceOfMaterial;

    /**
     * @var \DateTime $created
     * @Gedmo\Timestampable(on="create")
     * @ORM\Column(type="datetime", nullable=true)
     * @Groups({"draft"})
     */
    private $created;

    /**
     * @var \DateTime $updated
     * @Gedmo\Timestampable(on="update")
     * @ORM\Column(type="datetime", nullable=true)
     * @Groups({"draft"})
     */
    private $updated;

    /**
     * @var array
     * @ORM\Column(name="content_uris", type="array", nullable=true)
     * @Groups({"draft"})
     */
    private $contentUris = [];

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return Account
     */
    public function getAccount()
    {
        return $this->account;
    }

    /**
     * @param Account $account
     */
    public function setAccount(Account $account)
    {
        $this->account = $account;
    }

    /**
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * @param string $title
     */
    public function setTitle(string $title)
    {
        $this->title = $title;
    }

    /**
     * @return string
     */
    public function getHeadline()
    {
        return $this->headline;
    }

    /**
     * @param string $headline
     */
    public function setHeadline(string $headline)
    {
        $this->headline = $headline;
    }

    /**
     * @return string | null
     */
    public function getContent()
    {
        return $this->content;
    }

    /**
     * @param string $content
     */
    public function setContent(string $content)
    {
        $this->content = $content;
    }

    /**
     * @return boolean
     */
    public function getForAdults()
    {
        return $this->forAdults;
    }

    /**
     * @param boolean $forAdults
     */
    public function setForAdults($forAdults)
    {
        $this->forAdults = $forAdults;
    }

    /**
     * @return string
     */
    public function getReference()
    {
        return $this->reference;
    }

    /**
     * @param string $reference
     */
    public function setReference($reference)
    {
        $this->reference = $reference;
    }

    /**
     * Get sourceOfMaterial
     *
     * @return string
     */
    public function getSourceOfMaterial()
    {
        return $this->sourceOfMaterial;
    }

    /**
     * @param string $sourceOfMaterial
     */
    public function setSourceOfMaterial($sourceOfMaterial)
    {
        $this->sourceOfMaterial = $sourceOfMaterial;
    }

    /**
     * @return mixed
     */
    public function getCreated()
    {
        return $this->created;
    }

    /**
     * @param mixed $created
     */
    public function setCreated($created)
    {
        $this->created = $created;
    }

    /**
     * @return mixed
     */
    public function getUpdated()
    {
        return $this->updated;
    }

    /**
     * @param mixed $updated
     */
    public function setUpdated($updated)
    {
        $this->updated = $updated;
    }

    /**
     * @return array
     */
    public function getContentUris()
    {
        return $this->contentUris;
    }

    /**
     * @param array $contentUris
     */
    public function setContentUris(array $contentUris)
    {
        $this->contentUris = $contentUris;
    }
}