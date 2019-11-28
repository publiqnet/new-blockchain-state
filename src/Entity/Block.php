<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 1/30/19
 * Time: 11:59 AM
 */

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\Index;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Class Block
 * @package App\Entity
 *
 * @ORM\Table(name="block",indexes={@Index(columns={"account_id"})})
 * @ORM\Entity(repositoryClass="App\Repository\BlockRepository")
 */
class Block
{
    /**
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Account", inversedBy="signedBlocks")
     * @Groups({"block"})
     */
    private $account;

    /**
     * @ORM\Column(name="hash", type="string", length=64, unique=true)
     * @Groups({"block"})
     */
    private $hash;

    /**
     * @ORM\Column(name="number", type="string", length=16, unique=true)
     * @Groups({"block"})
     */
    private $number;

    /**
     * @ORM\Column(name="sign_time", type="integer")
     * @Groups({"block"})
     */
    private $signTime;

    /**
     * @ORM\Column(name="size", type="integer")
     * @Groups({"block"})
     */
    private $size;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Reward", mappedBy="block", cascade={"remove"}, fetch="EXTRA_LAZY")
     * @Groups({"block"})
     */
    private $rewards;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Transaction", mappedBy="block", cascade={"remove"}, fetch="EXTRA_LAZY")
     * @Groups({"block"})
     */
    private $transactions;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\ContentUnitViews", mappedBy="block", cascade={"remove"}, fetch="EXTRA_LAZY")
     */
    private $views;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\BoostedContentUnitSpending", mappedBy="block", cascade={"remove"}, fetch="EXTRA_LAZY")
     */
    private $spendings;

    /**
     * @Groups({"block"})
     */
    private $transactionsCount;

    /**
     * @Groups({"block", "transaction"})
     */
    private $confirmationsCount;

    /**
     * @Groups({"block"})
     */
    private $previousBlockHash;

    /**
     * @ORM\Column(name="fee_whole", type="integer", nullable=true)
     */
    private $feeWhole;

    /**
     * @ORM\Column(name="fee_fraction", type="integer", nullable=true)
     */
    private $feeFraction;

    public function __construct()
    {
        $this->rewards = new ArrayCollection();
        $this->transactions = new ArrayCollection();
        $this->views = new ArrayCollection();
        $this->spendings = new ArrayCollection();
    }

    public function __toString()
    {
        return $this->hash;
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
    public function getAccount()
    {
        return $this->account;
    }

    /**
     * @param mixed $account
     */
    public function setAccount($account)
    {
        $this->account = $account;
    }

    /**
     * @return mixed
     */
    public function getHash()
    {
        return $this->hash;
    }

    /**
     * @param mixed $hash
     */
    public function setHash($hash)
    {
        $this->hash = $hash;
    }

    /**
     * @return mixed
     */
    public function getNumber()
    {
        return $this->number;
    }

    /**
     * @param mixed $number
     */
    public function setNumber($number)
    {
        $this->number = $number;
    }

    /**
     * @return mixed
     */
    public function getSignTime()
    {
        return $this->signTime;
    }

    /**
     * @param mixed $signTime
     */
    public function setSignTime($signTime)
    {
        $this->signTime = $signTime;
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
    public function getTransactions()
    {
        return $this->transactions;
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
    public function getSpendings()
    {
        return $this->spendings;
    }

    /**
     * @param mixed $transactionsCount
     */
    public function setTransactionsCount($transactionsCount)
    {
        $this->transactionsCount = $transactionsCount;
    }

    /**
     * @return mixed
     */
    public function getTransactionsCount()
    {
        return $this->transactionsCount;
    }

    /**
     * @param mixed $confirmationsCount
     */
    public function setConfirmationsCount($confirmationsCount)
    {
        $this->confirmationsCount = $confirmationsCount;
    }

    /**
     * @return mixed
     */
    public function getConfirmationsCount()
    {
        return $this->confirmationsCount;
    }

    /**
     * @param mixed $previousBlockHash
     */
    public function setPreviousBlockHash($previousBlockHash)
    {
        $this->previousBlockHash = $previousBlockHash;
    }

    /**
     * @return mixed
     */
    public function getPreviousBlockHash()
    {
        return $this->previousBlockHash;
    }

    /**
     * @return mixed
     */
    public function getSize()
    {
        return $this->size;
    }

    /**
     * @param mixed $size
     */
    public function setSize($size)
    {
        $this->size = $size;
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
        $this->feeWhole = intval($feeWhole);
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
        $this->feeFraction = intval($feeFraction);
    }
}