<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\ContentType\Action;

enum EventType: string
{
    case Publish = 'publish';
    case Unpublish = 'unpublish';
    case FinalizeDraft = 'finalize_draft';
    case NewDraft = 'new_draft';
}
