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
     * @ORM\Column(type="integer")
     */
    private $tempNumber;

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
    public function getTempNumber()
    {
        return $this->tempNumber;
    }

    /**
     * @param mixed $tempNumber
     */
    public function setTempNumber($tempNumber)
    {
        $this->tempNumber = $tempNumber;
    }
}