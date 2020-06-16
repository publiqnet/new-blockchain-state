<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 6/16/20
 * Time: 12:52 PM
 */

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Class AccountCustomData
 * @package App\Entity
 *
 * @ORM\Table(name="account_custom_data")
 * @ORM\Entity
 */
class AccountCustomData
{
    /**
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var string
     * @ORM\Column(type="string", length=64, unique=true)
     */
    private $slug;

    /**
     * @var string
     * @ORM\Column(type="string", length=128, unique=true)
     */
    private $brainKey;

    /**
     * @var string
     * @ORM\Column(type="string", length=128, unique=true)
     */
    private $privateKey;

    /**
     * @var Account
     * @ORM\JoinColumn(nullable=true, referencedColumnName="id")
     * @ORM\OneToOne(targetEntity="App\Entity\Account", inversedBy="customData")
     */
    private $account;


    public function __toString()
    {
        return $this->slug;
    }

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getBrainKey()
    {
        return $this->brainKey;
    }

    /**
     * @param string $brainKey
     */
    public function setBrainKey(string $brainKey)
    {
        $this->brainKey = $brainKey;
    }

    /**
     * @return string
     */
    public function getPrivateKey()
    {
        return $this->privateKey;
    }

    /**
     * @param string $privateKey
     */
    public function setPrivateKey(string $privateKey)
    {
        $this->privateKey = $privateKey;
    }

    /**
     * @return Account
     */
    public function getAccount()
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
}