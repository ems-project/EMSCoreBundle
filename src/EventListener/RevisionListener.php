<?php

declare(strict_types=1);

namespace EMS\CoreBundle\EventListener;

use EMS\CommonBundle\Common\EMSLink;
use EMS\CommonBundle\Helper\EmsFields;
use EMS\CoreBundle\Event\UpdateRevisionReferersEvent;
use EMS\CoreBundle\Exception\LockedException;
use EMS\CoreBundle\Service\DataService;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class RevisionListener implements EventSubscriberInterface
{
    private DataService $dataService;
    private LoggerInterface $logger;

    public function __construct(DataService $dataService, LoggerInterface $logger)
    {
        $this->dataService = $dataService;
        $this->logger = $logger;
    }

    /**
     * @return array<string, array>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            UpdateRevisionReferersEvent::class => [
                ['updateReferers', 0],
            ],
        ];
    }

    public function updateReferers(UpdateRevisionReferersEvent $event): void
    {
        $callback = function (string $type, string $targetField, string $referrerEmsId, EMSLink $emsLink) {
            try {
                $form = null;
                $revision = $this->dataService->initNewDraft($emsLink->getContentType(), $emsLink->getOuuid());
                $data = $revision->getRawData();

                if (!isset($data[$targetField])) {
                    $data[$targetField] = [];
                }

                $currentData = $data[$targetField];

                if ('remove' === $type && \in_array($referrerEmsId, $currentData)) {
                    $data[$targetField] = \array_values(\array_diff($currentData, [$referrerEmsId]));
                    $revision->setRawData($data);
                    $this->dataService->finalizeDraft($revision, $form, null, false);
                } elseif ('add' === $type && !\in_array($referrerEmsId, $currentData)) {
                    $data[$targetField][] = $referrerEmsId;
                    $revision->setRawData($data);
                    $this->dataService->finalizeDraft($revision, $form, null, false);
                } else {
                    $this->dataService->discardDraft($revision);
                }
            } catch (LockedException $e) {
                $this->logger->error('service.data.update_referrers_error', [
                    EmsFields::LOG_CONTENTTYPE_FIELD => $emsLink->getContentType(),
                    EmsFields::LOG_OUUID_FIELD => $emsLink->getOuuid(),
                    EmsFields::LOG_EXCEPTION_FIELD => $e,
                    EmsFields::LOG_ERROR_MESSAGE_FIELD => $e->getMessage(),
                ]);
            }
        };

        foreach ($event->getRemoveEmsLinks() as $removeEmsLink) {
            $callback('remove', $event->getTargetField(), $event->getRefererOuuid(), $removeEmsLink);
        }

        foreach ($event->getAddEmsLinks() as $addEmsLink) {
            $callback('add', $event->getTargetField(), $event->getRefererOuuid(), $addEmsLink);
        }
    }
}
