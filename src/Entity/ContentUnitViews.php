<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 11/18/19
 * Time: 1:57 PM
 */

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\Index;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Class ContentUnitViews
 * @package App\Entity
 *
 * @ORM\Table(name="content_unit_views", indexes={@Index(columns={"views_time"})})
 * @ORM\Entity(repositoryClass="App\Repository\ContentUnitViewsRepository")
 */
class ContentUnitViews
{
    /**
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Block", inversedBy="views")
     * @Groups({"contentUnitViews"})
     */
    private $block;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\ContentUnit", inversedBy="viewsPerChannel")
     */
    private $contentUnit;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Account", inversedBy="views")
     * @Groups({"contentUnitViews"})
     */
    private $channel;

    /**
     * @ORM\Column(type="integer", nullable=false)
     * @Groups({"contentUnitViews"})
     */
    private $viewsTime;

    /**
     * @ORM\Column(type="integer", nullable=false)
     * @Groups({"contentUnitViews"})
     */
    private $viewsCount;


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
    public function getBlock()
    {
        return $this->block;
    }

    /**
     * @param mixed $block
     */
    public function setBlock($block)
    {
        $this->block = $block;
    }

    /**
     * @return mixed
     */
    public function getContentUnit()
    {
        return $this->contentUnit;
    }

    /**
     * @param mixed $contentUnit
     */
    public function setContentUnit($contentUnit)
    {
        $this->contentUnit = $contentUnit;
    }

    /**
     * @return mixed
     */
    public function getViewsTime()
    {
        return $this->viewsTime;
    }

    /**
     * @param mixed $viewsTime
     */
    public function setViewsTime($viewsTime)
    {
        $this->viewsTime = $viewsTime;
    }

    /**
     * @return mixed
     */
    public function getViewsCount()
    {
        return $this->viewsCount;
    }

    /**
     * @param mixed $viewsCount
     */
    public function setViewsCount($viewsCount)
    {
        $this->viewsCount = $viewsCount;
    }
}