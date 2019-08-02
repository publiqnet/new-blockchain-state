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
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Class Account
 * @package App\Entity
 *
 * @ORM\Table(name="account")
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
     * @ORM\Column(name="address", type="string", length=128, nullable=true)
     * @Groups({"account", "accountBase"})
     */
    private $address;

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
     * @ORM\Column(name="list_view", type="boolean")
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
     * @ORM\OneToMany(targetEntity="App\Entity\Block", mappedBy="account")
     */
    private $signedBlocks;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Reward", mappedBy="to")
     */
    private $rewards;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\File", mappedBy="author")
     */
    private $files;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\ContentUnit", mappedBy="author")
     */
    private $authorContentUnits;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\ContentUnit", mappedBy="channel")
     */
    private $channelContentUnits;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Content", mappedBy="channel")
     */
    private $contents;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Transfer", mappedBy="from")
     */
    private $fromTransfers;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Transfer", mappedBy="to")
     */
    private $toTransfers;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Draft", mappedBy="account")
     */
    private $drafts;

    /**
     * @var string
     * @ORM\Column(type="string", length=64, unique=true, nullable=true)
     * @Groups({"account"})
     */
    private $apiKey;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\PublicationMember", mappedBy="member")
     */
    private $publications;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\PublicationMember", mappedBy="inviter")
     */
    private $publicationInvitees;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Notification", mappedBy="performer")
     */
    private $performedNotifications;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\UserNotification", mappedBy="account")
     */
    private $notifications;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Subscription", mappedBy="subscriber")
     */
    private $subscriptions;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Subscription", mappedBy="author", cascade="remove")
     */
    private $subscribers;

    /**
     * @var integer
     * @Groups({"accountMemberStatus"})
     */
    private $memberStatus;

    /**
     * @ORM\ManyToMany(targetEntity="App\Entity\File", inversedBy="storages")
     */
    private $storageFiles;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\BoostedContentUnit", mappedBy="sponsor")
     */
    private $boostedContentUnits;

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
        $this->subscriptions = new ArrayCollection();
        $this->subscribers = new ArrayCollection();
        $this->storageFiles = new ArrayCollection();
        $this->boostedContentUnits = new ArrayCollection();
    }

    public function __toString()
    {
        return $this->address;
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
    public function getAddress()
    {
        return $this->address;
    }

    /**
     * @param mixed $address
     */
    public function setAddress($address)
    {
        $this->address = $address;
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
     * @param string $bio
     */
    public function setBio(string $bio)
    {
        $this->bio = $bio;
    }
}