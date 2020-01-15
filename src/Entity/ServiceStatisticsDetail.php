<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 1/15/20
 * Time: 11:21 AM
 */

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Class ServiceStatisticsDetail
 * @package App\Entity
 *
 * @ORM\Table(name="service_statistics_detail")
 * @ORM\Entity
 */
class ServiceStatisticsDetail
{
    /**
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\ServiceStatistics", inversedBy="details", fetch="EXTRA_LAZY")
     */
    private $serviceStatistics;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Account", inversedBy="servedDetails", fetch="EXTRA_LAZY")
     */
    private $storage;

    /**
     * @ORM\Column(type="integer")
     */
    private $count;


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
    public function getServiceStatistics()
    {
        return $this->serviceStatistics;
    }

    /**
     * @param mixed $serviceStatistics
     */
    public function setServiceStatistics($serviceStatistics)
    {
        $this->serviceStatistics = $serviceStatistics;
    }

    /**
     * @return mixed
     */
    public function getStorage()
    {
        return $this->storage;
    }

    /**
     * @param mixed $storage
     */
    public function setStorage($storage)
    {
        $this->storage = $storage;
    }

    /**
     * @return mixed
     */
    public function getCount()
    {
        return $this->count;
    }

    /**
     * @param mixed $count
     */
    public function setCount($count)
    {
        $this->count = $count;
    }
}