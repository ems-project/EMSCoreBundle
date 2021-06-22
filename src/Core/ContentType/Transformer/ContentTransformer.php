<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\ContentType\Transformer;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\ORM\EntityManagerInterface;
use EMS\CommonBundle\Common\ArrayHelper\RecursiveMapper;
use EMS\CommonBundle\Common\Standard\Json;
use EMS\CoreBundle\Core\Revision\Revisions;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Service\DataService;

final class ContentTransformer
{
    private EntityManagerInterface $em;
    private ContentTransformers $transformers;
    private DataService $dataService;

    public const USER = 'SYSTEM_CONTENT_TRANSFORM';

    public function __construct(
        Registry $doctrine,
        ContentTransformers $transformers,
        DataService $dataService
    ) {
        $em = $doctrine->getManager();
        if (!$em instanceof EntityManagerInterface) {
            throw new \Exception('Could not find doctrine entity manager');
        }

        $this->em = $em;
        $this->transformers = $transformers;
        $this->dataService = $dataService;
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
                $config = $definition['config'] ?? '';

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
     *
     * @return \Generator|array[]
     */
    public function transform(Revisions $revisions, array $transformerDefinitions, int $batchSize, bool $dryRun): \Generator
    {
        $this->em->getConnection()->getConfiguration()->setSQLLogger(null);
        $this->em->getConnection()->setAutoCommit(false);
        $activeTransaction = false;

        foreach ($revisions as $i => $revision) {
            $transformed = $this->transformRevision($revision, $transformerDefinitions, $dryRun);

            if ($transformed) {
                $activeTransaction = true;
            }

            yield [$revision->getOuuid(), $transformed];

            if (($i % $batchSize) === 0 && $activeTransaction && !$dryRun) {
                $this->em->commit();
                $this->em->clear(Revision::class);
            }
        }

        if ($activeTransaction && !$dryRun) {
            $this->em->commit();
            $this->em->clear(Revision::class);
        }
    }

    /**
     * @param array<mixed> $transformerDefinitions
     */
    private function transformRevision(Revision $revision, array $transformerDefinitions, bool $dryRun): bool
    {
        $rawData = $revision->getRawData();
        RecursiveMapper::mapPropertyValue(
            $rawData,
            function (string $property, $value) use ($transformerDefinitions) {
                if (\key_exists($property, $transformerDefinitions)) {
                    return $this->transformValue($value, $transformerDefinitions[$property]);
                }

                return $value;
            }
        );

        if ($rawData === $revision->getRawData()) {
            return false;
        }

        if (!$dryRun) {
            $revision = $this->dataService->initNewDraft($revision->getContentTypeName(), $revision->getOuuid(), null, self::USER);
            $revision->setRawData($rawData);
            $this->dataService->finalizeDraft($revision, $revisionType, self::USER, false);
        }

        return true;
    }

    /**
     * @param mixed        $value
     * @param array<mixed> $transformerDefinitions
     *
     * @return mixed
     */
    private function transformValue($value, array $transformerDefinitions)
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
