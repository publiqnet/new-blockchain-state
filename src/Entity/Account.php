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
 * @ORM\Entity(repositoryClass="App\Repository\AccountRepository")
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
     * @ORM\Column(name="public_key", type="string", length=128, nullable=true)
     * @Groups({"account", "accountBase"})
     */
    private $publicKey;

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
        $this->storageFiles = new ArrayCollection();
        $this->boostedContentUnits = new ArrayCollection();
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