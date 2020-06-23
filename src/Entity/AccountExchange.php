<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 6/23/2020
 * Time: 1:14 PM
 */

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\HasLifecycleCallbacks;

/**
 * @ORM\Table(name="account_exchange")
 * @ORM\Entity()
 * @HasLifecycleCallbacks
 */
class AccountExchange
{
    const STATUSES = [
        'new' => 0,
        'completed' => 1,
        'failed' => 2,
        'expired' => 3,
    ];

    /**
     * @var int
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var Account
     * @ORM\ManyToOne(targetEntity="App\Entity\Account", inversedBy="exchanges")
     * @ORM\JoinColumn(name="account_id", referencedColumnName="id", nullable=false)
     */
    private $account;

    /**
     * @var string
     * @ORM\Column(type="string", length=96, nullable=false, unique=true)
     */
    private $exchangeId;

    /**
     * @var integer
     * @ORM\Column(type="integer", options={"default": 0})
     */
    private $status = 0;

    /**
     * @var float
     * @ORM\Column(type="float", nullable=true)
     */
    private $amount;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Notification", mappedBy="exchange", cascade="remove")
     */
    private $notifications;


    public function __construct()
    {
        $this->notifications = new ArrayCollection();
    }

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @return Account
     */
    public function getAccount(): Account
    {
        return $this->account;
    }

    /**
     * @param Account $account
     */
    public function setAccount(Account $account)
    {
        $this->account = $account;
    }

    /**
     * @return string
     */
    public function getExchangeId()
    {
        return $this->exchangeId;
    }

    /**
     * @param string $exchangeId
     */
    public function setExchangeId($exchangeId)
    {
        $this->exchangeId = $exchangeId;
    }

    /**
     * @return int
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * @param int $status
     */
    public function setStatus(int $status)
    {
        $this->status = $status;
    }

    /**
     * @return float|null
     */
    public function getAmount()
    {
        return $this->amount;
    }

    /**
     * @param float|null $amount
     */
    public function setAmount($amount)
    {
        $this->amount = $amount;
    }

    /**
     * Get notifications
     */
    public function getNotifications()
    {
        return $this->notifications;
    }
}