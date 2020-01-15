<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 11/1/19
 * Time: 2:09 PM
 */

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Class ServiceStatistics
 * @package App\Entity
 *
 * @ORM\Table(name="service_statistics")
 * @ORM\Entity
 */
class ServiceStatistics
{
    /**
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Account", inversedBy="serviceStatistics")
     * @Groups({"explorerServiceStatistics"})
     */
    private $account;

    /**
     * @ORM\OneToOne(targetEntity="App\Entity\Transaction", mappedBy="serviceStatistic")
     */
    private $transaction;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\ServiceStatisticsDetail", mappedBy="serviceStatistics", cascade={"remove"}, fetch="EXTRA_LAZY")
     */
    private $details;

    public function __construct()
    {
        $this->details = new ArrayCollection();
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
    public function getAccount()
    {
        return $this->account;
    }

    /**
     * @param mixed $account
     */
    public function setAccount($account)
    {
        $this->account = $account;
    }

    /**
     * @return mixed
     */
    public function getTransaction()
    {
        return $this->transaction;
    }

    /**
     * @return mixed
     */
    public function getDetails()
    {
        return $this->details;
    }
}