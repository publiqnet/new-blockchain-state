<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 4/9/19
 * Time: 5:10 PM
 */

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ORM\Entity
 * @ORM\Table(name="notification_type")
 */
class NotificationType
{
    const TYPES = [
        'new_article' => ['key' => 'new_article', 'en' => '{{performer}} has posted a new article', 'es' => '{{performer}} has posted a new article', 'jp' => '{{performer}}さんが新しい記事を投稿しました'],
        'share_article' => ['key' => 'share_article', 'en' => 'Make sure to share {{article}} on social media to get more views', 'es' => 'Make sure to share {{article}} on social media to get more views', 'jp' => 'Make sure to share {{article}} on social media to get more views'],
        'article_reported' => ['key' => 'article_reported', 'en' => '{{performer}} has reported your article', 'es' => '{{performer}} has reported your article', 'jp' => '{{performer}}さんがあなたの記事を報告しました'],
        'new_transfer' => ['key' => 'new_transfer', 'en' => '{{performer}} has sent you a new transfer', 'es' => '{{performer}} has sent you a new transfer', 'jp' => '{{performer}}新しい送金を送ってくれました'],
        'subscribe_user' => ['key' => 'subscribe_user', 'en' => '{{performer}} has followed you', 'es' => '{{performer}} has followed you', 'jp' => '{{performer}} has followed you'],
        'unsubscribe_user' => ['key' => 'unsubscribe_user', 'en' => '{{performer}} has unfollowed you', 'es' => '{{performer}} has unfollowed you', 'jp' => '{{performer}} has unfollowed you'],
        'publication_new_article' => ['key' => 'publication_new_article', 'en' => '{{performer}} has posted a new article in {{target}}', 'es' => '{{performer}} has posted a new article in {{target}}', 'jp' => '{{performer}}さんが新しい記事を投稿しました{{target}}'],
        'publication_invitation_new' => ['key' => 'publication_invitation_new', 'en' => '{{performer}} has invited you to join {{target}}', 'es' => '{{performer}} has invited you to join {{target}}', 'jp' => '{{performer}}に参加するように招待されています{{target}}'],
        'publication_invitation_cancelled' => ['key' => 'publication_invitation_cancelled', 'en' => '{{performer}} has cancelled invitation to join {{target}}', 'es' => '{{performer}} has cancelled invitation to join {{target}}', 'jp' => '{{performer}}さんが参加の招待状をキャンセルしました{{target}}'],
        'publication_invitation_accepted' => ['key' => 'publication_invitation_accepted', 'en' => '{{performer}} has accepted invitation to join {{target}}', 'es' => '{{performer}} has accepted invitation to join {{target}}', 'jp' => '{{performer}}は参加の招待を受け入れました{{target}}'],
        'publication_invitation_rejected' => ['key' => 'publication_invitation_rejected', 'en' => '{{performer}} has rejected invitation to join {{target}}', 'es' => '{{performer}} has rejected invitation to join {{target}}', 'jp' => '{{performer}}参加を拒否されました{{target}}'],
        'publication_request_new' => ['key' => 'publication_request_new', 'en' => '{{performer}} has requested to join {{target}}', 'es' => '{{performer}} has requested to join {{target}}', 'jp' => '{{performer}}は参加を要求しました{{target}}'],
        'publication_request_cancelled' => ['key' => 'publication_request_cancelled', 'en' => '{{performer}} has cancelled request to join {{target}}', 'es' => '{{performer}} has cancelled request to join {{target}}', 'jp' => '{{performer}}参加をキャンセルするリクエストがキャンセルされました{{target}}'],
        'publication_request_accepted' => ['key' => 'publication_request_accepted', 'en' => '{{performer}} has accepted your request to join {{target}}', 'es' => '{{performer}} has accepted your request to join {{target}}', 'jp' => '{{performer}}あなたの参加リクエストを受け入れました{{target}}'],
        'publication_request_rejected' => ['key' => 'publication_request_rejected', 'en' => '{{performer}} has rejected your request to join {{target}}', 'es' => '{{performer}} has rejected your request to join {{target}}', 'jp' => '{{performer}}あなたの参加要請を拒否しました{{target}}'],
        'publication_membership_cancelled' => ['key' => 'publication_membership_cancelled', 'en' => 'You are no longer member of {{target}}', 'es' => 'You are no longer member of {{target}}', 'jp' => '{{target}}あなたはもはや%sのメンバーではありません'],
        'publication_membership_cancelled_by_user' => ['key' => 'publication_membership_cancelled_by_user', 'en' => '{{performer}} has left {{target}}', 'es' => '{{performer}} has left {{target}}', 'jp' => '{{performer}}は{{target}}を残しました'],
        'article_boosted_by_other' => ['key' => 'article_boosted_by_other', 'en' => '{{performer}} has boosted {{article}}', 'es' => '{{performer}} has boosted {{article}}', 'jp' => '{{performer}} has boosted {{article}}'],
        'ataix_exchange_completed' => ['key' => 'ataix_exchange_completed', 'en' => 'You have received {{amount}} PBQ', 'es' => 'You have received {{amount}} PBQ', 'jp' => 'You have received {{amount}} PBQ'],
    ];

    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var string
     * @ORM\Column(name="keyword", type="string", length=48, nullable=false, unique=true)
     * @Groups({"notificationType"})
     */
    private $keyword;

    /**
     * @var string
     * @ORM\Column(name="body_en", type="string", length=128, nullable=false)
     * @Groups({"notificationType"})
     */
    private $bodyEn;

    /**
     * @var string
     * @ORM\Column(name="body_jp", type="string", length=128, nullable=false)
     * @Groups({"notificationType"})
     */
    private $bodyJp;

    /**
     * @var string
     * @ORM\Column(name="body_es", type="string", length=128, nullable=false)
     * @Groups({"notificationType"})
     */
    private $bodyEs;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Notification", mappedBy="type")
     */
    private $notifications;

    public function __construct()
    {
        $this->notifications = new ArrayCollection();
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getKeyword()
    {
        return $this->keyword;
    }

    /**
     * @param string $keyword
     */
    public function setKeyword(string $keyword)
    {
        $this->keyword = $keyword;
    }

    /**
     * @return string
     */
    public function getBodyEn()
    {
        return $this->bodyEn;
    }

    /**
     * @param string $bodyEn
     */
    public function setBodyEn(string $bodyEn)
    {
        $this->bodyEn = $bodyEn;
    }

    /**
     * @return string
     */
    public function getBodyEs()
    {
        return $this->bodyEs;
    }

    /**
     * @param string $bodyEs
     */
    public function setBodyEs(string $bodyEs)
    {
        $this->bodyEs = $bodyEs;
    }

    /**
     * @return string
     */
    public function getBodyJp()
    {
        return $this->bodyJp;
    }

    /**
     * @param string $bodyJp
     */
    public function setBodyJp(string $bodyJp)
    {
        $this->bodyJp = $bodyJp;
    }

    /**
     * @return mixed
     */
    public function getNotifications()
    {
        return $this->notifications;
    }
}