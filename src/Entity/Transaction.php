<?php
/**
 * Created by PhpStorm.
 * User: grigor
 * Date: 9/24/18
 * Time: 7:31 PM
 */

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\Index;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Class Transaction
 * @package App\Entity
 *
 * @ORM\Table(name="transaction",indexes={@Index(columns={"block_id"})})
 * @ORM\Entity(repositoryClass="App\Repository\TransactionRepository")
 */
class Transaction
{
    /**
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @ORM\Column(name="name", type="string", length=64, nullable=true)
     */
    private $name;

    /**
     * @ORM\Column(name="rtt", type="integer", nullable=true)
     * @Groups({"explorerTransaction", "explorerTransactionLight"})
     */
    private $rtt;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Block", inversedBy="transactions", fetch="EXTRA_LAZY")
     * @Groups({"explorerTransaction", "explorerTransactionLight"})
     */
    private $block;

    /**
     * @ORM\Column(name="fee_whole", type="integer")
     * @Groups({"explorerTransaction", "explorerTransactionLight"})
     */
    private $feeWhole;

    /**
     * @ORM\Column(name="fee_fraction", type="integer")
     * @Groups({"explorerTransaction", "explorerTransactionLight"})
     */
    private $feeFraction;

    /**
     * @ORM\Column(name="transaction_hash", type="string", length=64, nullable=true, unique=true)
     * @Groups({"explorerTransaction", "explorerTransactionLight"})
     */
    private $transactionHash;

    /**
     * @ORM\Column(name="transaction_size", type="integer")
     * @Groups({"explorerTransaction", "explorerTransactionLight"})
     */
    private $transactionSize;

    /**
     * @ORM\Column(name="time_signed", type="integer")
     * @Groups({"explorerTransaction", "explorerTransactionLight"})
     */
    private $timeSigned;

    /**
     * @ORM\JoinColumn(nullable=true, referencedColumnName="id")
     * @ORM\OneToOne(targetEntity="App\Entity\File", inversedBy="transaction", cascade={"remove"}, fetch="EXTRA_LAZY")
     * @Groups({"explorerTransaction"})
     */
    private $file;

    /**
     * @ORM\JoinColumn(nullable=true, referencedColumnName="id")
     * @ORM\OneToOne(targetEntity="App\Entity\ContentUnit", inversedBy="transaction", cascade={"remove"}, fetch="EXTRA_LAZY")
     * @Groups({"explorerTransaction"})
     */
    private $contentUnit;

    /**
     * @ORM\JoinColumn(nullable=true, referencedColumnName="id")
     * @ORM\OneToOne(targetEntity="App\Entity\BoostedContentUnit", inversedBy="transaction", cascade={"remove"}, fetch="EXTRA_LAZY")
     * @Groups({"explorerTransaction"})
     */
    private $boostedContentUnit;

    /**
     * @ORM\JoinColumn(nullable=true, referencedColumnName="id")
     * @ORM\OneToOne(targetEntity="App\Entity\CancelBoostedContentUnit", inversedBy="transaction", cascade={"remove"}, fetch="EXTRA_LAZY")
     * @Groups({"explorerTransaction"})
     */
    private $cancelBoostedContentUnit;

    /**
     * @ORM\JoinColumn(nullable=true, referencedColumnName="id")
     * @ORM\OneToOne(targetEntity="App\Entity\Content", inversedBy="transaction", cascade={"remove"}, fetch="EXTRA_LAZY")
     * @Groups({"explorerTransaction"})
     */
    private $content;

    /**
     * @ORM\JoinColumn(nullable=true, referencedColumnName="id")
     * @ORM\OneToOne(targetEntity="App\Entity\Transfer", inversedBy="transaction", cascade={"remove"}, fetch="EXTRA_LAZY")
     * @Groups({"explorerTransaction"})
     */
    private $transfer;

    /**
     * @ORM\JoinColumn(nullable=true, referencedColumnName="id")
     * @ORM\OneToOne(targetEntity="App\Entity\Role", inversedBy="transaction", cascade={"remove"}, fetch="EXTRA_LAZY")
     * @Groups({"explorerTransaction"})
     */
    private $role;

    /**
     * @ORM\JoinColumn(nullable=true, referencedColumnName="id")
     * @ORM\OneToOne(targetEntity="App\Entity\StorageUpdate", inversedBy="transaction", cascade={"remove"}, fetch="EXTRA_LAZY")
     * @Groups({"explorerTransaction"})
     */
    private $storageUpdate;

