<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2/21/19
 * Time: 6:07 PM
 */

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Class Transfer
 * @package App\Entity
 *
 * @ORM\Table(name="transfer")
 * @ORM\Entity(repositoryClass="App\Repository\TransferRepository")
 */
class Transfer
{
    /**
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Account", inversedBy="fromTransfers")
     */
    private $from;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Account", inversedBy="toTransfers")
     */
    private $to;

    /**
     * @ORM\Column(name="whole", type="integer")
     */
    private $whole;

    /**
     * @ORM\Column(name="fraction", type="integer")
     */
    private $fraction;

    /**
     * @ORM\Column(name="message", type="text")
     */
    private $message;

    /**
     * @ORM\OneToOne(targetEntity="App\Entity\Transaction", mappedBy="transfer")
     */
    private $transaction;


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
    public function getFrom()
    {
        return $this->from;
    }

    /**
     * @param mixed $from
     */
    public function setFrom($from)
    {
        $this->from = $from;
    }

    /**
     * @return mixed
     */
    public function getTo()
    {
        return $this->to;
    }

    /**
     * @param mixed $to
     */
    public function setTo($to)
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
    public function getMessage()
    {
        return $this->message;
    }

    /**
     * @param mixed $message
     */
    public function setMessage($message)
    {
        $this->message = $message;
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
}