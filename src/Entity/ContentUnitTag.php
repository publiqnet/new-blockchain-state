<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 8/26/19
 * Time: 12:42 PM
 */

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\HasLifecycleCallbacks;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ORM\Table(name="content_unit_tag")
 * @ORM\Entity()
 * @HasLifecycleCallbacks
 */
class ContentUnitTag
{
    /**
     * @var int
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var Tag
     * @ORM\ManyToOne(targetEntity="App\Entity\Tag", inversedBy="contentUnits")
     * @ORM\JoinColumn(name="tag_id", referencedColumnName="id", nullable=false)
     * @Groups({"tag"})
     */
    private $tag;

    /**
     * @var ContentUnit
     * @ORM\ManyToOne(targetEntity="App\Entity\ContentUnit", inversedBy="tags")
     * @ORM\JoinColumn(name="content_unit_id", referencedColumnName="id", nullable=true)
     */
    private $contentUnit;

    /**
     * @var string
     * @ORM\Column(type="string", length=96, nullable=true)
     */
    private $contentUnitUri;


    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @return Tag
     */
    public function getTag(): ?Tag
    {
        return $this->tag;
    }

    /**
     * @param Tag $tag
     */
    public function setTag(Tag $tag)
    {
        $this->tag = $tag;
    }

    /**
     * @return mixed
     */
    public function getContentUnit()
    {
        return $this->contentUnit;
    }

    /**
     * @param mixed $contentUnit
     */
    public function setContentUnit($contentUnit)
    {
        $this->contentUnit = $contentUnit;
    }

    /**
     * @return string
     */
    public function getContentUnitUri()
    {
        return $this->contentUnitUri;
    }

    /**
     * @param string $contentUnitUri
     */
    public function setContentUnitUri(string $contentUnitUri)
    {
        $this->contentUnitUri = $contentUnitUri;
    }
}