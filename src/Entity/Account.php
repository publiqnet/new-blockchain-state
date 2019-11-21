<?php
/**
 * Created by PhpStorm.
 * User: grigor
 * Date: 9/25/18
 * Time: 11:08 AM
 */

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\Index;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Class Account
 * @package App\Entity
 *
 * @ORM\Table(name="account", indexes={@Index(columns={"first_name", "last_name", "bio"}, flags={"fulltext"})})
 * @ORM\Entity(repositoryClass="App\Repository\AccountRepository")
 */
class Account implements UserInterface
{
    /**
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var string
     * @ORM\Column(name="public_key", type="string", length=128, nullable=true)
     * @Groups({"account", "accountBase"})
     */
    private $publicKey;

    /**
     * @var string
     * @ORM\Column(name="old_public_key", type="string", length=128, nullable=true)
     */
    private $oldPublicKey;

    /**
     * @var string
     * @ORM\Column(name="email", type="string", length=128, nullable=true)
     * @Groups({"account", "accountEmail"})
     */
    private $email;

    /**
     * @var string
     * @ORM\Column(name="first_name", type="string", length=64, nullable=true)
     * @Groups({"account", "accountBase"})
     */
    private $firstName;

    /**
     * @var string
     * @ORM\Column(name="last_name", type="string", length=64, nullable=true)
     * @Groups({"account", "accountBase"})
     */
    private $lastName;

    /**
     * @var string
     * @ORM\Column(name="bio", type="text", nullable=true)
     * @Groups({"account", "accountBase"})
     */
    private $bio;

    /**
     * @ORM\Column(name="image", type="string", nullable=true)
     * @Assert\File(
     *     maxSize="8M",
     *     maxSizeMessage="max upload size: {{ limit }}{{ suffix }}",
     *     mimeTypesMessage="The mime type of the file is invalid ({{ type }}). Allowed mime types are {{ types }}.",
     *     mimeTypes = {
     *         "image/png",
     *         "image/jpeg",
     *         "image/jpg"
     *     }
     * )
     * @Groups({"account", "accountBase"})
     */
    private $image;

    /**
     * @var string
     * @ORM\Column(type="string", length=16, nullable=true)
     * @Groups({"account"})
     */
    private $language;

    /**
     * @ORM\Column(name="whole", type="integer")
     * @Groups({"account"})
     */
    private $whole;

    /**
     * @ORM\Column(name="fraction", type="integer")
     * @Groups({"account"})
     */
    private $fraction;

    /**
     * @ORM\Column(name="channel", type="boolean")
     */
    private $channel = 0;

    /**
     * @ORM\Column(name="storage", type="boolean")
     */
    private $storage = 0;

    /**
     * @ORM\Column(name="list_view", type="boolean", nullable=true)
     */
    private $listView = 0;

    /**
     * @var string
     * @ORM\Column(type="string", length=64, nullable=true)
     * @Groups({"account"})
     */
    private $url;

    /**
     * @ORM\Column(name="blockchain", type="boolean")
     */
    private $blockchain = 0;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Block", mappedBy="account", fetch="EXTRA_LAZY")
     */
    private $signedBlocks;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Reward", mappedBy="to", fetch="EXTRA_LAZY")
     */
    private $rewards;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\File", mappedBy="author", fetch="EXTRA_LAZY")
     */
    private $files;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\ContentUnit", mappedBy="author", fetch="EXTRA_LAZY")
     */
    private $authorContentUnits;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\ContentUnit", mappedBy="channel", fetch="EXTRA_LAZY")
     */
    private $channelContentUnits;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Content", mappedBy="channel", fetch="EXTRA_LAZY")
     */
    private $contents;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Transfer", mappedBy="from", fetch="EXTRA_LAZY")
     */
    private $fromTransfers;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Transfer", mappedBy="to", fetch="EXTRA_LAZY")
     */
    private $toTransfers;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Draft", mappedBy="account", fetch="EXTRA_LAZY")
     */
    private $drafts;

