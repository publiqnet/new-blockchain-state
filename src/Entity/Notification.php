<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 4/9/19
 * Time: 5:14 PM
 */

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\HasLifecycleCallbacks;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ORM\Entity(repositoryClass="App\Repository\NotificationRepository")
 * @ORM\Table(name="notification")
 * @HasLifecycleCallbacks
 */
class Notification
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
     * @var Account
     * @ORM\ManyToOne(targetEntity="App\Entity\Account", inversedBy="performedNotifications")
     * @Groups({"notification"})
     */
    private $performer;

    /**
     * @var NotificationType
     * @ORM\ManyToOne(targetEntity="App\Entity\NotificationType", inversedBy="notifications")
     * @ORM\JoinColumn(name="type_id", referencedColumnName="id", nullable=false)
     * @Groups({"notification"})
     */
    private $type;

    /**
     * @var string
     * @ORM\Column(name="data", type="string", length=64, nullable=true)
     */
    private $data;

    /**
     * @var int
     * @ORM\Column(name="created_at", type="integer", nullable=false)
     * @Groups({"notification"})
     */
    private $created_at;

    /**
     * @var Publication
     * @ORM\ManyToOne(targetEntity="App\Entity\Publication", inversedBy="notifications")
     * @Groups({"notification"})
     */
    private $publication;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\UserNotification", mappedBy="notification", cascade="remove")
     */
    private $userNotifications;

    /**
     * @var ContentUnit
     * @ORM\ManyToOne(targetEntity="App\Entity\ContentUnit", inversedBy="notifications")
     * @Groups({"notification"})
     */
    private $contentUnit;

    public function __construct()
    {
        $this->userNotifications = new ArrayCollection();
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return Account | null
     */
    public function getPerformer()
    {
        return $this->performer;
    }

    /**
     * @param Account $performer
     */
    public function setPerformer(Account $performer)
    {
        $this->performer = $performer;
    }

    /**
     * @return int
     */
    public function getCreatedAt()
    {
        return $this->created_at;
    }

    /**
     * @ORM\PrePersist
     */
    public function onPrePersistUpdateCreatedAt()
    {
        $this->created_at = (new \DateTime())->getTimestamp();
    }

    /**
     * @return NotificationType
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param NotificationType $type
     */
    public function setNotificationType(NotificationType $type)
    {
        $this->type = $type;
    }

    /**
     * @return ArrayCollection
     */
    public function getUserNotifications()
    {
        return $this->userNotifications;
    }

    /**
     * @return string | null
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * @param string $data
     */
    public function setData(string $data)
    {
        $this->data = $data;
    }

    /**
     * @return Publication | null
     */
    public function getPublication()
    {
        return $this->publication;
    }

    /**
     * @param Publication $publication
     */
    public function setPublication(Publication $publication)
    {
        $this->publication = $publication;
    }

    /**
     * @return ContentUnit | null
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
}