    /**
     * @ORM\JoinColumn(nullable=true, referencedColumnName="id")
     * @ORM\OneToOne(targetEntity="App\Entity\ServiceStatistics", inversedBy="transaction", cascade={"remove"}, fetch="EXTRA_LAZY")
     * @Groups({"explorerTransaction"})
     */
    private $serviceStatistic;


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
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param mixed $name
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * @return mixed
     */
    public function getRtt()
    {
        return $this->rtt;
    }

    /**
     * @param mixed $rtt
     */
    public function setRtt($rtt)
    {
        $this->rtt = $rtt;
    }

    /**
     * @return mixed
     */
    public function getBlock()
    {
        return $this->block;
    }

    /**
     * @param mixed $block
     */
    public function setBlock($block)
    {
        $this->block = $block;
    }

    /**
     * @return mixed
     */
    public function getFeeWhole()
    {
        return $this->feeWhole;
    }

    /**
     * @param mixed $feeWhole
     */
    public function setFeeWhole($feeWhole)
    {
        $this->feeWhole = $feeWhole;
    }

    /**
     * @return mixed
     */
    public function getFeeFraction()
    {
        return $this->feeFraction;
    }

    /**
     * @param mixed $feeFraction
     */
    public function setFeeFraction($feeFraction)
    {
        $this->feeFraction = $feeFraction;
    }

    /**
     * @return mixed
     */
    public function getTransactionHash()
    {
        return $this->transactionHash;
    }

    /**
     * @param mixed $transactionHash
     */
    public function setTransactionHash($transactionHash)
    {
        $this->transactionHash = $transactionHash;
    }

    /**
     * @return mixed
     */
    public function getTransactionSize()
    {
        return $this->transactionSize;
    }

    /**
     * @param mixed $transactionSize
     */
    public function setTransactionSize($transactionSize)
    {
        $this->transactionSize = $transactionSize;
    }

    /**
     * @return mixed
     */
    public function getTimeSigned()
    {
        return $this->timeSigned;
    }

    /**
     * @param mixed $timeSigned
     */
    public function setTimeSigned($timeSigned)
    {
        $this->timeSigned = $timeSigned;
    }

    /**
     * @return mixed
     */
    public function getFile()
    {
        return $this->file;
    }

    /**
     * @param mixed $file
     */
    public function setFile($file)
    {
        $this->file = $file;
    }

    /**
     * @return mixed
     */
    public function getContent()
    {
        return $this->content;
    }

    /**
     * @param mixed $content
     */
    public function setContent($content)
    {
        $this->content = $content;
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
     * @return mixed
     */
    public function getTransfer()
    {
        return $this->transfer;
    }

    /**
     * @param mixed $transfer
     */
    public function setTransfer($transfer)
    {
        $this->transfer = $transfer;
    }

    /**
     * @return mixed
     */
    public function getBoostedContentUnit()
    {
        return $this->boostedContentUnit;
    }

    /**
     * @param mixed $boostedContentUnit
     */
    public function setBoostedContentUnit($boostedContentUnit)
    {
        $this->boostedContentUnit = $boostedContentUnit;
    }

    /**
     * @return mixed
     */
    public function getCancelBoostedContentUnit()
    {
        return $this->cancelBoostedContentUnit;
    }

    /**
     * @param mixed $cancelBoostedContentUnit
     */
    public function setCancelBoostedContentUnit($cancelBoostedContentUnit)
    {
        $this->cancelBoostedContentUnit = $cancelBoostedContentUnit;
    }

    /**
     * @return mixed
     */
    public function getRole()
    {
        return $this->role;
    }

    /**
     * @param mixed $role
     */
    public function setRole($role)
    {
        $this->role = $role;
    }

    /**
     * @return mixed
     */
    public function getStorageUpdate()
    {
        return $this->storageUpdate;
    }

    /**
     * @param mixed $storageUpdate
     */
    public function setStorageUpdate($storageUpdate)
    {
        $this->storageUpdate = $storageUpdate;
    }

    /**
     * @return mixed
     */
    public function getServiceStatistic()
    {
        return $this->serviceStatistic;
    }

    /**
     * @param mixed $serviceStatistic
     */
    public function setServiceStatistic($serviceStatistic)
    {
        $this->serviceStatistic = $serviceStatistic;
    }
}