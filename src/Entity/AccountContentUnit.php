<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 5/27/20
 * Time: 1:55 PM
 */

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\HasLifecycleCallbacks;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ORM\Table(name="account_content_unit")
 * @ORM\Entity()
 * @HasLifecycleCallbacks
 */
class AccountContentUnit
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
     * @ORM\ManyToOne(targetEntity="App\Entity\Account", inversedBy="authorContentUnits")
     * @ORM\JoinColumn(name="account_id", referencedColumnName="id", nullable=false)
     * @Groups({"contentUnitFull", "contentUnitList", "contentUnitReference"})
     */
    private $account;

    /**
     * @var ContentUnit
     * @ORM\ManyToOne(targetEntity="App\Entity\ContentUnit", inversedBy="authors")
     * @ORM\JoinColumn(name="content_unit_id", referencedColumnName="id", nullable=true)
     */
    private $contentUnit;

    /**
     * @var boolean
     * @ORM\Column(type="boolean")
     * @Groups({"contentUnitList", "contentUnitFull"})
     */
    private $signed = 0;

    /**
     * @var int
     * @ORM\Column(type="integer", nullable=true, options={"default": 0})
     * @Groups({"contentUnitList", "contentUnitFull"})
     */
    private $signTime = 0;

    /**
     * @var string
     * @ORM\Column(type="string", length=256, nullable=true)
     */
    private $signature;


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
     * @return ContentUnit
     */
    public function getContentUnit(): ContentUnit
    {
        return $this->contentUnit;
    }

    /**
     * @param ContentUnit $contentUnit
     */
    public function setContentUnit(ContentUnit $contentUnit)
    {
        $this->contentUnit = $contentUnit;
    }

    /**
     * @return bool
     */
    public function isSigned(): bool
    {
        return $this->signed;
    }

    /**
     * @param bool $signed
     */
    public function setSigned(bool $signed)
    {
        $this->signed = $signed;

        if ($this->signTime = 0 && $signed) {
            $this->signTime = time();
        }
    }

    /**
     * @return int
     */
    public function getSignTime(): int
    {
        return $this->signTime;
    }

    /**
     * @param int $signTime
     */
    public function setSignTime(int $signTime)
    {
        $this->signTime = $signTime;
    }

    /**
     * @return string|null
     */
    public function getSignature()
    {
        return $this->signature;
    }

    /**
     * @param string|null $signature
     */
    public function setSignature($signature)
    {
        $this->signature = $signature;
    }
}