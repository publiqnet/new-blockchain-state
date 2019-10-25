<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 2/21/19
 * Time: 3:46 PM
 */

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\Index;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Class Reward
 * @package App\Entity
 *
 * @ORM\Table(name="reward",indexes={@Index(columns={"block_id"})})
 * @ORM\Entity
 */
class Reward
{
    /**
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Block", inversedBy="rewards", fetch="EXTRA_LAZY")
     */
    private $block;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Account", inversedBy="rewards", fetch="EXTRA_LAZY")
     * @Groups({"block"})
     */
    private $to;

    /**
     * @ORM\Column(name="whole", type="integer")
     * @Groups({"block"})
     */
    private $whole;

    /**
     * @ORM\Column(name="fraction", type="integer")
     * @Groups({"block"})
     */
    private $fraction;

    /**
     * @ORM\Column(name="reward_type", type="string", length=32)
     * @Groups({"block"})
     */
    private $rewardType;


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
    public function getTo()
    {
        return $this->to;
    }

    /**
     * @param Account $to
     */
    public function setTo(Account $to)
    {
        $this->to = $to;
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
     * @return mixed
     */
    public function getRewardType()
    {
        return $this->rewardType;
    }

    /**
     * @param mixed $rewardType
     */
    public function setRewardType($rewardType)
    {
        $this->rewardType = $rewardType;
    }
}