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
     * @ORM\Column(name="public_key", type="string", length=128, nullable=false, unique=true)
     * @Groups({"explorerAccountLight", "explorerAccount", "trackerAccountLight", "networkAccountLight", "networkAccountReward"})
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
     * @Groups({"networkAccountLight"})
     */
    private $url;

    /**
     * @ORM\Column(name="blockchain", type="boolean")
     */
    private $blockchain = 0;

    /**
     * @ORM\Column(name="miner", type="boolean", options={"default": 0})
     */
    private $miner = 0;

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

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\ContentUnitViews", mappedBy="channel", fetch="EXTRA_LAZY")
     */
    private $views;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\ServiceStatistics", mappedBy="account", fetch="EXTRA_LAZY")
     */
    private $serviceStatistics;

    /**
     * @var int
     * @Groups({"networkAccountChannel"})
     */
    private $publishedContentsCount;

    /**
     * @var int
     * @Groups({"networkAccountChannel", "networkAccountStorage"})
     */
    private $distributedContentsCount;

    /**
     * @var int
     * @Groups({"networkAccountChannel"})
     */
    private $contributorsCount;

    /**
     * @var string
     * @Groups({"networkAccountReward"})
     */
    private $rewardType;

    /**
     * @var string
     * @Groups({"networkAccountReward"})
     */
    private $totalWhole;

    /**
     * @var int
     * @Groups({"networkAccountReward"})
     */
    private $totalFraction;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\ServiceStatisticsDetail", mappedBy="storage", fetch="EXTRA_LAZY")
     */
    private $servedDetails;

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
        $this->views = new ArrayCollection();
        $this->serviceStatistics = new ArrayCollection();
        $this->servedDetails = new ArrayCollection();
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
    public function getViews()
    {
        return $this->views;
    }

    /**
     * @return mixed
     */
    public function getServiceStatistics()
    {
        return $this->serviceStatistics;
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
    public function isMiner()
    {
        return $this->miner;
    }

    /**
     * @param mixed $miner
     */
    public function setMiner($miner)
    {
        $this->miner = $miner;
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

    /**
     * @return int
     */
    public function getPublishedContentsCount()
    {
        return $this->publishedContentsCount;
    }

    /**
     * @param int $publishedContentsCount
     */
    public function setPublishedContentsCount(int $publishedContentsCount)
    {
        $this->publishedContentsCount = $publishedContentsCount;
    }

    /**
     * @return int
     */
    public function getDistributedContentsCount()
    {
        return $this->distributedContentsCount;
    }

    /**
     * @param int $distributedContentsCount
     */
    public function setDistributedContentsCount(int $distributedContentsCount)
    {
        $this->distributedContentsCount = $distributedContentsCount;
    }

    /**
     * @return int
     */
    public function getContributorsCount()
    {
        return $this->contributorsCount;
    }

    /**
     * @param int $contributorsCount
     */
    public function setContributorsCount(int $contributorsCount)
    {
        $this->contributorsCount = $contributorsCount;
    }

    /**
     * @return string
     */
    public function getRewardType()
    {
        return $this->rewardType;
    }

    /**
     * @param string $rewardType
     */
    public function setRewardType(string $rewardType)
    {
        $this->rewardType = $rewardType;
    }

    /**
     * @return int
     */
    public function getTotalWhole()
    {
        return $this->totalWhole;
    }

    /**
     * @param int $totalWhole
     */
    public function setTotalWhole(int $totalWhole)
    {
        $this->totalWhole = $totalWhole;
    }

    /**
     * @return int
     */
    public function getTotalFraction()
    {
        return $this->totalFraction;
    }

    /**
     * @param int $totalFraction
     */
    public function setTotalFraction(int $totalFraction)
    {
        $this->totalFraction = $totalFraction;
    }

    /**
     * @return mixed
     */
    public function getServedDetails()
    {
        return $this->servedDetails;
    }
}