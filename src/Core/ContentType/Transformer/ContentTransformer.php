<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\ContentType\Transformer;

use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Service\DataService;
use EMS\Helpers\ArrayHelper\ArrayHelper;
use EMS\Helpers\Standard\Json;

final readonly class ContentTransformer
{
    public function __construct(private ContentTransformers $transformers, private DataService $dataService)
    {
    }

    /**
     * @return array<mixed>
     */
    public function getTransformerDefinitions(ContentType $contentType): array
    {
        $transformerDefinitions = [];

        foreach ($contentType->getFieldType()->loopChildren() as $fieldType) {
            $migrationOptions = $fieldType->getOptions()['migrationOptions'] ?? [];
            $definedTransformers = $migrationOptions['transformers'] ?? [];

            foreach ($definedTransformers as $definition) {
                $transformer = $this->transformers->get($definition['class']);
                $config = $definition['config'] ?? '{}';

                if (!$transformer->supports($fieldType->getType())) {
                    continue;
                }

                $transformerDefinitions[$fieldType->getName()][] = [
                    'transformer' => $transformer,
                    'config' => $config,
                    'valid_config' => $transformer->validateConfig($config),
                ];
            }
        }

        return $transformerDefinitions;
    }

    /**
     * @param array<mixed> $transformerDefinitions
     */
    public function transform(Revision $revision, array $transformerDefinitions, string $user, bool $dryRun): bool
    {
        $rawData = ArrayHelper::map($revision->getRawData(), function ($value, $property) use ($transformerDefinitions) {
            if (\key_exists($property, $transformerDefinitions)) {
                return $this->transformValue($value, $transformerDefinitions[$property]);
            }

            return $value;
        });

        if ($rawData === $revision->getRawData()) {
            return false;
        }

        if (!$dryRun) {
            $revision = $this->dataService->initNewDraft($revision->getContentTypeName(), $revision->giveOuuid(), null, $user);
            $revision->setRawData($rawData);
            $this->dataService->finalizeDraft($revision, null, $user, false);
        }

        return true;
    }

    /**
     * @param array<mixed> $transformerDefinitions
     *
     * @return mixed
     */
    private function transformValue(mixed $value, array $transformerDefinitions)
    {
        foreach ($transformerDefinitions as $definition) {
            /** @var ContentTransformerInterface $transformer */
            $transformer = $definition['transformer'];
            $context = new TransformContext($value, Json::decode($definition['config'] ?? '{}'));
            $transformer->transform($context);

            if ($context->isTransformed()) {
                $value = $context->getTransformed();
            }
        }

        return $value;
    }
}
