<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 1/9/20
 * Time: 4:44 PM
 */

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * NetworkHomeSliderAdmin
 *
 * @ORM\Table(name="network_pbq_content")
 * @ORM\Entity
 */
class NetworkPbqContent
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var string
     * @ORM\Column(type="string")
     * @Groups({"networkPbqContent"})
     * @Assert\NotBlank()
     */
    private $title;

    /**
     * @var string
     * @ORM\Column(type="text", nullable=true)
     * @Groups({"networkPbqContent"})
     */
    private $content;

    /**
     * @var string
     * @ORM\Column(type="string", nullable=true)
     * @Groups({"networkPbqContent"})
     */
    private $link;

    /**
     * @var string
     * @ORM\Column(type="string", nullable=true)
     * @Groups({"networkPbqContent"})
     */
    private $image;

    /**
     * @var string
     * @ORM\Column(type="string", nullable=true)
     * @Groups({"networkPbqContent"})
     */
    private $imageHover;

    /**
     * @var \DateTime $created
     * @Gedmo\Timestampable(on="create")
     * @ORM\Column(type="datetime", nullable=false)
     */
    private $created;

    /**
     * @var \DateTime $updated
     * @Gedmo\Timestampable(on="update")
     * @ORM\Column(type="datetime", nullable=false)
     */
    private $updated;


    public function __toString()
    {
        return $this->title ? $this->title: '';
    }

    /**
     * @return int
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getTitle(): ?string
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
    public function getContent(): ?string
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
     * @return \DateTime
     */
    public function getCreated(): \DateTime
    {
        return $this->created;
    }

    public function setCreated(\DateTimeInterface $created): self
    {
        $this->created = $created;

        return $this;
    }

    /**
     * @return \DateTime
     */
    public function getUpdated(): \DateTime
    {
        return $this->updated;
    }

    /**
     * @param \DateTime $updated
     */
    public function setUpdated(\DateTime $updated)
    {
        $this->updated = $updated;
    }

    /**
     * Updates the hash value to force the preUpdate and postUpdate events to fire
     */
    public function refreshUpdated()
    {
        $this->setUpdated(new \DateTime());
    }

    /**
     * @return string
     */
    public function getLink(): ?string
    {
        return $this->link;
    }

    /**
     * @param string $link
     */
    public function setLink(string $link)
    {
        $this->link = $link;
    }

    /**
     * @return string
     */
    public function getImage(): ?string
    {
        return $this->image;
    }

    /**
     * @param string|null $image
     */
    public function setImage($image)
    {
        $this->image = $image;
    }

    /**
     * @return string
     */
    public function getImageHover(): ?string
    {
        return $this->imageHover;
    }

    /**
     * @param string|null $imageHover
     */
    public function setImageHover($imageHover)
    {
        $this->imageHover = $imageHover;
    }
}