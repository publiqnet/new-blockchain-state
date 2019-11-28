<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 11/28/19
 * Time: 5:36 PM
 */

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Class BoostedContentUnitSpending
 * @package App\Entity
 *
 * @ORM\Table(name="boosted_content_unit_spending")
 * @ORM\Entity(repositoryClass="App\Repository\BoostedContentUnitSpendingRepository")
 */
class BoostedContentUnitSpending
{
    /**
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Block", inversedBy="spendings")
     */
    private $block;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\BoostedContentUnit", inversedBy="boostedContentUnitSpendings")
     */
    private $boostedContentUnit;

    /**
     * @ORM\Column(type="integer", nullable=false)
     */
    private $whole;

    /**
     * @ORM\Column(type="integer", nullable=false)
     */
    private $fraction;


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
    public function getBoostedContentUnit()
    {
        return $this->boostedContentUnit;
    }

    /**
     * @param mixed $boostedContentUnit
     */
    public function setBoostedContentUnit($boostedContentUnit)
    {
        $this->boostedContentUnit = $boostedContentUnit;
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
}