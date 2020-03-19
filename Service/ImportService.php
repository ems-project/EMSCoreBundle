<?php


namespace EMS\CoreBundle\Service;


use Doctrine\Bundle\DoctrineBundle\Registry;
use EMS\CoreBundle\Elasticsearch\Bulker;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Helper\DocumentImporter;
use Symfony\Component\Form\FormFactoryInterface;

class ImportService
{

    /** @var DataService */
    protected $dataService;
    /** @var FormFactoryInterface */
    private $formFactory;
    /** @var Registry */
    private $doctrine;
    /** @var Bulker */
    private $bulker;
    /** @var string */
    private $instanceId;

    public function __construct(Registry $doctrine, DataService $dataService, FormFactoryInterface $formFactory, Bulker $bulker, string $instanceId)
    {
        $this->dataService = $dataService;
        $this->formFactory = $formFactory;
        $this->doctrine = $doctrine;
        $this->bulker = $bulker;
        $this->instanceId = $instanceId;
    }

    public function initDocumentImporter(ContentType $contentType, string $lockUser, bool $rawImport, bool $signData, bool $indexInDefaultEnv, int $bulkSize, bool $finalize, bool $force) : DocumentImporter
    {
        $entityManager = $this->doctrine->getManager();
        return new DocumentImporter($this->dataService, $entityManager, $this->formFactory, $this->bulker, $this->instanceId, $contentType, $lockUser, $rawImport, $signData, $indexInDefaultEnv, $bulkSize, $finalize, $force);
    }
}
