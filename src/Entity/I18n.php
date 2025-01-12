<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Entity;

use EMS\CommonBundle\Entity\CreatedModifiedTrait;
use EMS\CommonBundle\Entity\IdentifierIntegerTrait;
use EMS\CoreBundle\Entity\Helper\JsonClass;
use EMS\CoreBundle\Entity\Helper\JsonDeserializer;
use EMS\Helpers\Standard\DateTime;

class I18n extends JsonDeserializer implements \JsonSerializable, EntityInterface
{
    use CreatedModifiedTrait;
    use IdentifierIntegerTrait;

    /** @var string */
    protected $identifier;
    /** @var array<array{locale: string, text: string}> */
    protected array $content = [];

    public function __construct()
    {
        $this->created = DateTime::create('now');
        $this->modified = DateTime::create('now');
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

    #[\Override]
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

    #[\Override]
    public function getName(): string
    {
        return $this->identifier;
    }
}
