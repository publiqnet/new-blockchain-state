<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 1/9/20
 * Time: 4:09 PM
 */

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * NetworkPage
 *
 * @ORM\Table(name="network_page")
 * @ORM\Entity
 * @ORM\HasLifecycleCallbacks()
 */
class NetworkPage
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
     * @var string
     * @ORM\Column(type="string")
     * @Groups({"networkPage"})
     * @Assert\NotBlank()
     */
    private $title;

    /**
     * @var string
     * @ORM\Column(type="text", nullable=true)
     * @Groups({"networkPage"})
     */
    private $content;

    /**
     * @var \DateTime $created
     * @Gedmo\Timestampable(on="create")
     * @ORM\Column(type="datetime", nullable=false)
     */
    private $created;

    /**
     * @var \DateTime $updated
     * @Gedmo\Timestampable(on="update")
     * @ORM\Column(type="datetime", nullable=false)
     */
    private $updated;

    /**
     * @var string
     * @Gedmo\Slug(fields={"title"}, updatable=false)
     * @ORM\Column(type="string")
     * @Groups({"networkSupportContent"})
     */
    protected $slug;

    /**
     * @var string
     * @ORM\Column(type="string", nullable=true)
     * @Groups({"networkPageDaemon"})
     */
    private $githubLinkTitle;

    /**
     * @var string
     * @ORM\Column(type="string", nullable=true)
     * @Groups({"networkPageDaemon"})
     */
    private $githubLink;

    /**
     * @var string
     * @ORM\Column(type="string", nullable=true)
     * @Groups({"networkPageDaemon"})
     */
    private $dockerLinkTitle;

    /**
     * @var string
     * @ORM\Column(type="string", nullable=true)
     * @Groups({"networkPageDaemon"})
     */
    private $dockerLink;


    public function __toString()
    {
        return $this->title ? $this->title: '';
    }

    /**
     * @return int
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getTitle(): ?string
    {
        return $this->title;
    }

    /**
     * @param string $title
     */
    public function setTitle(string $title)
    {
        $this->title = $title;
    }

    /**
     * @return string
     */
    public function getContent(): ?string
    {
        return $this->content;
    }

    /**
     * @param string $content
     */
    public function setContent(string $content)
    {
        $this->content = $content;
    }

    /**
     * @return \DateTime
     */
    public function getCreated(): \DateTime
    {
        return $this->created;
    }

    public function setCreated(\DateTimeInterface $created): self
    {
        $this->created = $created;

        return $this;
    }

    /**
     * @return \DateTime
     */
    public function getUpdated(): \DateTime
    {
        return $this->updated;
    }

    /**
     * @param \DateTime $updated
     */
    public function setUpdated(\DateTime $updated)
    {
        $this->updated = $updated;
    }

    /**
     * Updates the hash value to force the preUpdate and postUpdate events to fire
     */
    public function refreshUpdated()
    {
        $this->setUpdated(new \DateTime());
    }

    /**
     * @return string
     */
    public function getSlug(): ?string
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

    /**
     * @return string
     */
    public function getGithubLinkTitle(): ?string
    {
        return $this->githubLinkTitle;
    }

    /**
     * @param string $githubLinkTitle
     */
    public function setGithubLinkTitle(string $githubLinkTitle)
    {
        $this->githubLinkTitle = $githubLinkTitle;
    }

    /**
     * @return string
     */
    public function getGithubLink(): ?string
    {
        return $this->githubLink;
    }

    /**
     * @param string $githubLink
     */
    public function setGithubLink(string $githubLink)
    {
        $this->githubLink = $githubLink;
    }

    /**
     * @return string
     */
    public function getDockerLinkTitle(): ?string
    {
        return $this->dockerLinkTitle;
    }

    /**
     * @param string $dockerLinkTitle
     */
    public function setDockerLinkTitle(string $dockerLinkTitle)
    {
        $this->dockerLinkTitle = $dockerLinkTitle;
    }

    /**
     * @return string
     */
    public function getDockerLink(): ?string
    {
        return $this->dockerLink;
    }

    /**
     * @param string $dockerLink
     */
    public function setDockerLink(string $dockerLink)
    {
        $this->dockerLink = $dockerLink;
    }
}