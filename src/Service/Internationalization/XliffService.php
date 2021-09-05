<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Service\Internationalization;

use EMS\CommonBundle\Elasticsearch\Document\Document;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Helper\Xliff\Extractor;
use Psr\Log\LoggerInterface;

class XliffService
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @param string[] $fields
     */
    public function extract(Document $source, Extractor $extractor, array $fields, ContentType $targetContentType, Environment $targetEnvironment, ?string $sourceDocumentField): void
    {
    }
}
