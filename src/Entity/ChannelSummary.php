<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 2/17/20
 * Time: 1:45 PM
 */

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Class ChannelSummary
 * @package App\Entity
 *
 * @ORM\Table(name="channel_summary")
 * @ORM\Entity
 */
class ChannelSummary
{
    /**
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Account", inversedBy="channelSummary")
     */
    private $channel;

    /**
     * @ORM\Column(name="published_month", type="integer", nullable=true)
     */
    private $publishedMonth = 0;

    /**
     * @ORM\Column(name="published_week", type="integer", nullable=true)
     */
    private $publishedWeek = 0;

    /**
     * @ORM\Column(name="published_day", type="integer", nullable=true)
     */
    private $publishedDay = 0;

    /**
     * @ORM\Column(name="distributed_month", type="integer", nullable=true)
     */
    private $distributedMonth = 0;

    /**
     * @ORM\Column(name="distributed_week", type="integer", nullable=true)
     */
    private $distributedWeek = 0;

    /**
     * @ORM\Column(name="distributed_day", type="integer", nullable=true)
     */
    private $distributedDay = 0;



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
    public function getPublishedMonth()
    {
        return $this->publishedMonth;
    }

    /**
     * @param mixed $publishedMonth
     */
    public function setPublishedMonth($publishedMonth)
    {
        $this->publishedMonth = $publishedMonth;
    }

    /**
     * @return mixed
     */
    public function getPublishedWeek()
    {
        return $this->publishedWeek;
    }

    /**
     * @param mixed $publishedWeek
     */
    public function setPublishedWeek($publishedWeek)
    {
        $this->publishedWeek = $publishedWeek;
    }

    /**
     * @return mixed
     */
    public function getPublishedDay()
    {
        return $this->publishedDay;
    }

    /**
     * @param mixed $publishedDay
     */
    public function setPublishedDay($publishedDay)
    {
        $this->publishedDay = $publishedDay;
    }

    /**
     * @return mixed
     */
    public function getDistributedMonth()
    {
        return $this->distributedMonth;
    }

    /**
     * @param mixed $distributedMonth
     */
    public function setDistributedMonth($distributedMonth)
    {
        $this->distributedMonth = $distributedMonth;
    }

    /**
     * @return mixed
     */
    public function getDistributedWeek()
    {
        return $this->distributedWeek;
    }

    /**
     * @param mixed $distributedWeek
     */
    public function setDistributedWeek($distributedWeek)
    {
        $this->distributedWeek = $distributedWeek;
    }

    /**
     * @return mixed
     */
    public function getDistributedDay()
    {
        return $this->distributedDay;
    }

    /**
     * @param mixed $distributedDay
     */
    public function setDistributedDay($distributedDay)
    {
        $this->distributedDay = $distributedDay;
    }
}