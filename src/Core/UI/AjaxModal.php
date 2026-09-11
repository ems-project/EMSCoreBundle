<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\UI;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\TemplateWrapper;

final class AjaxModal
{
    private ?string $icon = null;
    private ?string $title = null;
    private ?string $body = null;
    private ?string $footer = null;
    private bool $success = false;

    /** @var array<mixed> */
    private array $messages = [];

    public function __construct(private readonly TemplateWrapper $template, private readonly TranslatorInterface $translator)
    {
    }

    public function addMessageSuccess(TranslatableMessage $key): self
    {
        $this->success = true;

        $this->messages[] = ['success' => $key->trans($this->translator)];

        return $this;
    }

    public function setIcon(?string $icon): self
    {
        $this->icon = $icon;

        return $this;
    }

    public function setTitleRaw(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function setTitle(TranslatableMessage $title): self
    {
        $this->title = $title->trans($this->translator);

        return $this;
    }

    /**
     * @param array<mixed> $context
     */
    public function setBody(string $block, array $context = []): self
    {
        $this->body = $this->template->renderBlock($block, $context);

        return $this;
    }

    public function setBodyHtml(string $html): self
    {
        $this->body = $html;

        return $this;
    }

    /**
     * @param array<mixed> $context
     */
    public function setFooter(string $block, array $context = []): self
    {
        $this->footer = $this->template->renderBlock($block, $context);

        return $this;
    }

    public function setFooterHtml(string $html): self
    {
        $this->footer = $html;

        return $this;
    }

    /**
     * @param array<mixed> $data
     */
    public function getSuccessResponse(array $data = []): JsonResponse
    {
        return new JsonResponse(\array_merge([
            'success' => true,
            'modalClose' => true,
        ], $data));
    }

    public function getResponse(): JsonResponse
    {
        $title = $this->icon && $this->title ?
            \sprintf('<i class="%s"></i> %s', $this->icon, $this->title)
            : $this->title;

        return new JsonResponse(\array_filter([
            'modalMessages' => $this->messages,
            'modalTitle' => $title,
            'modalBody' => $this->body,
            'modalFooter' => $this->footer,
            'modalSuccess' => $this->success,
        ], fn ($value) => null !== $value));
    }
}
