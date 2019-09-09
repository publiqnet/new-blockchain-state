<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 9/9/19
 * Time: 10:49 AM
 */

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Sonata\TranslationBundle\Model\Gedmo\TranslatableInterface;

/**
 * @ORM\Table(name="dictionary")
 * @ORM\Entity()
 */
class Dictionary implements TranslatableInterface
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
     * @var string
     * @ORM\Column(type="string", length=64, unique=true)
     */
    private $wordKey;

    /**
     * @var string
     * @ORM\Column(type="string")
     * @Gedmo\Translatable
     */
    private $value;

    /**
     * @Gedmo\Locale
     * Used locale to override Translation listener`s locale
     * this is not a mapped field of entity metadata, just a simple property
     * and it is not necessary because globally locale can be set in listener
     */
    private $locale;


    public function __toString()
    {
        return $this->wordKey;
    }

    /**
     * @return int
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getWordKey(): ?string
    {
        return $this->wordKey;
    }

    /**
     * @param string $wordKey
     */
    public function setWordKey(string $wordKey)
    {
        $this->wordKey = $wordKey;
    }

    /**
     * @return string
     */
    public function getValue(): ?string
    {
        return $this->value;
    }

    /**
     * @param string $value
     */
    public function setValue(string $value)
    {
        $this->value = $value;
    }

    /**
     * @param string $locale
     */
    public function setLocale($locale)
    {
        $this->locale = $locale;
    }

    /**
     * @return mixed
     */
    public function getLocale()
    {
        return $this->locale;
    }
}