    /**
     * @var string
     * @ORM\Column(type="string", length=64, unique=true, nullable=true)
     * @Groups({"account"})
     */
    private $apiKey;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\PublicationMember", mappedBy="member", fetch="EXTRA_LAZY")
     */
    private $publications;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\PublicationMember", mappedBy="inviter", fetch="EXTRA_LAZY")
     */
    private $publicationInvitees;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Notification", mappedBy="performer", fetch="EXTRA_LAZY")
     */
    private $performedNotifications;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\UserNotification", mappedBy="account", fetch="EXTRA_LAZY")
     */
    private $notifications;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\UserPreference", mappedBy="account", fetch="EXTRA_LAZY")
     */
    private $preferences;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\UserViewLog", mappedBy="user", fetch="EXTRA_LAZY")
     */
    private $viewLogs;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Subscription", mappedBy="subscriber", fetch="EXTRA_LAZY")
     */
    private $subscriptions;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Subscription", mappedBy="author", cascade="remove", fetch="EXTRA_LAZY")
     */
    private $subscribers;

    /**
     * @var integer
     * @Groups({"accountMemberStatus"})
     */
    private $memberStatus;

    /**
     * @ORM\ManyToMany(targetEntity="App\Entity\File", inversedBy="storages", fetch="EXTRA_LAZY")
     */
    private $storageFiles;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\BoostedContentUnit", mappedBy="sponsor", fetch="EXTRA_LAZY")
     */
    private $boostedContentUnits;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\ContentUnitViews", mappedBy="channel", fetch="EXTRA_LAZY")
     */
    private $views;

    /**
     * @var boolean
     * @Groups({"accountSubscribed"})
     */
    private $subscribed;

    /**
     * @var int
     * @Groups({"accountBase"})
     */
    private $subscribersCount;

    public function __construct()
    {
        $this->signedBlocks = new ArrayCollection();
        $this->rewards = new ArrayCollection();
        $this->files = new ArrayCollection();
        $this->authorContentUnits = new ArrayCollection();
        $this->channelContentUnits = new ArrayCollection();
        $this->contents = new ArrayCollection();
        $this->fromTransfers = new ArrayCollection();
        $this->toTransfers = new ArrayCollection();
        $this->drafts = new ArrayCollection();
        $this->publications = new ArrayCollection();
        $this->publicationInvitees = new ArrayCollection();
        $this->performedNotifications = new ArrayCollection();
        $this->notifications = new ArrayCollection();
        $this->preferences = new ArrayCollection();
        $this->viewLogs = new ArrayCollection();
        $this->subscriptions = new ArrayCollection();
        $this->subscribers = new ArrayCollection();
        $this->storageFiles = new ArrayCollection();
        $this->boostedContentUnits = new ArrayCollection();
        $this->views = new ArrayCollection();
    }

