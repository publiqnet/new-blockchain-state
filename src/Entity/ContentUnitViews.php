<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 11/14/19
 * Time: 3:40 PM
 */

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Class ContentUnitViews
 * @package App\Entity
 *
 * @ORM\Table(name="content_unit_views")
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
     */
    private $block;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\ContentUnit", inversedBy="viewsPerChannel")
     */
    private $contentUnit;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Account", inversedBy="views")
     */
    private $channel;

    /**
     * @ORM\Column(type="integer", nullable=false)
     */
    private $viewsTime;

    /**
     * @ORM\Column(type="integer", nullable=false)
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