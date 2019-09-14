<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 5/10/19
 * Time: 5:01 PM
 */

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ORM\Table(name="subscription")
 * @ORM\Entity()
 */
class Subscription
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Account", inversedBy="subscriptions")
     */
    private $subscriber;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Account", inversedBy="subscribers")
     * @Groups({"subscription_author"})
     */
    private $author;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Publication", inversedBy="subscribers")
     * @Groups({"subscription_publication"})
     */
    private $publication;

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return Account
     */
    public function getSubscriber()
    {
        return $this->subscriber;
    }

    /**
     * @param Account $subscriber
     */
    public function setSubscriber(Account $subscriber)
    {
        $this->subscriber = $subscriber;
    }

    /**
     * @return mixed
     */
    public function getAuthor()
    {
        return $this->author;
    }

    /**
     * @param mixed $author
     */
    public function setAuthor($author)
    {
        $this->author = $author;
    }

    /**
     * @return mixed
     */
    public function getPublication()
    {
        return $this->publication;
    }

    /**
     * @param mixed $publication
     */
    public function setPublication($publication)
    {
        $this->publication = $publication;
    }
}