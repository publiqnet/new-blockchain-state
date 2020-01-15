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
     * @ORM\ManyToOne(targetEntity="App\Entity\ContentUnit", inversedBy="servedDetails", fetch="EXTRA_LAZY")
     */
    private $contentUnit;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\File", inversedBy="servedDetails", fetch="EXTRA_LAZY")
     */
    private $file;

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
    public function getFile()
    {
        return $this->file;
    }

    /**
     * @param mixed $file
     */
    public function setFile($file)
    {
        $this->file = $file;
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