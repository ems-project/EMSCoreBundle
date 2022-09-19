<?php

namespace EMS\CoreBundle\Helper\AssetExtractor;

use EMS\CommonBundle\Common\Standard\Json;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ExtractedData
{
    protected const FIELD_LANGUAGE = 'language';
    private ?string $locale;
    /** @var mixed[] */
    private array $source;

    /**
     * @param array<string, mixed> $source
     */
    private function __construct(array $source)
    {
        $this->source = $source;
        $optionsResolver = new OptionsResolver();
        $optionsResolver->setDefined(\array_keys($source));
        $optionsResolver
            ->setDefaults([
                self::FIELD_LANGUAGE => null,
            ])
            ->setAllowedTypes(self::FIELD_LANGUAGE, ['string', 'null'])
        ;

        /** @var array{language: null|string} $resolverOptions */
        $resolverOptions = $optionsResolver->resolve($source);

        $this->locale = $resolverOptions[self::FIELD_LANGUAGE];
    }

    public static function fromJsonString(string $json): self
    {
        return new self(Json::decode($json));
    }

    public static function fromMetaString(string $metaString): self
    {
        return new self(self::convertMetaStringToArray($metaString));
    }

    public function getLocale(): ?string
    {
        return $this->locale;
    }

    /**
     * @return mixed[]
     */
    public function getSource(): array
    {
        return $this->source;
    }

    /**
     * @return array<string, string>
     */
    private static function convertMetaStringToArray(string $data): array
    {
        if (!\mb_check_encoding($data)) {
            $data = \mb_convert_encoding($data, \mb_internal_encoding(), 'ASCII');
        }
        $cleaned = \preg_replace("/\r/", '', $data);
        if (null === $cleaned) {
            throw new \RuntimeException('It was possible to parse meta information');
        }
        $matches = [];
        \preg_match_all(
            '/^(.*): (.*)$/m',
            $cleaned,
            $matches,
            PREG_PATTERN_ORDER
        );
        $metaArray = \array_combine($matches[1], $matches[2]);
        if (false === $metaArray) {
            return [];
        }

        return $metaArray;
    }
}
