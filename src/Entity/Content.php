<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 2/21/19
 * Time: 4:32 PM
 */

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Class Content
 * @package App\Entity
 *
 * @ORM\Table(name="content")
 * @ORM\Entity
 */
class Content
{
    /**
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @ORM\Column(name="blockchain_content_id", type="string", length=64)
     */
    private $contentId;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Account", inversedBy="contents")
     */
    private $channel;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\ContentUnit", mappedBy="content", fetch="EXTRA_LAZY")
     */
    private $contentUnits;

    /**
     * @ORM\OneToOne(targetEntity="App\Entity\Transaction", mappedBy="content")
     */
    private $transaction;


    public function __construct()
    {
        $this->contentUnits = new ArrayCollection();
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
    public function getContentId()
    {
        return $this->contentId;
    }

    /**
     * @param mixed $contentId
     */
    public function setContentId($contentId)
    {
        $this->contentId = $contentId;
    }

    /**
     * @return mixed
     */
    public function getChannel()
    {
        return $this->channel;
    }

    /**
     * @param mixed $channel
     */
    public function setChannel($channel)
    {
        $this->channel = $channel;
    }

    /**
     * @return mixed
     */
    public function getContentUnits()
    {
        return $this->contentUnits;
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