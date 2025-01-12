<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Elasticsearch\Index;

class Settings
{
    /** @var array<array<mixed>> */
    private array $filters = [];

    /** @var array<array<mixed>> */
    private array $analyzers = [];

    /** @var array<string> */
    private array $languageAnalyzers = [];

    public function isEmpty(): bool
    {
        return empty($this->filters) && empty($this->analyzers);
    }

    /**
     * @return array<mixed>
     */
    public function toArray(): array
    {
        return [
            'analysis' => [
                'filter' => $this->filters,
                'analyzer' => $this->analyzers,
            ],
        ];
    }

    /**
     * @return array<mixed>
     */
    public function getLanguageAnalyzers(): array
    {
        return $this->languageAnalyzers;
    }

    public function addAnalyzerHtmlStrip(): Settings
    {
        $this->analyzers['html_strip'] = $this->createCustomAnalyzer(['standard']);

        return $this;
    }

    public function addAnalyzersEnglish(): Settings
    {
        $this->filters['english_stop'] = $this->getFilterStop('_english_');
        $this->filters['english_stemmer'] = $this->getFilterStemmer('english');
        $this->filters['empty_elision'] = $this->getFilterElision();

        $this->analyzers['english_for_highlighting'] = $this->createCustomAnalyzer(
            ['standard', 'lowercase', 'empty_elision', 'english_stemmer', 'english_stop']
        );

        $this->languageAnalyzers['en'] = 'english_for_highlighting';

        return $this;
    }

    public function addAnalyzersFrench(): Settings
    {
        $this->filters['french_stop'] = $this->getFilterStop('_french_');
        $this->filters['french_stemmer'] = $this->getFilterStemmer('light_french');
        $this->filters['french_elision'] = $this->getFilterElision(['l', 'm', 't', 'qu', 'n', 's', 'j', 'd', 'c', 'jusqu', 'quoiqu', 'lorsqu', 'puisq']);

        $this->analyzers['french_for_highlighting'] = $this->createCustomAnalyzer(
            ['standard', 'asciifolding', 'lowercase', 'french_elision', 'french_stemmer', 'french_stop']
        );

        $this->languageAnalyzers['fr'] = 'french_for_highlighting';

        return $this;
    }

    public function addAnalyzersDutch(): Settings
    {
        $this->filters['dutch_stop'] = $this->getFilterStop('_dutch_');
        $this->filters['dutch_stemmer'] = $this->getFilterStemmer('dutch');
        $this->filters['empty_elision'] = $this->getFilterElision();

        $this->analyzers['dutch_for_highlighting'] = $this->createCustomAnalyzer(
            ['standard', 'asciifolding', 'lowercase', 'empty_elision', 'dutch_stemmer', 'dutch_stop']
        );

        $this->languageAnalyzers['nl'] = 'dutch_for_highlighting';

        return $this;
    }

    public function addAnalyzersGerman(): Settings
    {
        $this->filters['german_stop'] = $this->getFilterStop('_german_');
        $this->filters['german_stemmer'] = $this->getFilterStemmer('light_german');
        $this->filters['empty_elision'] = $this->getFilterElision();

        $this->analyzers['german_for_highlighting'] = $this->createCustomAnalyzer(
            ['standard', 'lowercase', 'empty_elision', 'german_stemmer', 'german_stop']
        );

        $this->languageAnalyzers['de'] = 'german_for_highlighting';

        return $this;
    }

    /**
     * @param array<mixed> $filters
     *
     * @return array<mixed>
     */
    private function createCustomAnalyzer(array $filters): array
    {
        return [
            'filter' => $filters,
            'type' => 'custom',
            'char_filter' => ['html_strip'],
            'tokenizer' => 'standard',
        ];
    }

    /**
     * @param array<mixed> $articles
     *
     * @return array<mixed>
     */
    private function getFilterElision(array $articles = ['']): array
    {
        return ['type' => 'elision', 'articles' => $articles, 'articles_case' => 'false'];
    }

    /**
     * @return array<mixed>
     */
    private function getFilterStemmer(string $name): array
    {
        return ['name' => $name, 'type' => 'stemmer'];
    }

    /**
     * @return array<mixed>
     */
    private function getFilterStop(string $stopWords): array
    {
        return ['ignore_case' => 'false', 'remove_trailing' => 'true', 'type' => 'stop', 'stopwords' => $stopWords];
    }
}
