<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 3/22/19
 * Time: 12:14 PM
 */

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\HasLifecycleCallbacks;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ORM\Table(name="publication_member")
 * @ORM\Entity(repositoryClass="App\Repository\PublicationMemberRepository")
 * @HasLifecycleCallbacks
 */
class PublicationMember
{
    const TYPES = [
        'owner' => 1,
        'editor' => 2,
        'contributor' => 3,
        'invited_editor' => 102,
        'invited_contributor' => 103,
        'requested_contributor' => 203
    ];

    /**
     * @var int
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var Publication
     * @ORM\ManyToOne(targetEntity="App\Entity\Publication", inversedBy="members")
     * @ORM\JoinColumn(name="publication_id", referencedColumnName="id", nullable=false)
     */
    private $publication;

    /**
     * @var Account
     * @ORM\ManyToOne(targetEntity="App\Entity\Account", inversedBy="publicationInvitees")
     * @ORM\JoinColumn(name="inviter_id", referencedColumnName="id", nullable=true)
     */
    private $inviter;

    /**
     * @var Account
     * @ORM\ManyToOne(targetEntity="App\Entity\Account", inversedBy="publications")
     * @ORM\JoinColumn(name="user_id", referencedColumnName="id", nullable=true)
     * @Groups({"publicationMembers"})
     */
    private $member;

    /**
     * @var integer
     * @ORM\Column(name="status", type="integer")
     */
    private $status;


    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @return Publication
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
     * @return Account
     */
    public function getInviter()
    {
        return $this->inviter;
    }

    /**
     * @param Account $inviter
     */
    public function setInviter(Account $inviter)
    {
        $this->inviter = $inviter;
    }

    /**
     * @return Account
     */
    public function getMember()
    {
        return $this->member;
    }

    /**
     * @param Account $member
     */
    public function setMember(Account $member)
    {
        $this->member = $member;
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
}