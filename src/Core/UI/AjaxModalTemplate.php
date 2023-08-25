<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\UI;

use Twig\TemplateWrapper;

class AjaxModalTemplate
{
    private string $blockTitle = 'modal_title';
    private string $blockBody = 'modal_body';
    private string $blockFooter = 'modal_footer';

    public function __construct(private readonly TemplateWrapper $template)
    {
    }

    /**
     * @param array<string, mixed> $context
     * @param string[]             $warnings
     *
     * @return array{
     *      modalTitle?: string,
     *      modalBody?: string,
     *      modalFooter?: string,
     * }
     */
    public function render(array $context, array $warnings = []): array
    {
        return \array_filter([
            'modalMessages' => \array_map(static fn (string $warning) => ['warning' => $warning], $warnings),
            'modalTitle' => $this->renderBlock($this->blockTitle, $context),
            'modalBody' => $this->renderBlock($this->blockBody, $context),
            'modalFooter' => $this->renderBlock($this->blockFooter, $context),
        ], static fn ($value) => null !== $value);
    }

    public function setBlockTitle(string $blockTitle): self
    {
        $this->blockTitle = $blockTitle;

        return $this;
    }

    public function setBlockBody(string $blockBody): self
    {
        $this->blockBody = $blockBody;

        return $this;
    }

    public function setBlockFooter(string $blockFooter): self
    {
        $this->blockFooter = $blockFooter;

        return $this;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function renderBlock(string $block, array $context): ?string
    {
        if (!$this->template->hasBlock($block)) {
            return null;
        }

        return $this->template->renderBlock($block, $context);
    }
}
