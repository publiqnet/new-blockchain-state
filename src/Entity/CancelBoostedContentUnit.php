<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 11/26/19
 * Time: 5:33 PM
 */

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Class CancelBoostedContentUnit
 * @package App\Entity
 *
 * @ORM\Table(name="cancel_boosted_content_unit")
 * @ORM\Entity
 */
class CancelBoostedContentUnit
{
    /**
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @ORM\OneToOne(targetEntity="App\Entity\Transaction", mappedBy="cancelBoostedContentUnit")
     */
    private $transaction;

    /**
     * @ORM\OneToOne(targetEntity="App\Entity\BoostedContentUnit", inversedBy="cancelBoostedContentUnit")
     * @ORM\JoinColumn(name="cancel_boosted_content_unit_id", referencedColumnName="id", onDelete="SET NULL")
     */
    private $boostedContentUnit;


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
}