<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 10/1/19
 * Time: 11:15 AM
 */

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Table(name="user_view_log")
 * @ORM\Entity()
 */
class UserViewLog
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var ContentUnit
     * @ORM\ManyToOne(targetEntity="App\Entity\ContentUnit", inversedBy="viewLogs")
     * @ORM\JoinColumn(name="content_unit_id", referencedColumnName="id", nullable=false)
     */
    private $contentUnit;

    /**
     * @var string
     * @ORM\Column(name="user_identifier", type="string", length=32, nullable=false)
     */
    private $userIdentifier;

    /**
     * @var int
     * @ORM\Column(name="datetime", type="integer")
     */
    private $datetime;

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @return mixed
     */
    public function getContentUnit()
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
     * @return mixed
     */
    public function getUserIdentifier()
    {
        return $this->userIdentifier;
    }

    /**
     * @param string $userIdentifier
     */
    public function setUserIdentifier(string $userIdentifier)
    {
        $this->userIdentifier = $userIdentifier;
    }

    /**
     * @return int
     */
    public function getDatetime(): int
    {
        return $this->datetime;
    }

    /**
     * @param int $datetime
     */
    public function setDatetime(int $datetime)
    {
        $this->datetime = $datetime;
    }
}