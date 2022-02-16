<?php

namespace EMS\CoreBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use EMS\CoreBundle\Entity\Helper\JsonClass;
use EMS\CoreBundle\Entity\Helper\JsonDeserializer;

/**
 * I18n.
 *
 * @ORM\Table(name="i18n")
 * @ORM\Entity()
 * @ORM\HasLifecycleCallbacks()
 */
class I18n extends JsonDeserializer implements \JsonSerializable, EntityInterface
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
     * @var \DateTime
     *
     * @ORM\Column(name="created", type="datetime")
     */
    private $created;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="modified", type="datetime")
     */
    private $modified;

    /**
     * @var string
     *
     * @ORM\Column(name="identifier", type="string", unique=true, length=200)
     * @ORM\OrderBy({"identifier" = "ASC"})
     */
    protected $identifier;

    /**
     * @var array
     *
     * @ORM\Column(name="content", type="json_array")
     */
    protected $content;

    /**
     * @ORM\PrePersist
     * @ORM\PreUpdate
     */
    public function updateModified()
    {
        $this->modified = new \DateTime();
        if (!isset($this->created)) {
            $this->created = $this->modified;
        }
    }

    /**
     * Get id.
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set created.
     *
     * @param \DateTime $created
     *
     * @return I18n
     */
    public function setCreated($created)
    {
        $this->created = $created;

        return $this;
    }

    /**
     * Get created.
     *
     * @return \DateTime
     */
    public function getCreated()
    {
        return $this->created;
    }

    /**
     * Set modified.
     *
     * @param \DateTime $modified
     *
     * @return I18n
     */
    public function setModified($modified)
    {
        $this->modified = $modified;

        return $this;
    }

    /**
     * Get modified.
     *
     * @return \DateTime
     */
    public function getModified()
    {
        return $this->modified;
    }

    /**
     * Set content.
     *
     * @param array $content
     *
     * @return I18n
     */
    public function setContent($content)
    {
        $this->content = $content;

        return $this;
    }

    /**
     * Get content.
     *
     * @return array
     */
    public function getContent()
    {
        return $this->content;
    }

    /**
     * Get content of locale.
     *
     * @param string $locale
     *
     * @return string
     */
    public function getContentTextforLocale($locale)
    {
        if (!empty($this->content)) {
            foreach ($this->content as $translation) {
                if ($translation['locale'] === $locale) {
                    return $translation['text'];
                }
            }
        }

        return 'no match found for key'.$this->getIdentifier().' with locale '.$locale;
    }

    /**
     * Set identifier.
     *
     * @param string $identifier
     *
     * @return I18n
     */
    public function setIdentifier($identifier)
    {
        $this->identifier = $identifier;

        return $this;
    }

    /**
     * Get identifier.
     *
     * @return string
     */
    public function getIdentifier()
    {
        return $this->identifier;
    }

    public function jsonSerialize(): JsonClass
    {
        $json = new JsonClass(\get_object_vars($this), __CLASS__);
        $json->removeProperty('id');
        $json->removeProperty('created');
        $json->removeProperty('modified');

        return $json;
    }

    public static function fromJson(string $json, ?EntityInterface $dashboard = null): I18n
    {
        $meta = JsonClass::fromJsonString($json);
        $dashboard = $meta->jsonDeserialize($dashboard);
        if (!$dashboard instanceof I18n) {
            throw new \Exception(\sprintf('Unexpected object class, got %s', $meta->getClass()));
        }

        return $dashboard;
    }

    public function getName(): string
    {
        return $this->identifier;
    }
}
