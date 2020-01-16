<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 2/28/19
 * Time: 2:50 PM
 */

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\Index;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Class ContentUnit
 * @package App\Entity
 *
 * @ORM\Table(name="content_unit", indexes={@Index(columns={"title", "text_with_data"}, flags={"fulltext"})})
 * @ORM\Entity(repositoryClass="App\Repository\ContentUnitRepository")
 */
class ContentUnit
{
    /**
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @ORM\Column(name="uri", type="string", length=64, unique=true)
     * @Groups({"contentUnit", "contentUnitFull", "contentUnitList", "contentUnitSeo", "contentUnitNotification"})
     */
    private $uri;

    /**
     * @ORM\Column(name="blockchain_content_id", type="string", length=64)
     * @Groups({"contentUnitContentId"})
     */
    private $contentId;

    /**
     * @ORM\Column(name="title", type="string", length=256, nullable=false)
     * @Groups({"contentUnit", "contentUnitFull", "contentUnitList", "contentUnitSeo", "contentUnitNotification"})
     */
    private $title;

    /**
     * @ORM\Column(name="text", type="text", nullable=true)
     * @Groups({"contentUnit", "contentUnitFull"})
     */
    private $text;

    /**
     * @ORM\Column(name="text_with_data", type="text", nullable=true)
     */
    private $textWithData;

    /**
     * @ORM\Column(name="text_with_data_checked", type="boolean")
     */
    private $textWithDataChecked = 0;

    /**
     * @ORM\Column(name="views", type="integer", nullable=true)
     * @Groups({"contentUnit", "contentUnitFull", "contentUnitList"})
     */
    private $views = 0;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\File", inversedBy="covers")
     * @ORM\JoinColumn(name="cover_id", referencedColumnName="id", onDelete="SET NULL")
     * @Groups({"contentUnit", "contentUnitFull", "contentUnitList"})
     */
    private $cover;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Account", inversedBy="authorContentUnits")
     * @Groups({"contentUnitFull", "contentUnitList"})
     */
    private $author;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Account", inversedBy="channelContentUnits")
     */
    private $channel;

    /**
     * @ORM\ManyToMany(targetEntity="App\Entity\File", inversedBy="contentUnits", fetch="EXTRA_LAZY")
     * @Groups({"contentUnit", "contentUnitFull"})
     */
    private $files;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Content", inversedBy="contentUnits")
     * @ORM\JoinColumn(name="content_id", referencedColumnName="id", onDelete="SET NULL")
     */
    private $content;

    /**
     * @ORM\OneToOne(targetEntity="App\Entity\Transaction", mappedBy="contentUnit")
     */
    private $transaction;

    /**
     * @var integer
     * @Groups({"contentUnit", "contentUnitFull", "contentUnitList"})
     */
    private $published;

    /**
     * @var boolean
     * @Groups({"contentUnit", "contentUnitFull", "contentUnitList"})
     */
    private $boosted;

    /**
     * @var string
     * @Groups({"contentUnit", "contentUnitFull", "contentUnitList"})
     */
    private $status;

    /**
     * @var mixed
     * @Groups({"previousVersions"})
     */
    private $previousVersions;

    /**
     * @var mixed
     * @Groups({"nextVersions"})
     */
    private $nextVersions;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Publication", inversedBy="contentUnits")
     * @ORM\JoinColumn(name="publication_id", referencedColumnName="id", onDelete="SET NULL")
     * @Groups({"contentUnit", "contentUnitFull", "contentUnitList"})
     */
    private $publication;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\BoostedContentUnit", mappedBy="contentUnit", cascade={"remove"}, fetch="EXTRA_LAZY")
     * @Groups({"boost"})
     */
    private $boosts;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\ContentUnitViews", mappedBy="contentUnit", cascade={"remove"})
     */
    private $viewsPerChannel;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\ContentUnitTag", mappedBy="contentUnit", fetch="EXTRA_LAZY")
     * @Groups({"contentUnit", "contentUnitFull", "contentUnitList"})
     */
    private $tags;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\UserViewLog", mappedBy="contentUnit", cascade={"remove"}, fetch="EXTRA_LAZY")
     */
    private $viewLogs;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\UserViewLogHistory", mappedBy="contentUnit", cascade={"remove"}, fetch="EXTRA_LAZY")
     */
    private $viewLogsHistory;

