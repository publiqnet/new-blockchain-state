<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 8/1/19
 * Time: 4:52 PM
 */

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Class BoostedContentUnit
 * @package App\Entity
 *
 * @ORM\Table(name="boosted_content_unit")
 * @ORM\Entity(repositoryClass="App\Repository\BoostedContentUnitRepository")
 */
class BoostedContentUnit
{
    /**
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @ORM\OneToOne(targetEntity="App\Entity\Transaction", mappedBy="boostedContentUnit")
     * @Groups({"boostedContentUnit", "boostedContentUnitMain"})
     */
    private $transaction;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Account", inversedBy="boostedContentUnits")
     * @Groups({"boostedContentUnit", "boostedContentUnitMain"})
     */
    private $sponsor;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\ContentUnit", inversedBy="boosts")
     * @Groups({"boostedContentUnit"})
     */
    private $contentUnit;

    /**
     * @ORM\Column(type="integer", nullable=false)
     * @Groups({"boostedContentUnit", "boostedContentUnitMain"})
     */
    private $startTimePoint;

    /**
     * @ORM\Column(type="integer", nullable=false)
     * @Groups({"boostedContentUnit", "boostedContentUnitMain"})
     */
    private $hours;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $cancelled;

    /**
     * @ORM\Column(type="integer", nullable=false)
     */
    private $whole;

    /**
     * @ORM\Column(type="integer", nullable=false)
     */
    private $fraction;

    /**
     * @var string
     * @Groups({"boostedContentUnit", "boostedContentUnitMain"})
     */
    private $status;


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
     * @return mixed
     */
    public function getSponsor()
    {
        return $this->sponsor;
    }

    /**
     * @param mixed $sponsor
     */
    public function setSponsor($sponsor)
    {
        $this->sponsor = $sponsor;
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
    public function getStartTimePoint()
    {
        return $this->startTimePoint;
    }

    /**
     * @param mixed $startTimePoint
     */
    public function setStartTimePoint($startTimePoint)
    {
        $this->startTimePoint = $startTimePoint;
    }

    /**
     * @return mixed
     */
    public function getHours()
    {
        return $this->hours;
    }

    /**
     * @param mixed $hours
     */
    public function setHours($hours)
    {
        $this->hours = $hours;
    }

    /**
     * @return mixed
     */
    public function isCancelled()
    {
        return $this->cancelled;
    }

    /**
     * @param boolean $cancelled
     */
    public function setCancelled($cancelled)
    {
        $this->cancelled = $cancelled;
    }

    /**
     * @return mixed
     */
    public function getWhole()
    {
        return $this->whole;
    }

    /**
     * @param mixed $whole
     */
    public function setWhole($whole)
    {
        $this->whole = $whole;
    }

    /**
     * @return mixed
     */
    public function getFraction()
    {
        return $this->fraction;
    }

    /**
     * @param mixed $fraction
     */
    public function setFraction($fraction)
    {
        $this->fraction = $fraction;
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
}