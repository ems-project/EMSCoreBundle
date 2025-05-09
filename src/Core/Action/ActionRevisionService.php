<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Action;

use EMS\CommonBundle\Common\Ai\OpenAiRequest;
use EMS\CommonBundle\Common\Ai\OpenAiResponseSchema;
use EMS\CoreBundle\Core\ContentType\FieldType\FieldTypeService;
use EMS\CoreBundle\Core\Revision\RawDataTransformer;
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Service\Revision\RevisionService;
use EMS\Helpers\ArrayHelper\ArrayHelper;
use EMS\Helpers\Standard\Json;
use Symfony\Component\PropertyAccess\PropertyAccessor;

class ActionRevisionService
{
    private readonly PropertyAccessor $propertyAccessor;

    public function __construct(
        private readonly ActionService $actionService,
        private readonly RevisionService $revisionService,
        private readonly FieldTypeService $fieldTypeService
    ) {
        $this->propertyAccessor = new PropertyAccessor();
    }

    /**
     * @return array{ 'outputFields': string[] }
     */
    public function handle(int $revisionId, int $fieldId): array
    {
        $revision = $this->getDraftRevision($revisionId);
        $config = $this->getConfig($fieldId);

        $ai = $config->ai;
        if (null === $ai || 'openai' !== $ai['provider']) {
            throw new \RuntimeException('For now only AI openai is supported!');
        }

        $inputJson = $this->buildInputJson($revision, $config);
        $outputSchema = $this->buildOutputSchema($config);
        $body = ArrayHelper::map(
            data: $ai['request'],
            mapper: static fn ($value) => '%inputJson%' === $value ? $inputJson : $value
        );

        $openAiRequest = OpenAiRequest::withResponseSchema($body, $outputSchema);
        $this->actionService->requestFromRevision($revision, $openAiRequest->body);

        return ['outputFields' => $config->getOutputFields()];
    }

    private function getConfig(int $fieldId): ActionRevisionConfig
    {
        $fieldType = $this->fieldTypeService->getById($fieldId);
        $fieldTree = $this->fieldTypeService->getTree($fieldType->giveContentType());

        return ActionRevisionConfig::fromFieldType($fieldType, $fieldTree);
    }

    private function getDraftRevision(int $revisionId): Revision
    {
        $revision = $this->revisionService->getByRevisionId($revisionId);
        if (!$revision->isDraft()) {
            throw new \RuntimeException('Revision is not in draft');
        }

        return $revision;
    }

    private function buildInputJson(Revision $revision, ActionRevisionConfig $config): string
    {
        $inputData = [];

        $data = $revision->getAutoSave() ?? $revision->getRawData();
        $rawData = RawDataTransformer::transform($revision->giveContentType()->getFieldType(), $data);

        foreach ($config->input as $inputField) {
            $inputPropertyPath = $inputField->getPropertyPath();
            if (!$this->propertyAccessor->isReadable($rawData, $inputPropertyPath)) {
                continue;
            }

            $value = $this->propertyAccessor->getValue($rawData, $inputPropertyPath);
            $inputData[$inputField->getPath()] = $value;
        }

        return Json::encode($inputData);
    }

    private function buildOutputSchema(ActionRevisionConfig $config): OpenAiResponseSchema
    {
        $schema = new OpenAiResponseSchema();

        foreach ($config->output as $outputField) {
            $schema->addStringProperty($outputField->getPropertyPath());
        }

        return $schema;
    }
}
