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
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Class Account
 * @package App\Entity
 *
 * @ORM\Table(name="account")
 * @ORM\Entity()
 */
class Account
{
    /**
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var string
     * @ORM\Column(name="public_key", type="string", length=128, nullable=false, unique=true)
     * @Groups({"explorerAccountLight", "explorerAccount", "trackerAccountLight"})
     */
    private $publicKey;

    /**
     * @ORM\Column(name="whole", type="integer")
     * @Groups({"explorerAccount"})
     */
    private $whole;

    /**
     * @ORM\Column(name="fraction", type="integer")
     * @Groups({"explorerAccount"})
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
     * @var string
     * @ORM\Column(type="string", length=64, nullable=true)
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
     * @ORM\ManyToMany(targetEntity="App\Entity\File", inversedBy="storages", fetch="EXTRA_LAZY")
     */
    private $storageFiles;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\BoostedContentUnit", mappedBy="sponsor", fetch="EXTRA_LAZY")
     */
    private $boostedContentUnits;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Role", mappedBy="account", fetch="EXTRA_LAZY")
     */
    private $roles;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\StorageUpdate", mappedBy="account", fetch="EXTRA_LAZY")
     */
    private $storageUpdates;

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
        $this->storageFiles = new ArrayCollection();
        $this->boostedContentUnits = new ArrayCollection();
        $this->roles = new ArrayCollection();
        $this->storageUpdates = new ArrayCollection();
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
    public function getRoles()
    {
        return $this->roles;
    }

    /**
     * @return mixed
     */
    public function getStorageUpdates()
    {
        return $this->storageUpdates;
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
    public function getFromTransfers()
    {
        return $this->fromTransfers;
    }

    /**
     * @return mixed
     */
    public function getToTransfers()
    {
        return $this->toTransfers;
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
     * @return mixed
     */
    public function getBoostedContentUnits()
    {
        return $this->boostedContentUnits;
    }
}