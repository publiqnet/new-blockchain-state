<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 6/23/2020
 * Time: 1:14 PM
 */

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\HasLifecycleCallbacks;

/**
 * @ORM\Table(name="account_exchange")
 * @ORM\Entity()
 * @HasLifecycleCallbacks
 */
class AccountExchange
{
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
     * @var boolean
     * @ORM\Column(type="boolean")
     */
    private $completed = false;

    /**
     * @var string
     * @ORM\Column(type="string", length=96, nullable=false, unique=true)
     */
    private $exchangeId;


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
     * @return bool
     */
    public function isCompleted()
    {
        return $this->completed;
    }

    /**
     * @param bool $completed
     */
    public function setCompleted(bool $completed)
    {
        $this->completed = $completed;
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
}