    public function __toString()
    {
        return $this->publicKey;
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
    public function getPublicKey()
    {
        return $this->publicKey;
    }

    /**
     * @param mixed $publicKey
     */
    public function setPublicKey($publicKey)
    {
        $this->publicKey = $publicKey;
    }

    /**
     * @return mixed
     */
    public function getOldPublicKey()
    {
        return $this->oldPublicKey;
    }

    /**
     * @param mixed $oldPublicKey
     */
    public function setOldPublicKey($oldPublicKey)
    {
        $this->oldPublicKey = $oldPublicKey;
    }

    /**
     * @return mixed
     */
    public function getEmail()
    {
        return $this->email;
    }

    /**
     * @param mixed $email
     */
    public function setEmail($email)
    {
        $this->email = $email;
    }

    /**
     * @return mixed
     */
    public function getFirstName()
    {
        return $this->firstName;
    }

    /**
     * @param mixed $firstName
     */
    public function setFirstName($firstName)
    {
        $this->firstName = $firstName;
    }

    /**
     * @return mixed
     */
    public function getLastName()
    {
        return $this->lastName;
    }

    /**
     * @param mixed $lastName
     */
    public function setLastName($lastName)
    {
        $this->lastName = $lastName;
    }

    /**
     * @return mixed
     */
    public function getImage()
    {
        return $this->image;
    }

    /**
     * @param mixed $image
     */
    public function setImage($image)
    {
        $this->image = $image;
    }

    /**
     * @return mixed
     */
    public function getLanguage()
    {
        return $this->language;
    }

    /**
     * @param mixed $language
     */
    public function setLanguage($language)
    {
        $this->language = $language;
    }

    /**
     * @return mixed
     */
    public function getWhole()
    {
        return $this->whole;
    }

    /**
     * @param mixed $whole
     */
    public function setWhole($whole)
    {
        $this->whole = $whole;
    }

    /**
     * @return mixed
     */
    public function getFraction()
    {
        return $this->fraction;
    }

    /**
     * @param mixed $fraction
     */
    public function setFraction($fraction)
    {
        $this->fraction = $fraction;
    }

    /**
     * @return mixed
     */
    public function getSignedBlocks()
    {
        return $this->signedBlocks;
    }

    /**
     * @return mixed
     */
    public function getRewards()
    {
        return $this->rewards;
    }

    /**
     * @return mixed
     */
    public function getFiles()
    {
        return $this->files;
    }

    /**
     * @return mixed
     */
    public function getAuthorContentUnits()
    {
        return $this->authorContentUnits;
    }

    /**
     * @return mixed
     */
    public function getChannelContentUnits()
    {
        return $this->channelContentUnits;
    }

    /**
     * @return mixed
     */
    public function getContents()
    {
        return $this->contents;
    }

    /**
     * @return mixed
     */
    public function getFromContents()
    {
        return $this->fromTransfers;
    }

    /**
     * @return mixed
     */
    public function getToContents()
    {
        return $this->toTransfers;
    }

    /**
     * @return mixed
     */
    public function getDrafts()
    {
        return $this->drafts;
    }

    /**
     * @return mixed
     */
    public function isChannel()
    {
        return $this->channel;
    }

    /**
     * @param mixed $channel
     */
    public function setChannel($channel)
    {
        $this->channel = $channel;
    }

    /**
     * @return mixed
     */
    public function isStorage()
    {
        return $this->storage;
    }

    /**
     * @param mixed $storage
     */
    public function setStorage($storage)
    {
        $this->storage = $storage;
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
    public function isBlockchain()
    {
        return $this->blockchain;
    }

    /**
     * @param mixed $blockchain
     */
    public function setBlockchain($blockchain)
    {
        $this->blockchain = $blockchain;
    }

    /**
     * @return string
     */
    public function getApiKey()
    {
        return $this->apiKey;
    }

    /**
     * @throws \Exception
     */
    public function setApiKey()
    {
        $this->apiKey = hash('sha256', random_bytes(128));
    }

    public function getSalt()
    {
        return '';
    }

    public function eraseCredentials()
    {
    }

    public function getRoles()
    {
        return array('ROLE_USER');
    }

    public function getPassword()
    {
        return '';
    }

    public function getUsername()
    {
        return '';
    }

    /**
     * Get publications
     */
    public function getPublications()
    {
        return $this->publications;
    }

    /**
     * Get publicationInvitees
     */
    public function getPublicationInvitees()
    {
        return $this->publicationInvitees;
    }

    /**
     * Get performedNotifications
     */
    public function getPerformedNotifications()
    {
        return $this->performedNotifications;
    }

    /**
     * Get notifications
     */
    public function getNotifications()
    {
        return $this->notifications;
    }

    /**
     * Get preferences
     */
    public function getPreferences()
    {
        return $this->preferences;
    }

    /**
     * Get viewLogs
     */
    public function getViewLogs()
    {
        return $this->viewLogs;
    }

    /**
     * Get subscriptions
     */
    public function getSubscriptions()
    {
        return $this->subscriptions;
    }

    /**
     * Get subscribers
     */
    public function getSubscribers()
    {
        return $this->subscribers;
    }

    /**
     * @return mixed
     */
    public function getStorageFiles()
    {
        return $this->storageFiles;
    }

    public function addStorageFile(File $file)
    {
        $this->storageFiles[] = $file;
    }

    public function removeStorageFile(File $file)
    {
        $this->storageFiles->removeElement($file);
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
    public function getBoostedContentUnits()
    {
        return $this->boostedContentUnits;
    }

    /**
     * @return mixed
     */
    public function getViews()
    {
        return $this->views;
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
     * @return string
     */
    public function getBio()
    {
        return $this->bio;
    }

    /**
     * @param mixed $bio
     */
    public function setBio($bio)
    {
        $this->bio = $bio;
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
}