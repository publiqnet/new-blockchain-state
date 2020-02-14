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
use Doctrine\ORM\Mapping\Index;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Table(name="publication", indexes={@Index(columns={"title", "description"}, flags={"fulltext"})})
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
     * @Groups({"publication", "publicationSeo", "trending", "publicationBase"})
     */
    private $slug;

    /**
     * @var string
     *
     * @ORM\Column(name="title", type="string", length=256)
     * @Assert\NotBlank(message="Title can not be empty")
     * @Groups({"publication", "publicationSeo", "trending", "publicationBase"})
     */
    private $title;

    /**
     * @var string
     *
     * @ORM\Column(name="description", type="text", nullable=true)
     * @Groups({ "publication", "publicationSeo"})
     */
    private $description;

    /**
     * @ORM\Column(type="string", nullable=true)
     * @Assert\Image(
     *     maxSize="5M",
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
     *     maxSize="5M",
     *     maxSizeMessage="max upload size: {{ limit }}{{ suffix }}",
     *     mimeTypesMessage="The mime type of the file is invalid ({{ type }}). Allowed mime types are {{ types }}.",
     *     mimeTypes = {
     *         "image/png",
     *         "image/jpeg",
     *         "image/jpg"
     *     }
     * )
     * @Groups({"publication", "trending", "publicationBase"})
     */
    private $logo;

    /**
     * @var string
     * @ORM\Column(type="string", nullable=true)
     * @Groups({"publication"})
     */
    private $color;

    /**
     * @ORM\Column(name="list_view", type="boolean", nullable=true)
     * @Groups({"publication"})
     */
    private $listView = 0;

    /**
     * @ORM\Column(name="hide_cover", type="boolean", nullable=true)
     * @Groups({"publication"})
     */
    private $hideCover = 0;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\PublicationMember", mappedBy="publication", cascade="remove")
     * @Groups({"publicationMembers"})
     */
    private $members;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Notification", mappedBy="publication", cascade="remove")
     */
    private $notifications;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Subscription", mappedBy="publication", cascade="remove")
     */
    private $subscribers;

    /**
     * @var integer
     * @Groups({"publicationMemberStatus"})
     */
    private $memberStatus;

    /**
     * @var integer
     * @Groups({"publication"})
     */
    private $storiesCount;

    /**
     * @var mixed
     * @Groups({"publicationMemberInviter"})
     */
    private $inviter;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\ContentUnit", mappedBy="publication")
     */
    private $contentUnits;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\PublicationArticle", mappedBy="publication", cascade={"remove"})
     */
    private $articles;

    /**
     * @ORM\ManyToMany(targetEntity="App\Entity\Tag", inversedBy="publications")
     * @Groups({"publication"})
     */
    private $tags;

    /**
     * @var boolean
     * @Groups({"publicationSubscribed"})
     */
    private $subscribed;

    /**
     * @var int
     * @Groups({"publication", "trending"})
     */
    private $subscribersCount;

    /**
     * @var int
     * @Groups({"publication", "trending"})
     */
    private $membersCount;

    /**
     * @var int
     * @ORM\Column(name="cover_position_x", type="integer", options={"default": 0})
     * @Groups({"publication"})
     */
    private $coverPositionX = 0;

    /**
     * @var int
     * @ORM\Column(name="cover_position_y", type="integer", options={"default": 0})
     * @Groups({"publication"})
     */
    private $coverPositionY = 0;

    /**
     * @ORM\Column(name="social_image", type="string", nullable=true)
     * @Assert\File()
     * @Groups({"publicationSeo"})
     */
    private $socialImage;

    /**
     * @ORM\Column(name="trending_position", type="integer", options={"default":0})
     */
    private $trendingPosition = 0;

    /**
     * @var int
     * @Groups({"publicationStats"})
     */
    private $totalViews;

    public function __construct()
    {
        $this->members = new ArrayCollection();
        $this->notifications = new ArrayCollection();
        $this->subscribers = new ArrayCollection();
        $this->contentUnits = new ArrayCollection();
        $this->articles = new ArrayCollection();
        $this->tags = new ArrayCollection();
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
     * @param mixed $color
     */
    public function setColor($color)
    {
        $this->color = $color;
    }

    /**
     * @return mixed
     */
    public function getListView()
    {
        return $this->listView;
    }

    /**
     * @param mixed $listView
     */
    public function setListView($listView)
    {
        $this->listView = $listView;
    }

    /**
     * @return mixed
     */
    public function getHideCover()
    {
        return $this->hideCover;
    }

    /**
     * @param mixed $hideCover
     */
    public function setHideCover($hideCover)
    {
        $this->hideCover = $hideCover;
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
     * Get subscribers
     */
    public function getSubscribers()
    {
        return $this->subscribers;
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
     * @return int
     */
    public function getStoriesCount()
    {
        return $this->storiesCount;
    }

    /**
     * @param int $storiesCount
     */
    public function setStoriesCount(int $storiesCount)
    {
        $this->storiesCount = $storiesCount;
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
    public function getArticles()
    {
        return $this->articles;
    }

    /**
     * @return mixed
     */
    public function getTags()
    {
        return $this->tags;
    }

    public function addTag(Tag $tag)
    {
        $this->tags->add($tag);
    }

    public function removeTag(Tag $tag)
    {
        $this->tags->removeElement($tag);
    }

    public function removeAllTags()
    {
        $this->tags->clear();
    }

    /**
     * @return bool
     */
    public function isSubscribed()
    {
        return $this->subscribed;
    }

    /**
     * @param bool $subscribed
     */
    public function setSubscribed(bool $subscribed)
    {
        $this->subscribed = $subscribed;
    }

    /**
     * @return int
     */
    public function getSubscribersCount()
    {
        return $this->subscribersCount;
    }

    /**
     * @param int $subscribersCount
     */
    public function setSubscribersCount(int $subscribersCount)
    {
        $this->subscribersCount = $subscribersCount;
    }

    /**
     * @return int
     */
    public function getMembersCount()
    {
        return $this->membersCount;
    }

    /**
     * @param int $membersCount
     */
    public function setMembersCount(int $membersCount)
    {
        $this->membersCount = $membersCount;
    }

    /**
     * @return mixed
     */
    public function getSocialImage()
    {
        return $this->socialImage;
    }

    /**
     * @param mixed $socialImage
     */
    public function setSocialImage($socialImage)
    {
        $this->socialImage = $socialImage;
    }

    /**
     * @return int
     */
    public function getCoverPositionX()
    {
        return $this->coverPositionX;
    }

    /**
     * @param int $coverPositionX
     */
    public function setCoverPositionX(int $coverPositionX)
    {
        $this->coverPositionX = $coverPositionX;
    }

    /**
     * @return int
     */
    public function getCoverPositionY()
    {
        return $this->coverPositionY;
    }

    /**
     * @param int $coverPositionY
     */
    public function setCoverPositionY(int $coverPositionY)
    {
        $this->coverPositionY = $coverPositionY;
    }

    /**
     * @return mixed
     */
    public function getTrendingPosition()
    {
        return $this->trendingPosition;
    }

    /**
     * @param mixed $trendingPosition
     */
    public function setTrendingPosition($trendingPosition)
    {
        $this->trendingPosition = $trendingPosition;
    }

    /**
     * @return int
     */
    public function getTotalViews()
    {
        return $this->totalViews;
    }

    /**
     * @param int $totalViews
     */
    public function setTotalViews(int $totalViews)
    {
        $this->totalViews = $totalViews;
    }
}