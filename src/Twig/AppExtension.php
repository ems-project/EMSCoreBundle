<?php

namespace EMS\CoreBundle\Twig;

use Caxy\HtmlDiff\HtmlDiff;
use DateTime;
use Doctrine\Bundle\DoctrineBundle\Registry;
use Elastica\ResultSet;
use EMS\CommonBundle\Common\EMSLink;
use EMS\CommonBundle\Elasticsearch\Document\DocumentInterface;
use EMS\CommonBundle\Elasticsearch\Exception\NotFoundException;
use EMS\CommonBundle\Helper\EmsFields;
use EMS\CommonBundle\Helper\Text\Encoder;
use EMS\CommonBundle\Search\Search as CommonSearch;
use EMS\CommonBundle\Service\ElasticaService;
use EMS\CommonBundle\Storage\Processor\Config;
use EMS\CommonBundle\Storage\Service\StorageInterface;
use EMS\CommonBundle\Twig\AssetRuntime;
use EMS\CommonBundle\Twig\RequestRuntime;
use EMS\CoreBundle\Core\ContentType\ContentTypeFields;
use EMS\CoreBundle\Core\ContentType\ContentTypeRoles;
use EMS\CoreBundle\Core\Mail\MailerService;
use EMS\CoreBundle\Core\Revision\Json\JsonMenuRenderer;
use EMS\CoreBundle\Core\Revision\Wysiwyg\WysiwygRuntime;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Entity\Sequence;
use EMS\CoreBundle\Entity\UserInterface;
use EMS\CoreBundle\Exception\CantBeFinalizedException;
use EMS\CoreBundle\Exception\SkipNotificationException;
use EMS\CoreBundle\Form\DataField\DateFieldType;
use EMS\CoreBundle\Form\DataField\DateRangeFieldType;
use EMS\CoreBundle\Form\DataField\TimeFieldType;
use EMS\CoreBundle\Form\Factory\ObjectChoiceListFactory;
use EMS\CoreBundle\Repository\SequenceRepository;
use EMS\CoreBundle\Service\ContentTypeService;
use EMS\CoreBundle\Service\FileService;
use EMS\CoreBundle\Service\Revision\RevisionService;
use EMS\CoreBundle\Service\SearchService;
use EMS\CoreBundle\Service\UserService;
use EMS\Helpers\Standard\Color;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormFactory;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Twig\Environment as TwigEnvironment;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class AppExtension extends AbstractExtension
{
    /**
     * @param array<mixed> $assetConfig
     */
    public function __construct(
        private readonly Registry $doctrine,
        private readonly AuthorizationCheckerInterface $authorizationChecker,
        private readonly UserService $userService,
        private readonly RevisionService $revisionService,
        private readonly ContentTypeService $contentTypeService,
        private readonly RouterInterface $router,
        private readonly TwigEnvironment $twig,
        private readonly ObjectChoiceListFactory $objectChoiceListFactory,
        private readonly LoggerInterface $logger,
        protected FormFactory $formFactory,
        protected FileService $fileService,
        protected RequestRuntime $commonRequestRuntime,
        private readonly MailerService $mailer,
        private readonly ElasticaService $elasticaService,
        private readonly SearchService $searchService,
        private readonly AssetRuntime $assetRuntime,
        protected array $assetConfig
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('emsco_cant_be_finalized', $this->cantBeFinalized(...)),
            new TwigFunction('sequence', $this->getSequenceNextValue(...)),
            new TwigFunction('diff_text', $this->diffText(...), ['is_safe' => ['html']]),
            new TwigFunction('diff', $this->diff(...), ['is_safe' => ['html']]),
            new TwigFunction('diff_html', $this->diffHtml(...), ['is_safe' => ['html']]),
            new TwigFunction('diff_icon', $this->diffIcon(...), ['is_safe' => ['html']]),
            new TwigFunction('diff_raw', $this->diffRaw(...), ['is_safe' => ['html']]),
            new TwigFunction('diff_color', $this->diffColor(...), ['is_safe' => ['html']]),
            new TwigFunction('diff_boolean', $this->diffBoolean(...), ['is_safe' => ['html']]),
            new TwigFunction('diff_choice', $this->diffChoice(...), ['is_safe' => ['html']]),
            new TwigFunction('diff_data_link', $this->diffDataLink(...), ['is_safe' => ['html']]),
            new TwigFunction('diff_date', $this->diffDate(...), ['is_safe' => ['html']]),
            new TwigFunction('diff_time', $this->diffTime(...), ['is_safe' => ['html']]),
            new TwigFunction('is_super', $this->isSuper(...)),
            new TwigFunction('emsco_asset_path', $this->assetPath(...), ['is_safe' => ['html']]),
            new TwigFunction('call_user_func', $this->callUserFunc(...)),
            new TwigFunction('emsco_generate_email', $this->generateEmailMessage(...)),
            new TwigFunction('emsco_send_email', $this->sendEmail(...)),
            new TwigFunction('emsco_users_enabled', [UserRuntime::class, 'getUsersEnabled']),
            new TwigFunction('emsco_uuid', [Uuid::class, 'uuid4'], ['deprecated' => true]),
            new TwigFunction('emsco_datatable', [DatatableRuntime::class, 'generateDatatable'], ['is_safe' => ['html']]),
            new TwigFunction('emsco_datatable_excel_path', [DatatableRuntime::class, 'getExcelPath'], ['is_safe' => ['html']]),
            new TwigFunction('emsco_datatable_csv_path', [DatatableRuntime::class, 'getCsvPath'], ['is_safe' => ['html']]),
            new TwigFunction('emsco_revisions_draft', [RevisionRuntime::class, 'getRevisionsInDraft']),
            new TwigFunction('emsco_revision_create', [RevisionRuntime::class, 'createRevision']),
            new TwigFunction('emsco_revision_update', [RevisionRuntime::class, 'updateRevision']),
            new TwigFunction('emsco_revision_merge', [RevisionRuntime::class, 'mergeRevision']),
            new TwigFunction('emsco_json_menu_nested', [JsonMenuRenderer::class, 'generateNested'], ['is_safe' => ['html']]),
            new TwigFunction('emsco_wysiwyg_info', [WysiwygRuntime::class, 'getInfo']),
            new TwigFunction('emsco_i18n_all', [I18nRuntime::class, 'findAll']),
            new TwigFunction('emsco_get_environments', [EnvironmentRuntime::class, 'getEnvironments']),
            new TwigFunction('emsco_get_environments_revision', [EnvironmentRuntime::class, 'getEnvironmentsRevision']),
            new TwigFunction('emsco_get_default_environment_names', [EnvironmentRuntime::class, 'getDefaultEnvironmentNames']),
            new TwigFunction('emsco_get_content_types', [ContentTypeRuntime::class, 'getContentTypes']),
            new TwigFunction('emsco_get_content_type_version_tags', [ContentTypeRuntime::class, 'getContentTypeVersionTags']),
            new TwigFunction('emsco_skip_notification', $this->skipNotificationException(...), ['is_safe' => ['html']]),
            new TwigFunction('emsco_get_form', [FormRuntime::class, 'getFormByName']),
            new TwigFunction('emsco_form', [FormRuntime::class, 'handleForm']),
            new TwigFunction('emsco_get_data_field', [FormRuntime::class, 'getDataField']),
            new TwigFunction('emsco_save_contents', $this->saveContents(...)),
            // deprecated
            new TwigFunction('cant_be_finalized', $this->cantBeFinalized(...), ['deprecated' => true, 'alternative' => 'emsco_cant_be_finalized']),
            new TwigFunction('get_default_environments', [EnvironmentRuntime::class, 'getDefaultEnvironmentNames'], ['deprecated' => true, 'alternative' => 'emsco_get_default_environment_names']),
            new TwigFunction('get_content_types', [ContentTypeRuntime::class, 'getContentTypes'], ['deprecated' => true, 'alternative' => 'emsco_get_content_types']),
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function getFilters(): array
    {
        return [
            new TwigFilter('inArray', $this->inArray(...)),
            new TwigFilter('firstInArray', $this->firstInArray(...)),
            new TwigFilter('md5', $this->md5(...)),
            new TwigFilter('convertJavaDateFormat', $this->convertJavaDateFormat(...)),
            new TwigFilter('convertJavascriptDateFormat', $this->convertJavascriptDateFormat(...)),
            new TwigFilter('convertJavascriptDateRangeFormat', $this->convertJavascriptDateRangeFormat(...)),
            new TwigFilter('getTimeFieldTimeFormat', $this->getTimeFieldTimeFormat(...)),
            new TwigFilter('soapRequest', $this->soapRequest(...)),
            new TwigFilter('luma', $this->relativeLuminance(...)),
            new TwigFilter('contrastratio', $this->contrastRatio(...)),
            new TwigFilter('all_granted', $this->allGranted(...)),
            new TwigFilter('one_granted', $this->oneGranted(...)),
            new TwigFilter('in_my_circles', $this->inMyCircles(...)),
            new TwigFilter('data_link', $this->dataLink(...), ['is_safe' => ['html']]),
            new TwigFilter('emsco_get_environment', [EnvironmentRuntime::class, 'getEnvironment']),
            new TwigFilter('generate_from_template', $this->generateFromTemplate(...)),
            new TwigFilter('objectChoiceLoader', $this->objectChoiceLoader(...)),
            new TwigFilter('groupedObjectLoader', $this->groupedObjectLoader(...)),
            new TwigFilter('propertyPath', $this->propertyPath(...)),
            new TwigFilter('is_super', $this->isSuper(...)),
            new TwigFilter('i18n', [I18nRuntime::class, 'i18n']),
            new TwigFilter('internal_links', $this->internalLinks(...)),
            new TwigFilter('src_path', $this->srcPath(...)),
            new TwigFilter('get_user', $this->getUser(...)),
            new TwigFilter('displayname', $this->displayName(...)),
            new TwigFilter('date_difference', $this->dateDifference(...)),
            new TwigFilter('debug', $this->debug(...)),
            new TwigFilter('search', $this->deprecatedSearch(...), ['deprecated' => true, 'alternative' => 'emsco_search']),
            new TwigFilter('emsco_search', $this->search(...)),
            new TwigFilter('call_user_func', $this->callUserFunc(...)),
            new TwigFilter('merge_recursive', $this->arrayMergeRecursive(...)),
            new TwigFilter('array_intersect', $this->arrayIntersect(...)),
            new TwigFilter('get_string', $this->getString(...)),
            new TwigFilter('get_file', $this->getFile(...)),
            new TwigFilter('get_field_by_path', $this->getFieldByPath(...)),
            new TwigFilter('json_decode', $this->jsonDecode(...)),
            new TwigFilter('get_revision_id', [RevisionRuntime::class, 'getRevisionId']),
            new TwigFilter('emsco_document_info', [RevisionRuntime::class, 'getDocumentInfo']),
            new TwigFilter('emsco_documents_info', [RevisionRuntime::class, 'getDocumentsInfo']),
            new TwigFilter('emsco_display', [RevisionRuntime::class, 'display']),
            new TwigFilter('emsco_log_notice', [CoreRuntime::class, 'logNotice']),
            new TwigFilter('emsco_log_warning', [CoreRuntime::class, 'logWarning']),
            new TwigFilter('emsco_log_error', [CoreRuntime::class, 'logError']),
            new TwigFilter('emsco_guess_locale', [DataExtractorRuntime::class, 'guessLocale']),
            new TwigFilter('emsco_asset_meta', [DataExtractorRuntime::class, 'assetMeta']),
            new TwigFilter('emsco_get', $this->get(...)),
            new TwigFilter('emsco_get_content_type', [ContentTypeRuntime::class, 'getContentType']),
            // deprecated
            new TwigFilter('data', $this->data(...), ['deprecated' => true, 'alternative' => 'emsco_get']),
            new TwigFilter('url_generator', Encoder::webalize(...), ['deprecated' => true, 'alternative' => 'ems_webalize']),
            new TwigFilter('emsco_webalize', Encoder::webalize(...), ['deprecated' => true, 'alternative' => 'ems_webalize']),
            new TwigFilter('get_environment', [EnvironmentRuntime::class, 'getEnvironment'], ['deprecated' => true, 'alternative' => 'emsco_get_environment']),
            new TwigFilter('get_content_type', [ContentTypeRuntime::class, 'getContentType'], ['deprecated' => true, 'alternative' => 'emsco_get_contentType']),
            new TwigFilter('data_label', $this->dataLabel(...), ['is_safe' => ['html'], 'deprecated' => true, 'alternative' => 'emsco_display']),
        ];
    }

    public function generateEmailMessage(string $title): Email
    {
        $mail = new Email();
        $mail->subject($title);

        return $mail;
    }

    public function sendEmail(Email $email): void
    {
        $this->mailer->sendMail($email);
    }

    /**
     * @param array<mixed> $fileField
     * @param array<mixed> $assetConfig
     * @param int          $referenceType
     */
    public function assetPath(array $fileField, string $processorIdentifier, array $assetConfig = [], string $route = 'ems_asset', string $fileHashField = EmsFields::CONTENT_FILE_HASH_FIELD, string $filenameField = EmsFields::CONTENT_FILE_NAME_FIELD, string $mimeTypeField = EmsFields::CONTENT_MIME_TYPE_FIELD, $referenceType = UrlGeneratorInterface::RELATIVE_PATH): string
    {
        $config = $assetConfig;
        if (!isset($config['_config_type'])) {
            $config['_config_type'] = 'image';
        }

        if (isset($this->assetConfig[$processorIdentifier])) {
            $config = \array_merge($this->assetConfig[$processorIdentifier], $config);
        }

        // removes invalid options like _sha1, _finalized_by, ..
        $config = \array_intersect_key($config, Config::getDefaults());

        // _published_datetime can also be removed as it has a sense only if the default config is updated
        if (isset($config['_published_datetime'])) {
            unset($config['_published_datetime']);
        }

        return $this->commonRequestRuntime->assetPath($fileField, $config, $route, $fileHashField, $filenameField, $mimeTypeField, $referenceType);
    }

    /**
     * @param int<1,512> $depth
     *
     * @return mixed
     */
    public function jsonDecode(string $json, bool $assoc = true, int $depth = 512, int $options = 0)
    {
        return \json_decode($json, $assoc, $depth, $options);
    }

    public function getFieldByPath(ContentType $contentType, string $path, bool $skipVirtualFields = false): ?FieldType
    {
        $fieldType = $this->contentTypeService->getChildByPath($contentType->getFieldType(), $path, $skipVirtualFields);
        if (false === $fieldType) {
            return null;
        }

        return $fieldType;
    }

    public function getFile(string $hash): ?string
    {
        return $this->fileService->getFile($hash);
    }

    /**
     * @return mixed[]
     */
    public function saveContents(string $content, string $filename, string $mimetype, int $usage = StorageInterface::STORAGE_USAGE_ASSET): array
    {
        $hash = $this->fileService->saveContents($content, $filename, $mimetype, $usage);

        return [
            EmsFields::CONTENT_FILE_HASH_FIELD => $hash,
            EmsFields::CONTENT_FILE_SIZE_FIELD => \strlen($content),
            EmsFields::CONTENT_FILE_NAME_FIELD => $filename,
            EmsFields::CONTENT_MIME_TYPE_FIELD => $mimetype,
        ];
    }

    /**
     * @param array<mixed> $rawData
     */
    public function getString(array $rawData, string $field): ?string
    {
        if (empty($rawData) or !isset($rawData[$field])) {
            return null;
        }
        if (\is_string($rawData[$field])) {
            return $rawData[$field];
        }
        $encoded = \json_encode($rawData[$field], JSON_THROW_ON_ERROR);

        return $encoded;
    }

    public function diff(?string $a, ?string $b, bool $compare, bool $escape = false, bool $htmlDiff = false, bool $raw = false): string
    {
        $tag = 'span';
        $textClass = '';
        $textLabel = '';

        if ($compare && $a !== $b) {
            if ($htmlDiff && $a && $b) {
                $textClass = 'text-orange';
                $htmlDiff = new HtmlDiff(($escape ? \htmlentities($b) : $this->internalLinks($b)) ?? '', ($escape ? \htmlentities($a) : $this->internalLinks($a)) ?? '');
                $textLabel = $htmlDiff->build();
            } else {
                $textClass = false;
                if (null !== $b) {
                    $textClass = 'text-red';
                    $textLabel .= '<del class="diffmod">'.($escape ? \htmlentities($b) : $this->internalLinks($b)).'</del>';
                }

                if (null !== $a) {
                    if ($textClass) {
                        $textClass = 'text-orange';
                    } else {
                        $textClass = 'text-green';
                    }
                    $textLabel .= ' <ins class="diffmod">'.($escape ? \htmlentities($a) : $this->internalLinks($a)).'</ins>';
                }
            }
        } else {
            if (null !== $a) {
                $textLabel = ($escape ? \htmlentities($a) : $this->internalLinks($a));
            } else {
                return '<span class="text-gray">[not defined]</span>';
            }
        }

        if ($raw) {
            return $textLabel ?? '';
        }

        return '<'.$tag.' class="'.$textClass.'">'.$textLabel.'</'.$tag.'>';
    }

    /**
     * @param mixed|null $rawData
     * @param mixed|null $compareRawData
     */
    public function diffBoolean($rawData, bool $compare, string $fieldName, $compareRawData): string
    {
        $a = $rawData ? true : false;
        $b = isset($compareRawData[$fieldName]) && $compareRawData[$fieldName];

        $textClass = '';
        if ($compare && $a !== $b) {
            $textClass = 'text-orange';
        }

        return '<span class="'.$textClass.'"><i class="fa fa'.($a ? '-check' : '').'-square-o"></i></span>';
    }

    /**
     * @param mixed|null $rawData
     * @param mixed|null $compareRawData
     */
    public function diffIcon($rawData, bool $compare, string $fieldName, $compareRawData): string
    {
        $b = $a = null;
        if ($rawData) {
            $a = '<i class="'.$rawData.'"></i> '.$rawData;
        }

        if (isset($compareRawData[$fieldName]) && $compareRawData[$fieldName]) {
            $b = '<i class="'.$compareRawData[$fieldName].'"></i> '.$compareRawData[$fieldName];
        }

        return $this->diff($a, $b, $compare);
    }

    /**
     * @param mixed|null $rawData
     * @param mixed|null$compareRawData
     */
    public function diffTime($rawData, bool $compare, string $fieldName, $compareRawData, string $format1, string $format2): string
    {
        return $this->diffDate($rawData, $compare, $fieldName, $compareRawData, $format1, $format2, TimeFieldType::STOREFORMAT);
    }

    /**
     * @param mixed|null $rawData
     * @param mixed|null $compareRawData
     */
    public function diffDate($rawData, bool $compare, string $fieldName, $compareRawData, string $format1, string $format2 = null, string $internalFormat = null): string
    {
        $b = $a = [];
        $out = '';
        $tag = 'li';
        $insColor = 'green';
        $delColor = 'red';

        if (isset($compareRawData[$fieldName])) {
            if (\is_array($compareRawData[$fieldName])) {
                $b = $compareRawData[$fieldName];
            } elseif (\is_scalar($compareRawData[$fieldName])) {
                $b = [$compareRawData[$fieldName]];
            }
        }

        if (\is_array($rawData)) {
            $a = $rawData;
        } elseif (\is_scalar($rawData)) {
            $tag = 'span';
            if (!empty($b)) {
                $insColor = $delColor = 'orange';
            }
            $a = [$rawData];
        }

        $formatedA = [];

        foreach ($a as $item) {
            if ($item instanceof \DateTime) {
                $date = $item;
            } elseif ($internalFormat) {
                $date = \DateTime::createFromFormat($internalFormat, $item);
            } else {
                $date = new \DateTime($item);
            }

            if (false === $date) {
                throw new \RuntimeException('Unexpected date format');
            }

            $value = $date->format($format1);
            $value2 = null;

            if (null !== $internalFormat) {
                $internal = $date->format($internalFormat);
                $formatedA[] = $internal;
                $inArray = \in_array($internal, $b);
            } elseif (null !== $format2) {
                $value2 = $date->format($format2);
                $formatedA[] = $value2;
                $inArray = \in_array($item, $b);
            } else {
                $formatedA[] = $value;
                $inArray = \in_array($value, $b);
            }

            if ($value2) {
                $value .= ' ('.$value2.')';
            }

            if (!$compare || $inArray) {
                $out .= '<'.$tag.' class="">'.\htmlentities($value).'</'.$tag.'>';
            } else {
                $out .= '<'.$tag.' class="text-'.$insColor.'"><ins class="diffmod">'.\htmlentities($value).'</ins></'.$tag.'>';
            }
        }

        if ($compare) {
            foreach ($b as $item) {
                if ($item instanceof \DateTime) {
                    $date = $item;
                } elseif ($internalFormat) {
                    $date = \DateTime::createFromFormat($internalFormat, $item);
                } else {
                    $date = new \DateTime($item);
                }
                if (false === $date) {
                    throw new \RuntimeException('Unexpected date format');
                }

                $value = $date->format($format1);
                $value2 = null;

                if (null !== $internalFormat) {
                    $internal = $date->format($internalFormat);
                    $inArray = \in_array($internal, $formatedA);
                } elseif (null !== $format2) {
                    $value2 = $date->format($format2);
                    $inArray = \in_array($item, $formatedA);
                } else {
                    $inArray = \in_array($value, $formatedA);
                }

                if ($value2) {
                    $value .= ' ('.$value2.')';
                }

                if (!$inArray) {
                    $out .= ' <'.$tag.' class="text-'.$delColor.'"><del class="diffmod">'.\htmlentities($value).'</del></'.$tag.'>';
                }
            }
        }

        return $out;
    }

    /**
     * @param mixed|null        $rawData
     * @param array<mixed>|null $labels
     * @param array<mixed>|null $choices
     * @param mixed|null        $compareRawData
     */
    public function diffChoice($rawData, ?array $labels, ?array $choices, bool $compare, string $fieldName, $compareRawData): string
    {
        $b = $a = [];
        $out = '';
        $tag = 'li';
        $insColor = 'green';
        $delColor = 'red';

        if (isset($compareRawData[$fieldName])) {
            if (\is_array($compareRawData[$fieldName])) {
                $b = $compareRawData[$fieldName];
            } elseif (\is_scalar($compareRawData[$fieldName])) {
                $b = [$compareRawData[$fieldName]];
            }
        }

        if (\is_array($rawData)) {
            $a = $rawData;
        } elseif (\is_scalar($rawData)) {
            $tag = 'span';
            if (!empty($b)) {
                $insColor = $delColor = 'orange';
            }
            $a = [$rawData];
        }

        if ($compare) {
            foreach ($b as $item) {
                $value = $item;
                if (\is_array($choices) && \in_array($value, $choices)) {
                    $idx = \array_search($value, $choices, true);
                    if (false !== $idx && \is_array($labels) && \array_key_exists($idx, $labels)) {
                        $value = $labels[$idx].' ('.$value.')';
                    }
                }
                if (!\in_array($item, $a)) {
                    $out .= '<'.$tag.' class="text-'.$delColor.'"><del class="diffmod">'.\htmlentities((string) $value).'</del></'.$tag.'>';
                }
            }
        }

        foreach ($a as $item) {
            $value = $item;
            if (\is_array($choices) && \in_array($value, $choices)) {
                $idx = \array_search($value, $choices, true);
                if (false !== $idx && \is_array($labels) && \array_key_exists($idx, $labels)) {
                    $value = $this->isSuper() ? $labels[$idx].' ('.$item.')' : $labels[$idx];
                }
            }
            if (!$compare || \in_array($item, $b)) {
                $out .= '<'.$tag.' class="" data-ems-id="'.$item.'">'.\htmlentities((string) $value).'</'.$tag.'>';
            } else {
                $out .= '<'.$tag.' class="text-'.$insColor.'"><ins class="diffmod">'.\htmlentities((string) $value).'</ins></'.$tag.'>';
            }
        }

        if (empty($out)) {
            $out = '<span class="text-gray">[empty]</span>';
        }

        return $out;
    }

    /**
     * @param mixed|null $rawData
     * @param mixed|null $compareRawData
     */
    public function diffDataLink($rawData, bool $compare, string $fieldName, $compareRawData): string
    {
        $b = $a = [];
        $out = '';

        if (\is_array($rawData)) {
            $a = $rawData;
        } elseif (\is_scalar($rawData)) {
            $a = [$rawData];
        }

        if (isset($compareRawData[$fieldName])) {
            if (\is_array($compareRawData[$fieldName])) {
                $b = $compareRawData[$fieldName];
            } elseif (\is_scalar($compareRawData[$fieldName])) {
                $b = [$compareRawData[$fieldName]];
            }
        }

        if ($compare) {
            foreach ($b as $item) {
                if (!\in_array($item, $a)) {
                    $out .= $this->dataLink($item, null, 'del').' ';
                }
            }
        }

        foreach ($a as $item) {
            if (!$compare || \in_array($item, $b)) {
                $out .= $this->dataLink($item).' ';
            } else {
                $out .= $this->dataLink($item, null, 'ins').' ';
            }
        }

        return $out;
    }

    /**
     * @param mixed|null $rawData
     * @param mixed|null $compareRawData
     */
    public function diffColor($rawData, bool $compare, string $fieldName, $compareRawData): string
    {
        $b = $a = null;
        if ($rawData) {
            $color = $rawData;
            $a = '<span style="background-color: '.$color.'; color: '.($this->contrastRatio($color, '#000000') > $this->contrastRatio($color, '#ffffff') ? '#000000' : '#ffffff').';">'.$color.'</span> ';
        }

        if (isset($compareRawData[$fieldName]) && $compareRawData[$fieldName]) {
            $color = $compareRawData[$fieldName];
            $b = '<span style="background-color: '.$color.'; color: '.($this->contrastRatio($color, '#000000') > $this->contrastRatio($color, '#ffffff') ? '#000000' : '#ffffff').';">'.$color.'</span> ';
        }

        return $this->diff($a, $b, $compare, false, false, true);
    }

    /**
     * @param mixed|null $rawData
     * @param mixed|null $compareRawData
     */
    public function diffRaw($rawData, bool $compare, string $fieldName, $compareRawData): string
    {
        $b = $compareRawData[$fieldName] ?? null;

        return $this->diff($rawData, $b, $compare);
    }

    /**
     * @param mixed|null $rawData
     * @param mixed|null $compareRawData
     */
    public function diffText($rawData, bool $compare, string $fieldName, $compareRawData): string
    {
        $b = $compareRawData[$fieldName] ?? null;

        return $this->diff($rawData, $b, $compare, true, true);
    }

    /**
     * @param mixed|null $rawData
     * @param mixed|null $compareRawData
     */
    public function diffHtml($rawData, bool $compare, string $fieldName, $compareRawData): string
    {
        $b = $compareRawData[$fieldName] ?? null;

        return $this->diff($rawData, $b, $compare, false, true, true);
    }

    public function getSequenceNextValue(string $name): int
    {
        $em = $this->doctrine->getManager();
        $repo = $em->getRepository(Sequence::class);
        if (!$repo instanceof SequenceRepository) {
            throw new \RuntimeException('Unexpected repository');
        }

        return $repo->nextValue($name);
    }

    /**
     * @param array<mixed> $array1
     * @param array<mixed> $array2
     *
     * @return array<mixed>
     */
    public function arrayIntersect(array $array1, array $array2): array
    {
        return \array_intersect($array1, $array2);
    }

    /**
     * @param array<mixed> ...$arrays
     *
     * @return array<mixed>
     */
    public function arrayMergeRecursive(array ...$arrays): array
    {
        return \array_merge_recursive(...$arrays);
    }

    public function cantBeFinalized(string $message = '', int $code = 0, \Throwable $previous = null): never
    {
        throw new CantBeFinalizedException($message, $code, $previous);
    }

    /**
     * @return mixed
     */
    public function callUserFunc(mixed $function, mixed ...$parameter)
    {
        return \call_user_func($function, $parameter);
    }

    /**
     * @deprecated
     *
     * @param array<mixed> $params
     *
     * @return array<mixed>
     */
    public function deprecatedSearch(array $params): array
    {
        $search = $this->elasticaService->convertElasticsearchSearch($params);

        return $this->elasticaService->search($search)->getResponse()->getData();
    }

    /**
     * @param string[]          $indexes
     * @param array<mixed>      $body
     * @param string[]          $contentTypes
     * @param array<mixed>|null $sort
     * @param string[]|null     $sources
     */
    public function search(array $indexes, array $body = [], array $contentTypes = [], ?int $size = null, int $from = 0, ?array $sort = null, ?array $sources = null): ResultSet
    {
        $query = $this->elasticaService->filterByContentTypes(null, $contentTypes);

        $boolQuery = $this->elasticaService->getBoolQuery();
        if (!empty($body) && $query instanceof $boolQuery) {
            $query->addMust($body);
        } elseif (!empty($body)) {
            if (null !== $query) {
                $boolQuery->addMust($query);
            }
            $query = $boolQuery;
            $query->addMust($body);
        }
        $search = new CommonSearch($indexes, $query);
        if (null !== $size) {
            $search->setSize($size);
        }
        $search->setFrom($from);
        if (null !== $sort) {
            $search->setSort($sort);
        }
        if (null !== $sources) {
            $search->setSources($sources);
        }

        return $this->elasticaService->search($search);
    }

    /**
     * @param array<mixed> $context
     */
    public function debug(string $message, array $context = []): void
    {
        $context['twig'] = 'twig';
        $this->logger->debug($message, $context);
    }

    public function dateDifference(string $date1, string $date2, bool $detailed = false): string
    {
        $datetime1 = \date_create($date1);
        $datetime2 = \date_create($date2);

        if (false === $datetime1 || false === $datetime2) {
            throw new \RuntimeException('Unexpected date format');
        }

        $interval = \date_diff($datetime1, $datetime2);
        if ($detailed) {
            return $interval->format('%R%a days %h hours %i minutes');
        }

        return (\intval($interval->format('%R%a')) + 1).' days';
    }

    public function getUser(string $username): ?UserInterface
    {
        return $this->userService->getUser($username);
    }

    public function displayName(?string $username): string
    {
        return match ($username) {
            null, '' => 'N/A',
            default => $this->userService->searchUser($username)?->getDisplayName() ?? $username
        };
    }

    public function srcPath(string $input, bool $asFileName = false): ?string
    {
        $path = $this->router->generate('ems_file_view', ['sha1' => '__SHA1__'], UrlGeneratorInterface::ABSOLUTE_PATH);
        $path = \substr($path, 0, \strlen($path) - 8);

        return \preg_replace_callback(
            '/(ems:\/\/asset:)(?P<hash>[^\n\r"\'\?]*)(?:\?(?P<query>(?:[^\n\r"|\']*)))?/i',
            function ($matches) use ($path, $asFileName) {
                if ($asFileName) {
                    return $this->fileService->getFile($matches['hash']) ?? $path.$matches['hash'];
                }

                $parameters = [];
                $query = \html_entity_decode($matches['query'] ?? '');
                \parse_str($query, $parameters);
                if (\is_string($parameters['name'] ?? null) && \is_string($parameters['type'] ?? null)) {
                    return $this->assetRuntime->assetPath([
                        EmsFields::CONTENT_FILE_HASH_FIELD => $matches['hash'],
                        EmsFields::CONTENT_FILE_NAME_FIELD => $parameters['name'],
                        EmsFields::CONTENT_MIME_TYPE_FIELD => $parameters['type'],
                    ], [
                    ],
                        'ems_asset',
                        EmsFields::CONTENT_FILE_HASH_FIELD,
                        EmsFields::CONTENT_FILE_NAME_FIELD,
                        EmsFields::CONTENT_MIME_TYPE_FIELD,
                        UrlGeneratorInterface::ABSOLUTE_PATH);
                }

                return $path.$matches['hash'];
            },
            $input
        );
    }

    public function internalLinks(string $input, bool $asFileName = false): ?string
    {
        $url = $this->router->generate('data.link', ['key' => 'object:'], UrlGeneratorInterface::ABSOLUTE_PATH);
        $out = \preg_replace('/ems:\/\/object:/i', $url, $input);

        if (null === $out) {
            throw new \RuntimeException('Unexpected null value');
        }

        return $this->srcPath($out, $asFileName);
    }

    public function isSuper(): bool
    {
        return $this->userService->isSuper();
    }

    /**
     * @param string[] $roles
     */
    public function allGranted(array $roles, bool $super = false): bool
    {
        if ($super && !$this->isSuper()) {
            return false;
        }
        foreach ($roles as $role) {
            if (!$this->authorizationChecker->isGranted($role)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param string|string[] $circles
     */
    public function inMyCircles(string|array $circles): bool
    {
        return $this->userService->inMyCircles($circles);
    }

    /**
     * @return array<string>
     */
    public function objectChoiceLoader(string $contentTypeName): array
    {
        return $this->objectChoiceListFactory->createLoader($contentTypeName, true)->loadAll();
    }

    /**
     * @return array<int|string, array<int, mixed>>
     */
    public function groupedObjectLoader(string $contentTypeName): array
    {
        $choices = $this->objectChoiceListFactory->createLoader($contentTypeName, true)->loadAll();
        $out = [];
        foreach ($choices as $choice) {
            if (!isset($out[$choice->getGroup()])) {
                $out[$choice->getGroup()] = [];
            }
            $out[$choice->getGroup()][] = $choice;
        }

        return $out;
    }

    /**
     * @param array<mixed> $params
     */
    public function generateFromTemplate(?string $template, array $params = []): ?string
    {
        if (empty($template)) {
            return null;
        }
        try {
            $out = $this->twig->createTemplate($template)->render($params);
        } catch (\Exception $e) {
            $out = 'Error in template: '.$e->getMessage();
        }

        return $out;
    }

    public function dataLabel(string $key): string
    {
        return $this->revisionService->display($key);
    }

    public function dataLink(string $key, string $revisionId = null, string $diffMod = null): string
    {
        $emsLink = EMSLink::fromText($key);
        if (!$emsLink->isValid() || !$contentType = $this->contentTypeService->getByName($emsLink->getContentType())) {
            return $key;
        }

        $label = \sprintf('<i class="%s"></i>', $contentType->getIcon() ?? 'fa fa-book');

        try {
            $document = $this->searchService->getDocument($contentType, $emsLink->getOuuid());
            $emsLink = $document->getEmsLink(); // versioned documents
            $emsSource = $document->getEMSSource();
            $label .= \sprintf('<span>%s</span>', $this->revisionService->display($document));
        } catch (NotFoundException) {
            $label .= \sprintf('<span>%s</span>', $emsLink->getEmsId());
        }

        $color = isset($emsSource) && $contentType->hasColorField() ? $emsSource->get($contentType->giveColorField()) : null;
        if ($color) {
            $contrasted = $this->contrastRatio($color, '#000000') > $this->contrastRatio($color, '#ffffff') ? '#000000' : '#ffffff';
            $label = '<span class="" style="color:'.$contrasted.';">'.$label.'</span>';
        }

        $attributes = [];
        $out = $label;

        if (null !== $diffMod) {
            $out = '<'.$diffMod.' class="diffmod">'.$out.'<'.$diffMod.'>';
        }

        $tooltipField = $contentType->field(ContentTypeFields::TOOLTIP);
        if ($tooltipField && isset($emsSource) && $tooltip = $emsSource->get($tooltipField, false)) {
            $attributes = ['data-toggle="tooltip"', 'data-placement="top"', \sprintf('title="%s"', $tooltip)];
        }

        if (!$this->authorizationChecker->isGranted($contentType->role(ContentTypeRoles::VIEW))) {
            if ($color) {
                $attributes = [...$attributes, \sprintf('style="color: %s"', $color)];
            }

            return \sprintf('<span %s>%s</span>', \implode(' ', $attributes), $out);
        }

        if (isset($color)) {
            $attributes = [...$attributes, 'style="background-color: '.$color.';border-color: '.$color.';"'];
        }

        $link = $this->router->generate('data.revisions', [
            'type' => $emsLink->getContentType(),
            'ouuid' => $emsLink->getOuuid(),
            'revisionId' => $revisionId,
        ]);

        return \vsprintf(
            '<a class="ems-data-link btn btn-primary btn-sm" href="%s" %s>%s</a>',
            [$link, \implode(' ', $attributes), $out]
        );
    }

    public function propertyPath(FormError $error): string
    {
        $parent = $error->getOrigin();
        $out = '';
        while ($parent) {
            $out = $parent->getName().$out;
            $parent = $parent->getParent();
            if ($parent) {
                $out = '_'.$out;
            }
        }

        return $out;
    }

    /**
     * @return array<mixed>|null
     */
    public function data(?string $key): ?array
    {
        return $this->get($key)?->getSource();
    }

    public function get(?string $key, ?Environment $environment = null): ?DocumentInterface
    {
        if (empty($key)) {
            return null;
        }

        $exploded = \explode(':', $key);
        if (2 !== \count($exploded)) {
            return null;
        }
        $type = $exploded[0];
        $ouuid = $exploded[1];

        $contentType = $this->contentTypeService->getByName($type);
        if (!$contentType instanceof ContentType) {
            return null;
        }

        try {
            return $this->searchService->getDocument($contentType, $ouuid, $environment);
        } catch (NotFoundException) {
            return null;
        }
    }

    /**
     * @param string[] $roles
     */
    public function oneGranted(array $roles, bool $super = false): bool
    {
        if ($super && !$this->isSuper()) {
            return false;
        }
        foreach ($roles as $role) {
            if ($this->authorizationChecker->isGranted($role)) {
                return true;
            }
        }

        return false;
    }

    public function relativeLuminance(string $rgb): float
    {
        $color = new Color($rgb);

        return $color->relativeLuminance();
    }

    public function contrastRatio(string $c1, string $c2): float
    {
        $color1 = new Color($c1);
        $color2 = new Color($c2);

        return $color1->contrastRatio($color2);
    }

    public function md5(string $value): string
    {
        return \md5($value);
    }

    /**
     * @deprecated
     * @see https://twig.symfony.com/doc/1.x/functions/dump.html
     */
    public function dump(): void
    {
        \trigger_error('dump is now integrated by default in twig 1.5', E_USER_DEPRECATED);
    }

    public function convertJavaDateFormat(string $format): string
    {
        return DateFieldType::convertJavaDateFormat($format);
    }

    public function convertJavascriptDateFormat(string $format): string
    {
        return DateFieldType::convertJavascriptDateFormat($format);
    }

    public function convertJavascriptDateRangeFormat(string $format): string
    {
        return DateRangeFieldType::convertJavascriptDateRangeFormat($format);
    }

    /**
     * @param array<array<string>> $options
     */
    public function getTimeFieldTimeFormat(array $options): string
    {
        return TimeFieldType::getFormat($options);
    }

    /**
     * @param array<mixed> $haystack
     */
    public function inArray(mixed $needle, array $haystack): bool
    {
        return false !== \array_search($needle, $haystack);
    }

    /**
     * @param array<mixed> $haystack
     */
    public function firstInArray(mixed $needle, array $haystack): bool
    {
        return 0 === \array_search($needle, $haystack);
    }

    /**
     * @param array{function: string, options?: array<mixed>, parameters?: mixed} $arguments
     *
     * @return mixed
     */
    public function soapRequest(mixed $wsdl, array $arguments)
    {
        /** @var \SoapClient $soapClient */
        $soapClient = null;
        if (\array_key_exists('options', $arguments)) {
            $soapClient = new \SoapClient($wsdl, $arguments['options']);
        } else {
            $soapClient = new \SoapClient($wsdl);
        }

        $function = $arguments['function'];

        if (\array_key_exists('parameters', $arguments)) {
            return $soapClient->$function($arguments['parameters']);
        }

        return $soapClient->$function();
    }

    public function csvEscaper(string $twig, string $name, string $charset): string
    {
        return $name;
    }

    public function getName(): string
    {
        return 'app_extension';
    }

    public function skipNotificationException(string $message = 'This notification has been skipped'): never
    {
        throw new SkipNotificationException($message);
    }
}
