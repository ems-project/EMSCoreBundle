<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Mcp;

use EMS\CoreBundle\Entity\ChoiceList;
use EMS\CoreBundle\Repository\ChoiceListRepository;
use EMS\Helpers\Html\MimeTypes;
use Mcp\Exception\ResourceNotFoundException;
use Mcp\Server\Builder;

/**
 * @phpstan-type ChoiceListResource array{name: string, label: string, description: string, choices: array<string, array{label: string, value: string, group: array{name: string, label: string}|null}>}
 */
final readonly class ElasticmsMcpResourceChoiceListService
{
    private const string RESOURCE_URI = 'elasticms://choice-lists';
    private const string RESOURCE_TEMPLATE_URI = 'elasticms://choice-lists/{name}';

    public function __construct(private ChoiceListRepository $choiceListRepository)
    {
    }

    public function addChoiceListResources(Builder $builder): void
    {
        $builder
            ->addResource(
                handler: $this->getChoiceLists(...),
                uri: self::RESOURCE_URI,
                name: 'choice_lists',
                title: 'Choice lists',
                description: 'Lists all elasticMS choice lists and their choices.',
                mimeType: MimeTypes::APPLICATION_JSON->value,
            )
            ->addResourceTemplate(
                handler: $this->getChoiceListByName(...),
                uriTemplate: self::RESOURCE_TEMPLATE_URI,
                name: 'choice_list',
                title: 'Choice list',
                description: 'Returns one elasticMS choice list and its choices. Replace {name} with the URL-encoded choice list name.',
                mimeType: MimeTypes::APPLICATION_JSON->value,
            );
    }

    /**
     * @return array{choiceLists: list<ChoiceListResource>}
     */
    public function getChoiceLists(): array
    {
        return [
            'choiceLists' => \array_values(\array_map(
                $this->buildChoiceList(...),
                $this->choiceListRepository->getAll(),
            )),
        ];
    }

    /**
     * @return ChoiceListResource
     */
    public function getChoiceListByName(string $name): array
    {
        $decodedName = \rawurldecode($name);
        $choiceList = $this->choiceListRepository->getByName($decodedName);
        if (!$choiceList instanceof ChoiceList) {
            throw new ResourceNotFoundException(\str_replace('{name}', $name, self::RESOURCE_TEMPLATE_URI));
        }

        return $this->buildChoiceList($choiceList);
    }

    /**
     * @return ChoiceListResource
     */
    private function buildChoiceList(ChoiceList $choiceList): array
    {
        return [
            'name' => $choiceList->getName(),
            'label' => $choiceList->getLabel(),
            'description' => $choiceList->getDescription(),
            'choices' => $choiceList->getLabelsAndGroups(),
        ];
    }
}
