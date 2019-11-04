<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 11/1/19
 * Time: 1:44 PM
 */

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Class StorageUpdate
 * @package App\Entity
 *
 * @ORM\Table(name="storage_update")
 * @ORM\Entity
 */
class StorageUpdate
{
    /**
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Account", inversedBy="storageUpdates")
     * @Groups({"explorerStorageUpdate"})
     */
    private $account;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\File", inversedBy="storageUpdates")
     * @Groups({"explorerStorageUpdate"})
     */
    private $file;

    /**
     * @ORM\OneToOne(targetEntity="App\Entity\Transaction", mappedBy="storageUpdate")
     */
    private $transaction;

    /**
     * @ORM\Column(name="status", type="string", length=64)
     * @Groups({"explorerStorageUpdate"})
     */
    private $status;

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
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * @param mixed $status
     */
    public function setStatus($status)
    {
        $this->status = $status;
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
    public function getTransaction()
    {
        return $this->transaction;
    }
}