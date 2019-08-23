<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 8/23/19
 * Time: 11:13 AM
 */

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Class Tag
 * @package App\Entity
 *
 * @ORM\Table(name="tag")
 * @ORM\Entity
 */
class Tag
{
    /**
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=64, unique=true)
     * @Groups({"tag"})
     */
    private $name;

    /**
     * @ORM\ManyToMany(targetEntity="App\Entity\Publication", mappedBy="tags")
     */
    private $publications;

    public function __construct()
    {
        $this->publications = new ArrayCollection();
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
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param mixed $name
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * @return mixed
     */
    public function getPublications()
    {
        return $this->publications;
    }
}