<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Form;

use EMS\CommonBundle\Entity\EntityInterface;
use EMS\CommonBundle\Helper\Text\Encoder;
use EMS\CoreBundle\Entity\Form;
use EMS\CoreBundle\Exception\FormNotFoundException;
use EMS\CoreBundle\Repository\FormRepository;
use EMS\CoreBundle\Service\EntityServiceInterface;
use Psr\Log\LoggerInterface;

class FormManager implements EntityServiceInterface
{
    public function __construct(private readonly FormRepository $formRepository, private readonly LoggerInterface $logger)
    {
    }

    #[\Override]
    public function isSortable(): bool
    {
        return true;
    }

    #[\Override]
    public function get(int $from, int $size, ?string $orderField, string $orderDirection, string $searchValue, mixed $context = null): array
    {
        if (null !== $context) {
            throw new \RuntimeException('Unexpected not null context');
        }

        return $this->formRepository->get($from, $size, $orderField, $orderDirection, $searchValue);
    }

    #[\Override]
    public function getEntityName(): string
    {
        return 'form';
    }

    /**
     * @return string[]
     */
    #[\Override]
    public function getAliasesName(): array
    {
        return [
            'forms',
            'Form',
            'Forms',
        ];
    }

    #[\Override]
    public function count(string $searchValue = '', mixed $context = null): int
    {
        if (null !== $context) {
            throw new \RuntimeException('Unexpected not null context');
        }

        return $this->formRepository->counter($searchValue);
    }

    public function update(Form $form): void
    {
        if (0 === $form->getOrderKey()) {
            $form->setOrderKey($this->formRepository->counter() + 1);
        }
        $form->setName(new Encoder()->slug(text: $form->getName(), separator: '_')->toString());
        $this->formRepository->create($form);
    }

    /**
     * @param string[] $ids
     */
    public function reorderByIds(array $ids): void
    {
        $counter = 1;
        foreach ($ids as $id) {
            $channel = $this->formRepository->getById($id);
            $channel->setOrderKey($counter++);
            $this->formRepository->create($channel);
        }
    }

    /**
     * @param string[] $ids
     */
    public function deleteByIds(array $ids): void
    {
        foreach ($this->formRepository->getByIds($ids) as $form) {
            $this->delete($form);
        }
    }

    public function delete(Form $form): void
    {
        $name = $form->getName();
        $this->formRepository->delete($form);
        $this->logger->warning('log.service.form.delete', [
            'name' => $name,
        ]);
    }

    public function getByName(string $name): Form
    {
        $form = $this->formRepository->getByName($name);
        if (null === $form) {
            throw new FormNotFoundException(\sprintf('Form %s not found', $name));
        }

        return $form;
    }

    #[\Override]
    public function getByItemName(string $name): ?EntityInterface
    {
        return $this->formRepository->getByName($name);
    }

    #[\Override]
    public function updateEntityFromJson(EntityInterface $entity, string $json): EntityInterface
    {
        if (!$entity instanceof Form) {
            throw new \RuntimeException('Unexpected form object');
        }
        $form = Form::fromJson($json, $entity);
        $this->formRepository->create($form);

        return $form;
    }

    #[\Override]
    public function createEntityFromJson(string $json, ?string $name = null): EntityInterface
    {
        $form = Form::fromJson($json);
        if (null !== $name && $form->getName() !== $name) {
            throw new \RuntimeException(\sprintf('Form name mismatched: %s vs %s', $form->getName(), $name));
        }
        $this->formRepository->create($form);

        return $form;
    }

    #[\Override]
    public function deleteByItemName(string $name): string
    {
        $form = $this->formRepository->getByName($name);
        if (null === $form) {
            throw new \RuntimeException(\sprintf('Form %s not found', $name));
        }
        $id = $form->getId();
        $this->formRepository->delete($form);

        return $id;
    }

    /**
     * @return Form[]
     */
    public function getAll(): array
    {
        return $this->formRepository->getAll();
    }

    /**
     * @param mixed[] $structure
     */
    public function reorderFields(Form $form, array $structure): void
    {
        $form->getFieldType()->reorderFields($structure);
        $this->update($form);
    }
}
