<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Service\Form\Submission;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\OptionsResolver\Exception\ExceptionInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class FormSubmissionRequest
{
    /** @var string */
    private $formName;
    /** @var string */
    private $instance;
    /** @var string */
    private $locale;
    /** @var array<mixed> */
    private $data;
    /** @var array<int, array{filename: string, mimeType: string, base64: string, size: string, form_field: string}> */
    private $files;
    /** @var string */
    private $label;
    /** @var \DateTime|null */
    private $expireDate;

    public function __construct(Request $request)
    {
        $json = \json_decode((string) $request->getContent(), true);

        if (!\is_array($json)) {
            throw new FormSubmissionException('invalid JSON!');
        }

        $submit = $this->resolveJson($json);

        $this->formName = $submit['form_name'];
        $this->instance = $submit['instance'];
        $this->locale = $submit['locale'];
        $this->data = $submit['data'];
        $this->files = $submit['files'];
        $this->label = $submit['label'] ?? '';
        $formattedDate = \DateTime::createFromFormat(\DateTime::ATOM, $submit['expire_date']);
        $this->expireDate = false != $formattedDate ? $formattedDate : null;
    }

    public function getFormName(): string
    {
        return $this->formName;
    }

    public function getInstance(): string
    {
        return $this->instance;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * @return array<int, array{filename: string, mimeType: string, base64: string, size: string, form_field: string}>
     */
    public function getFiles(): array
    {
        return $this->files;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getExpireDate(): ?\DateTime
    {
        return $this->expireDate;
    }

    /**
     * @param array<mixed> $json
     *
     * @return array{form_name: string, instance: string, locale: string, data: array, files: array, label: string, expire_date: string}
     */
    private function resolveJson(array $json): array
    {
        $jsonResolver = new OptionsResolver();
        $jsonResolver
            ->setRequired(['form_name', 'locale', 'data', 'instance'])
            ->setDefault('files', [])
            ->setDefault('label', '')
            ->setDefault('expire_date', '')
            ->setAllowedTypes('form_name', 'string')
            ->setAllowedTypes('locale', 'string')
            ->setAllowedTypes('data', 'array')
            ->setAllowedTypes('files', 'array')
            ->setAllowedTypes('label', 'string')
            ->setAllowedTypes('expire_date', 'string')
        ;

        try {
            /** @var array{form_name: string, instance: string, locale: string, data: array, files: array, label: string, expire_date: string} $json */
            $json = $jsonResolver->resolve($json);

            $fileResolver = new OptionsResolver();
            $fileResolver->setRequired(['filename', 'mimeType', 'base64', 'size', 'form_field']);

            $json['files'] = \array_map(function (array $file) use ($fileResolver) {
                return $fileResolver->resolve($file);
            }, $json['files']);

            return $json;
        } catch (ExceptionInterface $e) {
            throw new FormSubmissionException(\sprintf('Invalid configuration: %s', $e->getMessage()));
        }
    }
}
