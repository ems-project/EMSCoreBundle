<?php

namespace EMS\CoreBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use EMS\CoreBundle\Entity\Helper\JsonClass;
use EMS\CoreBundle\Entity\Helper\JsonDeserializer;
use EMS\Helpers\Standard\DateTime;

/**
 * I18n.
 *
 * @ORM\Table(name="i18n")
 * @ORM\Entity()
 * @ORM\HasLifecycleCallbacks()
 */
class I18n extends JsonDeserializer implements \JsonSerializable, EntityInterface
{
    use CreatedModifiedTrait;
    /**
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private int $id;

    /**
     * @var string
     *
     * @ORM\Column(name="identifier", type="string", unique=true, length=200)
     * @ORM\OrderBy({"identifier" = "ASC"})
     */
    protected $identifier;

    /**
     * @var array<array{locale: string, text: string}>
     *
     * @ORM\Column(name="content", type="json")
     */
    protected array $content = [];

    public function __construct()
    {
        $this->created = DateTime::create('now');
        $this->modified = DateTime::create('now');
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
     * @param array<array{locale: string, text: string}> $content
     */
    public function setContent(array $content): I18n
    {
        $this->content = $content;

        return $this;
    }

    /**
     * @return array<array{locale: string, text: string}>
     */
    public function getContent(): array
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
        $json = new JsonClass(\get_object_vars($this), self::class);
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
