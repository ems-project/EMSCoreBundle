<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Environment;

use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Service\SearchService;
use Twig\Environment as TwigEnvironment;
use Twig\TemplateWrapper;

class EnvironmentPublisherFactory
{
    /** @var array<string, ?TemplateWrapper> */
    private array $templates = [];
    /** @var array<string, EnvironmentPublisher> */
    private array $publishers = [];

    public function __construct(private readonly TwigEnvironment $templating, private readonly SearchService $searchService)
    {
    }

    public function create(Environment $environment, Revision $revision): EnvironmentPublisher
    {
        $currentPublisher = $this->publishers[$environment->getName()] ?? null;

        $messages = $currentPublisher ? $currentPublisher->getMessages() : [];
        $publisher = new EnvironmentPublisher($revision, $messages);

        if ($template = $this->getTemplate($environment)) {
            $template->render([
                'publication' => $publisher,
                'environment' => $environment,
                'revision' => $revision,
                'document' => $this->searchService->getDocument($revision->giveContentType(), $revision->giveOuuid()),
            ]);
        }

        $this->publishers[$environment->getName()] = $publisher;

        return $publisher;
    }

    public function getPublisher(Environment $environment): EnvironmentPublisher
    {
        return $this->publishers[$environment->getName()];
    }

    private function getTemplate(Environment $environment): ?TemplateWrapper
    {
        if (isset($this->templates[$environment->getName()])) {
            return $this->templates[$environment->getName()];
        }

        $environmentTemplate = $environment->getTemplatePublication();
        $template = $environmentTemplate ? $this->templating->createTemplate($environmentTemplate) : null;

        $this->templates[$environment->getName()] = $template;

        return $template;
    }
}
