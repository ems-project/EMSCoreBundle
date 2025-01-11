<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Document;

use EMS\CommonBundle\Common\EMSLink;
use EMS\CommonBundle\Elasticsearch\Exception\NotFoundException;
use EMS\CoreBundle\Core\ContentType\ViewTypes;
use EMS\CoreBundle\Form\View\DataLinkViewType;
use EMS\CoreBundle\Service\ContentTypeService;
use EMS\CoreBundle\Service\SearchService;
use EMS\Helpers\Standard\Type;
use Symfony\Component\HttpFoundation\Request;

final readonly class DataLinksFactory
{
    public function __construct(private SearchService $searchService, private ContentTypeService $contentTypeService, private ViewTypes $viewTypes)
    {
    }

    public function create(Request $request): DataLinks
    {
        $query = $request->query;
        $page = $query->getInt('page', 1);
        $pattern = Type::string($query->get('q', ''));

        $dataLinks = new DataLinks($page, $pattern);
        $dataLinks->setLocale($query->get('locale'));
        $dataLinks->setQuerySearchName($query->get('querySearch'));

        if ($query->has('searchId')) {
            $dataLinks->setSearchId((int) $request->query->get('searchId'));
        }

        if ($query->has('referrerEmsId')) {
            try {
                $referrerEmsId = EMSLink::fromText(Type::string($query->get('referrerEmsId')));
                $referrerDocument = $this->searchService->getDocumentByEmsLink($referrerEmsId);
                $dataLinks->setReferrerDocument($referrerDocument);
            } catch (NotFoundException) {
            }
        }

        if ($query->has('type')) {
            $this->handleType($dataLinks, $query->get('type', ''));
        }

        return $dataLinks;
    }

    private function handleType(DataLinks $dataLinks, string $type): void
    {
        $types = \array_filter(\explode(',', $type));
        $contentTypes = \array_map(fn (string $type) => $this->contentTypeService->giveByName($type), $types);
        $dataLinks->addContentTypes(...$contentTypes);

        if (1 !== \count($contentTypes)) {
            return;
        }

        $contentType = $contentTypes[0];
        $customView = $contentType->getFirstViewByType('ems.view.data_link');

        if (null === $customView) {
            return;
        }

        /** @var DataLinkViewType $viewType */
        $viewType = $this->viewTypes->get('ems.view.data_link');
        $viewType->render($customView, $dataLinks);
        $dataLinks->customViewRendered();
    }
}
