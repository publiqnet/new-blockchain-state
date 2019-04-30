<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 3/19/19
 * Time: 6:20 PM
 */

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\HasLifecycleCallbacks;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Table(name="publication")
 * @ORM\Entity(repositoryClass="App\Repository\PublicationRepository")
 * @HasLifecycleCallbacks
 */
class Publication
{
    /**
     * @var int
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var string
     * @ORM\Column(type="string")
     * @Groups({"publication"})
     */
    private $slug;

    /**
     * @var string
     *
     * @ORM\Column(name="title", type="string", length=256)
     * @Assert\NotBlank(message="Title can not be empty")
     * @Groups({"publication"})
     */
    private $title;

    /**
     * @var string
     *
     * @ORM\Column(name="description", type="text", nullable=true)
     * @Groups({ "publication"})
     */
    private $description;

    /**
     * @ORM\Column(type="string", nullable=true)
     * @Assert\Image(
     *     maxSize="2M",
     *     maxSizeMessage="max upload size: {{ limit }}{{ suffix }}",
     *     mimeTypesMessage="The mime type of the file is invalid ({{ type }}). Allowed mime types are {{ types }}.",
     *     mimeTypes = {
     *         "image/png",
     *         "image/jpeg",
     *         "image/jpg"
     *     }
     * )
     * @Groups({"publication"})
     */
    private $cover;

    /**
     * @ORM\Column(type="string", nullable=true)
     * @Assert\File(
     *     maxSize="2M",
     *     maxSizeMessage="max upload size: {{ limit }}{{ suffix }}",
     *     mimeTypesMessage="The mime type of the file is invalid ({{ type }}). Allowed mime types are {{ types }}.",
     *     mimeTypes = {
     *         "image/png",
     *         "image/jpeg",
     *         "image/jpg"
     *     }
     * )
     * @Groups({"publication"})
     */
    private $logo;

    /**
     * @var string
     * @ORM\Column(type="string", nullable=true)
     * @Groups({"publication"})
     */
    private $color;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\PublicationMember", mappedBy="publication", cascade="remove")
     * @Groups({"publicationMembers"})
     */
    private $members;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Notification", mappedBy="publication", cascade="remove")
     * @Groups({"publicationMembers"})
     */
    private $notifications;

    /**
     * @var integer
     * @Groups({"publicationMemberStatus"})
     */
    private $memberStatus;

    /**
     * @var mixed
     * @Groups({"publicationMemberInviter"})
     */
    private $inviter;

    public function __construct()
    {
        $this->members = new ArrayCollection();
        $this->notifications = new ArrayCollection();
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->title ?? '';
    }

    /**
     * Get id
     *
     * @return integer
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getSlug()
    {
        return $this->slug;
    }

    /**
     * @param string $slug
     */
    public function setSlug(string $slug)
    {
        $this->slug = $slug;
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
     * @throws \Exception
     */
    public function setTitle(string $title)
    {
        $this->title = $title;

        //  generate slug if not exist
        if (!$this->slug) {
            $this->slug = md5(random_bytes(128));
        }
    }

    /**
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * @param $description
     */
    public function setDescription($description)
    {
        $this->description = $description;
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
     * @return Publication
     */
    public function setCover($cover)
    {
        $this->cover = $cover;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getLogo()
    {
        return $this->logo;
    }

    /**
     * @param mixed $logo
     * @return Publication
     */
    public function setLogo($logo)
    {
        $this->logo = $logo;

        return $this;
    }

    /**
     * @return string
     */
    public function getColor()
    {
        return $this->color;
    }

    /**
     * @param string $color
     */
    public function setColor(string $color)
    {
        $this->color = $color;
    }

    /**
     * Get members
     */
    public function getMembers()
    {
        return $this->members;
    }

    /**
     * @param $members
     */
    public function setMembers($members)
    {
        $this->members = $members;
    }

    /**
     * Get notifications
     */
    public function getNotifications()
    {
        return $this->notifications;
    }

    /**
     * @return int
     */
    public function getMemberStatus()
    {
        return $this->memberStatus;
    }

    /**
     * @param int $memberStatus
     */
    public function setMemberStatus(int $memberStatus)
    {
        $this->memberStatus = $memberStatus;
    }

    /**
     * @return mixed
     */
    public function getInviter()
    {
        return $this->inviter;
    }

    /**
     * @param mixed $inviter
     */
    public function setInviter($inviter)
    {
        $this->inviter = $inviter;
    }
}