    /**
     * @ORM\Column(name="social_image", type="string", nullable=true)
     * @Assert\File()
     * @Groups({"contentUnitSeo"})
     */
    private $socialImage;

    /**
     * @ORM\Column(name="update_social_image", type="boolean")
     */
    private $updateSocialImage = 1;

    /**
     * @var string
     * @Groups({"contentUnitSeo"})
     */
    private $description;

    /**
     * @var mixed
     * @Groups({"boost"})
     */
    private $boostSummary;

    /**
     * @ORM\Column(name="highlight", type="boolean", options={"default":0})
     */
    private $highlight = 0;

    /**
     * @ORM\Column(name="highlight_background", type="string", nullable=true)
     * @Groups({"highlight"})
     */
    private $highlightBackground;

    /**
     * @ORM\Column(name="highlight_font", type="string", nullable=true)
     * @Groups({"highlight"})
     */
    private $highlightFont;

    /**
     * @ORM\Column(name="highlight_tag_class", type="string", nullable=true)
     * @Groups({"highlight"})
     */
    private $highlightTagClass;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Notification", mappedBy="contentUnit", cascade="remove")
     */
    private $notifications;


    public function __construct()
    {
        $this->files = new ArrayCollection();
        $this->boosts = new ArrayCollection();
        $this->viewsPerChannel = new ArrayCollection();
        $this->tags = new ArrayCollection();
        $this->viewLogs = new ArrayCollection();
        $this->viewLogsHistory = new ArrayCollection();
        $this->notifications = new ArrayCollection();
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
    public function getUri()
    {
        return $this->uri;
    }

    /**
     * @param mixed $uri
     */
    public function setUri($uri)
    {
        $this->uri = $uri;
    }

    /**
     * @return mixed
     */
    public function getContentId()
    {
        return $this->contentId;
    }

    /**
     * @param mixed $contentId
     */
    public function setContentId($contentId)
    {
        $this->contentId = $contentId;
    }

    /**
     * @return mixed
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * @param mixed $title
     */
    public function setTitle($title)
    {
        $this->title = $title;
    }

    /**
     * @return mixed
     */
    public function getText()
    {
        return $this->text;
    }

    /**
     * @param mixed $text
     */
    public function setText($text)
    {
        $this->text = $text;
    }

    /**
     * @return mixed
     */
    public function getTextWithData()
    {
        return $this->textWithData;
    }

    /**
     * @param mixed $textWithData
     */
    public function setTextWithData($textWithData)
    {
        $this->textWithData = $textWithData;
    }

    /**
     * @return mixed
     */
    public function isTextWithDataChecked()
    {
        return $this->textWithDataChecked;
    }

    /**
     * @param mixed $textWithDataChecked
     */
    public function setTextWithDataChecked($textWithDataChecked)
    {
        $this->textWithDataChecked = $textWithDataChecked;
    }

    /**
     * @return mixed
     */
    public function getViews()
    {
        return $this->views;
    }

    /**
     * @param mixed $views
     */
    public function setViews($views)
    {
        $this->views = $views;
    }

    /**
     * @param integer $views
     */
    public function plusViews($views)
    {
        $this->views += $views;
    }

    /**
     * @param integer $views
     */
    public function minusViews($views)
    {
        $this->views -= $views;
    }

    /**
     * @return mixed
     */
    public function getCover()
    {
        return $this->cover;
    }

    /**
     * @param mixed $cover
     */
    public function setCover($cover)
    {
        $this->cover = $cover;
    }

    /**
     * @return mixed
     */
    public function getAuthor()
    {
        return $this->author;
    }

    /**
     * @param mixed $author
     */
    public function setAuthor($author)
    {
        $this->author = $author;
    }

    /**
     * @return mixed
     */
    public function getChannel()
    {
        return $this->channel;
    }

    /**
     * @param mixed $channel
     */
    public function setChannel($channel)
    {
        $this->channel = $channel;
    }

    /**
     * @return mixed
     */
    public function getFiles()
    {
        return $this->files;
    }

    public function addFile(File $file)
    {
        $this->files[] = $file;
    }

    /**
     * @return mixed
     */
    public function getContent()
    {
        return $this->content;
    }

    /**
     * @param mixed $content
     */
    public function setContent($content)
    {
        $this->content = $content;
    }

    /**
     * @return mixed
     */
    public function getTransaction()
    {
        return $this->transaction;
    }

    /**
     * @param mixed $transaction
     */
    public function setTransaction($transaction)
    {
        $this->transaction = $transaction;
    }

    /**
     * @return int
     */
    public function getPublished()
    {
        return $this->published;
    }

    /**
     * @param int $published
     */
    public function setPublished(int $published)
    {
        $this->published = $published;
    }

    /**
     * @return mixed
     */
    public function getPublication()
    {
        return $this->publication;
    }

    /**
     * @param mixed $publication
     */
    public function setPublication($publication)
    {
        $this->publication = $publication;
    }

    /**
     * @return mixed
     */
    public function getBoosts()
    {
        return $this->boosts;
    }

    /**
     * @return mixed
     */
    public function getViewsPerChannel()
    {
        return $this->viewsPerChannel;
    }

    /**
     * @return bool
     */
    public function isBoosted()
    {
        return $this->boosted;
    }

    /**
     * @param bool $boosted
     */
    public function setBoosted(bool $boosted)
    {
        $this->boosted = $boosted;
    }

    /**
     * @return string
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * @param string $status
     */
    public function setStatus(string $status)
    {
        $this->status = $status;
    }

    /**
     * @return mixed
     */
    public function getPreviousVersions()
    {
        return $this->previousVersions;
    }

    /**
     * @param mixed $previousVersions
     */
    public function setPreviousVersions($previousVersions)
    {
        $this->previousVersions = $previousVersions;
    }

    /**
     * @return mixed
     */
    public function getNextVersions()
    {
        return $this->nextVersions;
    }

    /**
     * @param mixed $nextVersions
     */
    public function setNextVersions($nextVersions)
    {
        $this->nextVersions = $nextVersions;
    }

    /**
     * @return mixed
     */
    public function getTags()
    {
        return $this->tags;
    }

    /**
     * @return mixed
     */
    public function getViewLogs()
    {
        return $this->viewLogs;
    }

    /**
     * @return mixed
     */
    public function getViewLogsHistory()
    {
        return $this->viewLogsHistory;
    }

    /**
     * @return mixed
     */
    public function getSocialImage()
    {
        return $this->socialImage;
    }

    /**
     * @param mixed $socialImage
     */
    public function setSocialImage($socialImage)
    {
        $this->socialImage = $socialImage;
    }

    /**
     * @return mixed
     */
    public function isUpdateSocialImage()
    {
        return $this->updateSocialImage;
    }

    /**
     * @param mixed $updateSocialImage
     */
    public function setUpdateSocialImage($updateSocialImage)
    {
        $this->updateSocialImage = $updateSocialImage;
    }

    /**
     * @return string
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * @param string $description
     */
    public function setDescription(string $description)
    {
        $this->description = $description;
    }

    /**
     * @return mixed
     */
    public function getBoostSummary()
    {
        return $this->boostSummary;
    }

    /**
     * @param mixed $boostSummary
     */
    public function setBoostSummary($boostSummary)
    {
        $this->boostSummary = $boostSummary;
    }

    /**
     * @return mixed
     */
    public function getHighlightBackground()
    {
        return $this->highlightBackground;
    }

    /**
     * @param mixed $highlightBackground
     */
    public function setHighlightBackground($highlightBackground)
    {
        $this->highlightBackground = $highlightBackground;
    }

    /**
     * @return mixed
     */
    public function getHighlightFont()
    {
        return $this->highlightFont;
    }

    /**
     * @param mixed $highlightFont
     */
    public function setHighlightFont($highlightFont)
    {
        $this->highlightFont = $highlightFont;
    }

    /**
     * @return mixed
     */
    public function isHighlight()
    {
        return $this->highlight;
    }

    /**
     * @param mixed $highlight
     */
    public function setHighlight($highlight)
    {
        $this->highlight = $highlight;
    }

    /**
     * @return mixed
     */
    public function getHighlightTagClass()
    {
        return $this->highlightTagClass;
    }

    /**
     * @param mixed $highlightTagClass
     */
    public function setHighlightTagClass($highlightTagClass)
    {
        $this->highlightTagClass = $highlightTagClass;
    }

    /**
     * Get notifications
     */
    public function getNotifications()
    {
        return $this->notifications;
    }
}