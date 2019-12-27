<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 4/9/19
 * Time: 5:39 PM
 */

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ORM\Table(name="user_notification")
 * @ORM\Entity(repositoryClass="App\Repository\UserNotificationRepository")
 */
class UserNotification
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @Groups({"userNotification"})
     */
    private $id;

    /**
     * @var Account
     * @ORM\ManyToOne(targetEntity="App\Entity\Account", inversedBy="notifications")
     * @ORM\JoinColumn(name="user_id", referencedColumnName="id", nullable=false)
     */
    private $account;

    /**
     * @var Notification
     * @ORM\ManyToOne(targetEntity="App\Entity\Notification", inversedBy="userNotifications")
     * @ORM\JoinColumn(name="notification_id", referencedColumnName="id", nullable=false)
     * @Groups({"userNotification"})
     */
    private $notification;

    /**
     * @var bool
     * @ORM\Column(name="is_read", type="boolean")
     * @Groups({"userNotification"})
     */
    private $isRead = false;

    /**
     * @var bool
     * @ORM\Column(name="is_seen", type="boolean")
     * @Groups({"userNotification"})
     */
    private $isSeen = false;

    /**
     * @var bool
     * @ORM\Column(name="is_special", type="boolean", options={"default": 0})
     * @Groups({"userNotification"})
     */
    private $isSpecial = false;

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
     * @return Notification
     */
    public function getNotification(): Notification
    {
        return $this->notification;
    }

    /**
     * @param Notification $notification
     */
    public function setNotification(Notification $notification)
    {
        $this->notification = $notification;
    }

    /**
     * @return bool
     */
    public function isRead(): bool
    {
        return $this->isRead;
    }

    /**
     * @param bool $isRead
     */
    public function setIsRead(bool $isRead)
    {
        $this->isRead = $isRead;
    }

    /**
     * @return bool
     */
    public function isSeen(): bool
    {
        return $this->isSeen;
    }

    /**
     * @param bool $isSeen
     */
    public function setIsSeen(bool $isSeen)
    {
        $this->isSeen = $isSeen;
    }

    /**
     * @return bool
     */
    public function isSpecial(): bool
    {
        return $this->isSpecial;
    }

    /**
     * @param bool $isSpecial
     */
    public function setIsSpecial(bool $isSpecial)
    {
        $this->isSpecial = $isSpecial;
    }
}