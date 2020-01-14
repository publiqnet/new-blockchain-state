<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 1/14/20
 * Time: 12:18 PM
 */

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * NetworkShowcaseProject
 *
 * @ORM\Table(name="network_showcase_project")
 * @ORM\Entity
 */
class NetworkShowcaseProject
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
     * @Groups({"networkShowcaseProject"})
     * @Assert\NotBlank()
     */
    private $title;

    /**
     * @var string
     * @ORM\Column(type="string", nullable=true)
     * @Groups({"networkShowcaseProject"})
     */
    private $link;

    /**
     * @var string
     * @ORM\Column(type="string", nullable=true)
     * @Groups({"networkShowcaseProject"})
     */
    private $description;

    /**
     * @var bool
     * @ORM\Column(type="boolean", options={"default": 0})
     * @Groups({"networkShowcaseProject"})
     */
    private $pOAuth;

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
    public function setLink(?string $link)
    {
        $this->link = $link;
    }

    /**
     * @return string
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * @param string $description
     */
    public function setDescription(?string $description)
    {
        $this->description = $description;
    }

    /**
     * @return bool
     */
    public function isPOAuth(): bool
    {
        return $this->pOAuth;
    }

    /**
     * @param bool $pOAuth
     */
    public function setPOAuth(bool $pOAuth)
    {
        $this->pOAuth = $pOAuth;
    }
}