<?php
/**
 * Created by PhpStorm.
 * User: grigor
 * Date: 9/26/18
 * Time: 3:14 PM
 */

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @package App\Entity
 *
 * @ORM\Table(name="index_number")
 * @ORM\Entity
 */
class IndexNumber
{
    /**
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     */
    protected $id;

    /**
     * @ORM\Column(name="last_notify_time", type="integer")
     */
    private $lastNotifyTime;

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param mixed $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * @return mixed
     */
    public function getLastNotifyTime()
    {
        return $this->lastNotifyTime;
    }

    /**
     * @param mixed $lastNotifyTime
     */
    public function setLastNotifyTime($lastNotifyTime)
    {
        $this->lastNotifyTime = $lastNotifyTime;
    }
}