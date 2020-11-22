<?php

namespace EMS\CoreBundle\Twig;

use Caxy\HtmlDiff\HtmlDiff;
use DateTime;
use Doctrine\Bundle\DoctrineBundle\Registry;
use Elastica\Query\Term;
use Elasticsearch\Client;
use EMS\CommonBundle\Elasticsearch\Exception\NotSingleResultException;
use EMS\CommonBundle\Helper\EmsFields;
use EMS\CommonBundle\Helper\Text\Encoder;
use EMS\CommonBundle\Search\Search as CommonSearch;
use EMS\CommonBundle\Service\ElasticaService;
use EMS\CommonBundle\Storage\Processor\Config;
use EMS\CommonBundle\Twig\RequestRuntime;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Entity\Form\Search;
use EMS\CoreBundle\Entity\I18n;
use EMS\CoreBundle\Entity\UserInterface;
use EMS\CoreBundle\Exception\CantBeFinalizedException;
use EMS\CoreBundle\Exception\ElasticmsException;
use EMS\CoreBundle\Form\DataField\DateFieldType;
use EMS\CoreBundle\Form\DataField\DateRangeFieldType;
use EMS\CoreBundle\Form\DataField\TimeFieldType;
use EMS\CoreBundle\Form\Factory\ObjectChoiceListFactory;
use EMS\CoreBundle\Repository\I18nRepository;
use EMS\CoreBundle\Repository\SequenceRepository;
use EMS\CoreBundle\Service\ContentTypeService;
use EMS\CoreBundle\Service\EnvironmentService;
use EMS\CoreBundle\Service\FileService;
use EMS\CoreBundle\Service\UserService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormFactory;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\Router;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Twig\Environment as TwigEnvironment;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class AppExtension extends AbstractExtension
{
    /** @var FormFactory */
    protected $formFactory;
    /** @var FileService */
    protected $fileService;
    /** @var RequestRuntime */
    protected $commonRequestRuntime;
    /** @var array<mixed> */
    protected $assetConfig;
    /** @var Registry */
    private $doctrine;
    /** @var UserService */
    private $userService;
    /** @var AuthorizationCheckerInterface */
    private $authorizationChecker;
    /** @var ContentTypeService $contentTypeService */
    private $contentTypeService;
    /** @var Client $client */
    private $client;
    /** @var Router $router */
    private $router;
    /** @var TwigEnvironment $twig */
    private $twig;
    /** @var ObjectChoiceListFactory $objectChoiceListFactory */
    private $objectChoiceListFactory;
    /** @var EnvironmentService */
    private $environmentService;
    /** @var LoggerInterface */
    private $logger;
    /** @var \Swift_Mailer */
    private $mailer;
    /** @var ElasticaService */
    private $elasticaService;

    /**
     * @param array<mixed> $assetConfig
     */
    public function __construct(Registry $doctrine, AuthorizationCheckerInterface $authorizationChecker, UserService $userService, ContentTypeService $contentTypeService, Client $client, Router $router, TwigEnvironment $twig, ObjectChoiceListFactory $objectChoiceListFactory, EnvironmentService $environmentService, LoggerInterface $logger, FormFactory $formFactory, FileService $fileService, RequestRuntime $commonRequestRuntime, \Swift_Mailer $mailer, ElasticaService $elasticaService, array $assetConfig)
    {
        $this->doctrine = $doctrine;
        $this->authorizationChecker = $authorizationChecker;
        $this->userService = $userService;
        $this->contentTypeService = $contentTypeService;
        $this->client = $client;
        $this->router = $router;
        $this->twig = $twig;
        $this->objectChoiceListFactory = $objectChoiceListFactory;
        $this->environmentService = $environmentService;
        $this->logger = $logger;
        $this->formFactory = $formFactory;
        $this->fileService = $fileService;
        $this->commonRequestRuntime = $commonRequestRuntime;
        $this->mailer = $mailer;
        $this->elasticaService = $elasticaService;
        $this->assetConfig = $assetConfig;
    }

    /**
     *
     * {@inheritDoc}
     * @see Twig_Extension::getFunctions()
     */
    public function getFunctions()
    {
        return [
            new TwigFunction('get_content_types', array($this, 'getContentTypes')),
            new TwigFunction('cant_be_finalized', array($this, 'cantBeFinalized')),
            new TwigFunction('get_default_environments', array($this, 'getDefaultEnvironments')),
            new TwigFunction('emsco_get_environments', array($this, 'getEnvironments')),
            new TwigFunction('sequence', array($this, 'getSequenceNextValue')),
            new TwigFunction('diff_text', array($this, 'diffText'), ['is_safe' => ['html']]),
            new TwigFunction('diff', array($this, 'diff'), ['is_safe' => ['html']]),
            new TwigFunction('diff_html', array($this, 'diffHtml'), ['is_safe' => ['html']]),
            new TwigFunction('diff_icon', array($this, 'diffIcon'), ['is_safe' => ['html']]),
            new TwigFunction('diff_raw', array($this, 'diffRaw'), ['is_safe' => ['html']]),
            new TwigFunction('diff_color', array($this, 'diffColor'), ['is_safe' => ['html']]),
            new TwigFunction('diff_boolean', array($this, 'diffBoolean'), ['is_safe' => ['html']]),
            new TwigFunction('diff_choice', array($this, 'diffChoice'), ['is_safe' => ['html']]),
            new TwigFunction('diff_data_link', array($this, 'diffDataLink'), ['is_safe' => ['html']]),
            new TwigFunction('diff_date', array($this, 'diffDate'), ['is_safe' => ['html']]),
            new TwigFunction('diff_time', array($this, 'diffTime'), ['is_safe' => ['html']]),
            new TwigFunction('is_super', array($this, 'isSuper')),
            new TwigFunction('emsco_asset_path', [$this, 'assetPath'], ['is_safe' => ['html']]),
            new TwigFunction('call_user_func', array($this, 'callUserFunc')),
            new TwigFunction('emsco_generate_email', array($this, 'generateEmailMessage')),
            new TwigFunction('emsco_send_email', array($this, 'sendEmail')),
        ];
    }

    /**
     *
     * {@inheritDoc}
     * @see Twig_Extension::getFilters()
     */
    public function getFilters()
    {
        return array(
            new TwigFilter('searches', array($this, 'searchesList')),
            new TwigFilter('data', array($this, 'data')),
            new TwigFilter('inArray', array($this, 'inArray')),
            new TwigFilter('firstInArray', array($this, 'firstInArray')),
            new TwigFilter('md5', array($this, 'md5')),
            new TwigFilter('convertJavaDateFormat', array($this, 'convertJavaDateFormat')),
            new TwigFilter('convertJavascriptDateFormat', array($this, 'convertJavascriptDateFormat')),
            new TwigFilter('convertJavascriptDateRangeFormat', array($this, 'convertJavascriptDateRangeFormat')),
            new TwigFilter('getTimeFieldTimeFormat', array($this, 'getTimeFieldTimeFormat')),
            new TwigFilter('soapRequest', array($this, 'soapRequest')),
            new TwigFilter('luma', array($this, 'relativeLuminance')),
            new TwigFilter('contrastratio', array($this, 'contrastRatio')),
            new TwigFilter('all_granted', array($this, 'allGranted')),
            new TwigFilter('one_granted', array($this, 'oneGranted')),
            new TwigFilter('in_my_circles', array($this, 'inMyCircles')),
            new TwigFilter('data_link', [$this, 'dataLink'], ['is_safe' => ['html']]),
            new TwigFilter('data_label', [$this, 'dataLabel'], ['is_safe' => ['html']]),
            new TwigFilter('get_content_type', array($this, 'getContentType')),
            new TwigFilter('get_environment', array($this, 'getEnvironment')),
            new TwigFilter('generate_from_template', array($this, 'generateFromTemplate')),
            new TwigFilter('objectChoiceLoader', array($this, 'objectChoiceLoader')),
            new TwigFilter('groupedObjectLoader', array($this, 'groupedObjectLoader')),
            new TwigFilter('propertyPath', array($this, 'propertyPath')),
            new TwigFilter('is_super', array($this, 'isSuper')),
            new TwigFilter('i18n', array($this, 'i18n')),
            new TwigFilter('internal_links', array($this, 'internalLinks')),
            new TwigFilter('src_path', array($this, 'srcPath')),
            new TwigFilter('get_user', array($this, 'getUser')),
            new TwigFilter('displayname', array($this, 'displayName')),
            new TwigFilter('date_difference', array($this, 'dateDifference')),
            new TwigFilter('debug', array($this, 'debug')),
            new TwigFilter('search', array($this, 'search')),
            new TwigFilter('call_user_func', array($this, 'callUserFunc')),
            new TwigFilter('merge_recursive', array($this, 'arrayMergeRecursive')),
            new TwigFilter('array_intersect', array($this, 'arrayIntersect')),
            new TwigFilter('get_string', array($this, 'getString')),
            new TwigFilter('get_file', array($this, 'getFile')),
            new TwigFilter('get_field_by_path', array($this, 'getFieldByPath')),
            new TwigFilter('json_decode', array($this, 'jsonDecode')),
            new TwigFilter('get_revision_id', [RevisionRuntime::class, 'getRevisionId']),
            //deprecated
            new TwigFilter('url_generator', [Encoder::class, 'webalize'], ['deprecated' => true]),
            new TwigFilter('emsco_webalize', [Encoder::class, 'webalize'], ['deprecated' => true]),
        );
    }

    public function generateEmailMessage(string $title): \Swift_Message
    {
        return (new \Swift_Message($title));
    }

    public function sendEmail(\Swift_Message $message): void
    {
        $this->mailer->send($message);
    }

    /**
     * @param array<mixed> $fileField
     * @param string $processorIdentifier
     * @param array<mixed> $assetConfig
     * @param string $route
     * @param string $fileHashField
     * @param string $filenameField
     * @param string $mimeTypeField
     * @param int $referenceType
     * @return string
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

        //_published_datetime can also be removed as it has a sense only if the default config is updated
        if (isset($config['_published_datetime'])) {
            unset($config['_published_datetime']);
        }

        return $this->commonRequestRuntime->assetPath($fileField, $config, $route, $fileHashField, $filenameField, $mimeTypeField, $referenceType);
    }

    /**
     * @return mixed
     */
    public function jsonDecode(string $json, bool $assoc = true, int $depth = 512, int $options = 0)
    {
        return \json_decode($json, $assoc, $depth, $options);
    }

    public function getFieldByPath(ContentType $contentType, string $path, bool $skipVirtualFields = false): ?FieldType
    {
        $fieldType = $this->contentTypeService->getChildByPath($contentType->getFieldType(), $path, $skipVirtualFields);
        if ($fieldType === false) {
            return null;
        }
        return $fieldType;
    }

    public function getFile(string $hash): ?string
    {
        return $this->fileService->getFile($hash);
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
        $encoded = \json_encode($rawData[$field]);
        if ($encoded === false) {
            throw new \RuntimeException('Failure on json encode');
        }
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
                $htmlDiff = new HtmlDiff(($escape ? htmlentities($b) : $this->internalLinks($b)) ?? '', ($escape ? htmlentities($a) : $this->internalLinks($a)) ?? '');
                $textLabel = $htmlDiff->build();
            } else {
                $textClass = false;
                if ($b !== null) {
                    $textClass = 'text-red';
                    $textLabel .= '<del class="diffmod">' . ($escape ? htmlentities($b) : $this->internalLinks($b)) . '</del>';
                }

                if ($a !== null) {
                    if ($textClass) {
                        $textClass = 'text-orange';
                    } else {
                        $textClass = 'text-green';
                    }
                    $textLabel .= ' <ins class="diffmod">' . ($escape ? htmlentities($a) : $this->internalLinks($a)) . '</ins>';
                }
            }
        } else {
            if ($a !== null) {
                $textLabel = ($escape ? htmlentities($a) : $this->internalLinks($a));
            } else {
//                $textClass = 'text-gray';
//                $textLabel = '[not defined]';
//                $tag = 'span';
                return '<span class="text-gray">[not defined]</span>';
            }
        }

        if ($raw) {
            return $textLabel ?? '';
        }
        return '<' . $tag . ' class="' . $textClass . '">' . $textLabel . '</' . $tag . '>';
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

        return '<span class="' . $textClass . '"><i class="fa fa' . ($a ? '-check' : '') . '-square-o"></i></span>';
    }

    /**
     * @param mixed|null $rawData
     * @param mixed|null $compareRawData
     */
    public function diffIcon($rawData, bool $compare, string $fieldName, $compareRawData): string
    {
        $b = $a = null;
        if ($rawData) {
            $a = '<i class="' . $rawData . '"></i> ' . $rawData;
        }

        if (isset($compareRawData[$fieldName]) && $compareRawData[$fieldName]) {
            $b = '<i class="' . $compareRawData[$fieldName] . '"></i> ' . $compareRawData[$fieldName];
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
        $out = "";
        $tag = 'li';
        $insColor = 'green';
        $delColor = 'red';

        if (isset($compareRawData[$fieldName])) {
            if (is_array($compareRawData[$fieldName])) {
                $b = $compareRawData[$fieldName];
            } elseif (is_scalar($compareRawData[$fieldName])) {
                $b = [$compareRawData[$fieldName]];
            }
        }

        if (is_array($rawData)) {
            $a = $rawData;
        } elseif (is_scalar($rawData)) {
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

            if ($date === false) {
                throw new \RuntimeException('Unexpected date format');
            }

            $value = $date->format($format1);
            $value2 = null;

            if ($internalFormat !== null) {
                $internal = $date->format($internalFormat);
                $formatedA[] = $internal;
                $inArray = in_array($internal, $b);
            } elseif ($format2 !== null) {
                $value2 = $date->format($format2);
                $formatedA[] = $value2;
                $inArray = in_array($item, $b);
            } else {
                $formatedA[] = $value;
                $inArray = in_array($value, $b);
            }

            if ($value2) {
                $value .= ' (' . $value2 . ')';
            }

            if (!$compare || $inArray) {
                $out .= '<' . $tag . ' class="">' . htmlentities($value) . '</' . $tag . '>';
            } else {
                $out .= '<' . $tag . ' class="text-' . $insColor . '"><ins class="diffmod">' . htmlentities($value) . '</ins></' . $tag . '>';
            }
        }

        if ($compare) {
            foreach ($b as $item) {
                if ($item instanceof \DateTime) {
                    $date = $item;
                } elseif ($internalFormat) {
                    $date = \DateTime::createFromFormat($internalFormat, $item);
                } else {
                    $date = new DateTime($item);
                }
                if ($date === false) {
                    throw new \RuntimeException('Unexpected date format');
                }

                $value = $date->format($format1);
                $value2 = null;

                if ($internalFormat !== null) {
                    $internal = $date->format($internalFormat);
                    $inArray = in_array($internal, $formatedA);
                } elseif ($format2 !== null) {
                    $value2 = $date->format($format2);
                    $inArray = in_array($item, $formatedA);
                } else {
                    $inArray = in_array($value, $formatedA);
                }

                if ($value2) {
                    $value .= ' (' . $value2 . ')';
                }

                if (!$inArray) {
                    $out .= ' <' . $tag . ' class="text-' . $delColor . '"><del class="diffmod">' . htmlentities($value) . '</del></' . $tag . '>';
                }
            }
        }


        return $out;
    }

    /**
     * @param mixed|null $rawData
     * @param array<mixed>|null $labels
     * @param array<mixed>|null $choices
     * @param mixed|null $compareRawData
     */
    public function diffChoice($rawData, ?array $labels, ?array $choices, bool $compare, string $fieldName, $compareRawData): string
    {
        $b = $a = [];
        $out = "";
        $tag = 'li';
        $insColor = 'green';
        $delColor = 'red';

        if (isset($compareRawData[$fieldName])) {
            if (is_array($compareRawData[$fieldName])) {
                $b = $compareRawData[$fieldName];
            } elseif (is_scalar($compareRawData[$fieldName])) {
                $b = [$compareRawData[$fieldName]];
            }
        }

        if (is_array($rawData)) {
            $a = $rawData;
        } elseif (is_scalar($rawData)) {
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
                    $idx = array_search($value, $choices, true);
                    if ($idx !== false && \is_array($labels) && \array_key_exists($idx, $labels)) {
                        $value = $labels[$idx] . ' (' . $value . ')';
                    }
                }
                if (!\in_array($item, $a)) {
                    $out .= '<' . $tag . ' class="text-' . $delColor . '"><del class="diffmod">' . \htmlentities($value) . '</del></' . $tag . '>';
                }
            }
        }

        foreach ($a as $item) {
            $value = $item;
            if (\is_array($choices) && \in_array($value, $choices)) {
                $idx = \array_search($value, $choices, true);
                if ($idx !== false && \is_array($labels) && \array_key_exists($idx, $labels)) {
                    $value = $this->isSuper() ? $labels[$idx] . ' (' . $item . ')' : $labels[$idx];
                }
            }
            if (!$compare || in_array($item, $b)) {
                $out .= '<' . $tag . ' class="" data-ems-id="' . $item . '">' . \htmlentities($value) . '</' . $tag . '>';
            } else {
                $out .= '<' . $tag . ' class="text-' . $insColor . '"><ins class="diffmod">' . htmlentities($value) . '</ins></' . $tag . '>';
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
        $out = "";

        if (is_array($rawData)) {
            $a = $rawData;
        } elseif (is_scalar($rawData)) {
            $a = [$rawData];
        }

        if (isset($compareRawData[$fieldName])) {
            if (is_array($compareRawData[$fieldName])) {
                $b = $compareRawData[$fieldName];
            } elseif (is_scalar($compareRawData[$fieldName])) {
                $b = [$compareRawData[$fieldName]];
            }
        }

        if ($compare) {
            foreach ($b as $item) {
                if (!in_array($item, $a)) {
                    $out .= $this->dataLink($item, null, 'del') . ' ';
                }
            }
        }

        foreach ($a as $item) {
            if (!$compare || in_array($item, $b)) {
                $out .= $this->dataLink($item) . ' ';
            } else {
                $out .= $this->dataLink($item, null, 'ins') . ' ';
            }
        }


        return $out;
    }

    /**
     * @param mixed|null $rawData
     * @param mixed|null  $compareRawData
     */
    public function diffColor($rawData, bool $compare, string $fieldName, $compareRawData): string
    {
        $b = $a = null;
        if ($rawData) {
            $color = $rawData;
            $a = '<span style="background-color: ' . $color . '; color: ' . ($this->contrastRatio($color, '#000000') > $this->contrastRatio($color, '#ffffff') ? '#000000' : '#ffffff') . ';">' . $color . '</span> ';
        }

        if (isset($compareRawData[$fieldName]) && $compareRawData[$fieldName]) {
            $color = $compareRawData[$fieldName];
            $b = '<span style="background-color: ' . $color . '; color: ' . ($this->contrastRatio($color, '#000000') > $this->contrastRatio($color, '#ffffff') ? '#000000' : '#ffffff') . ';">' . $color . '</span> ';
        }
        return $this->diff($a, $b, $compare, false, false, true);
    }

    /**
     * @param mixed|null $rawData
     * @param mixed|null $compareRawData
     */
    public function diffRaw($rawData, bool $compare, string $fieldName, $compareRawData): string
    {
        $b = isset($compareRawData[$fieldName]) ? $compareRawData[$fieldName] : null;
        return $this->diff($rawData, $b, $compare);
    }

    /**
     * @param mixed|null $rawData
     * @param mixed|null $compareRawData
     */
    public function diffText($rawData, bool $compare, string $fieldName, $compareRawData): string
    {
        $b = isset($compareRawData[$fieldName]) ? $compareRawData[$fieldName] : null;

        return $this->diff($rawData, $b, $compare, true, true);
    }

    /**
     * @param mixed|null $rawData
     * @param mixed|null $compareRawData
     */
    public function diffHtml($rawData, bool $compare, string $fieldName, $compareRawData): string
    {
        $b = isset($compareRawData[$fieldName]) ? $compareRawData[$fieldName] : null;
        return $this->diff($rawData, $b, $compare, false, true, true);
    }


    public function getSequenceNextValue(string $name): int
    {
        $em = $this->doctrine->getManager();
        $repo = $em->getRepository('EMSCoreBundle:Sequence');
        if (!$repo instanceof SequenceRepository) {
            throw new \RuntimeException('Unexpected repository');
        }
        return $repo->nextValue($name);
    }

    /**
     * @param array<mixed> $array1
     * @param array<mixed> $array2
     * @return array<mixed>
     */
    public function arrayIntersect(array $array1, array $array2): array
    {
        return \array_intersect($array1, $array2);
    }

    /**
     * @param array<mixed> ...$arrays
     * @return array<mixed>
     */
    public function arrayMergeRecursive(array ...$arrays): array
    {
        return \array_merge_recursive($arrays);
    }

    public function cantBeFinalized(string $message = '', int $code = 0, \Throwable $previous = null): void
    {
        throw new CantBeFinalizedException($message, $code, $previous);
    }

    /**
     * @param mixed $function
     * @param mixed ...$parameter
     * @return mixed
     */
    public function callUserFunc($function, ...$parameter)
    {
        return \call_user_func($function, $parameter);
    }

    /**
     * @param array<mixed> $params
     * @return array<mixed>
     */
    public function search(array $params): array
    {
        return $this->client->search($params);
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

        if ($datetime1 === false || $datetime2 === false) {
            throw new \RuntimeException('Unexpected date format');
        }

        $interval = \date_diff($datetime1, $datetime2);
        if ($detailed) {
            return $interval->format('%R%a days %h hours %i minutes');
        }
        return (\intval($interval->format('%R%a')) + 1) . ' days';
    }

    public function getUser(string $username): ?UserInterface
    {
        $user = $this->userService->getUser($username);
        if ($user === null || $user instanceof UserInterface) {
            return $user;
        }
        throw new \RuntimeException('Unexpected user object');
    }

    public function displayName(string $username): string
    {
        /**@var UserInterface $user */
        $user = $this->userService->getUser($username);
        if (!empty($user)) {
            return $user->getDisplayName();
        }
        return $username;
    }

    public function srcPath(string $input, string $fileName = null): ?string
    {
        $path = $this->router->generate('ems_file_view', ['sha1' => '__SHA1__'], UrlGeneratorInterface::ABSOLUTE_PATH);
        $path = substr($path, 0, strlen($path) - 8);
        return preg_replace_callback(
            '/(ems:\/\/asset:)([^\n\r"\'\?]*)/i',
            function ($matches) use ($path, $fileName) {
                if ($fileName) {
                    return $this->fileService->getFile($matches[2]);
                }
                return $path . $matches[2];
            },
            $input
        );
    }

    public function internalLinks(string $input, string $fileName = null): ?string
    {
        $url = $this->router->generate('data.link', ['key' => 'object:'], UrlGeneratorInterface::ABSOLUTE_PATH);
        $out = preg_replace('/ems:\/\/object:/i', $url, $input);

        if ($out === null) {
            throw new \RuntimeException('Unexpected null value');
        }

        return $this->srcPath($out, $fileName);
    }


    public function i18n(string $key, string $locale = null): string
    {
        if (empty($locale)) {
            $locale = $this->router->getContext()->getParameter('_locale');
        }
        /**@var I18nRepository $repo */
        $repo = $this->doctrine->getManager()->getRepository('EMSCoreBundle:I18n');
        $result = $repo->findOneBy([
            'identifier' => $key,
        ]);

        if ($result instanceof I18n) {
            return $result->getContentTextforLocale($locale);
        }
        return $key;
    }

    public function isSuper(): bool
    {
        return $this->authorizationChecker->isGranted('ROLE_SUPER');
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
    public function inMyCircles($circles): bool
    {
        if (\is_array($circles) && count($circles) === 0) {
            return true;
        }

        if ($this->authorizationChecker->isGranted('ROLE_USER_MANAGEMENT')) {
            return true;
        }

        if (\is_array($circles)) {
            $user = $this->userService->getCurrentUser(UserService::DONT_DETACH);
            return \count(\array_intersect($circles, $user->getCircles())) > 0;
        }

        $user = $this->userService->getCurrentUser(UserService::DONT_DETACH);
        return \in_array($circles, $user->getCircles());
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
            $out = "Error in template: " . $e->getMessage();
        }
        return $out;
    }

    public function dataLabel(string $key): string
    {
        $out = $key;
        $exploded = explode(':', $key);
        if (count($exploded) == 2 && strlen($exploded[0]) > 0 && strlen($exploded[1]) > 0) {
            $type = $exploded[0];
            $ouuid = $exploded[1];

            /**@var \EMS\CoreBundle\Entity\ContentType $contentType */
            $contentType = $this->contentTypeService->getByName($type);
            if ($contentType) {
                if ($contentType->getIcon()) {
                    $icon = '<i class="' . $contentType->getIcon() . '"></i>&nbsp;&nbsp;';
                } else {
                    $icon = '<i class="fa fa-book"></i>&nbsp;&nbsp;';
                }

                try {
                    $fields = [];
                    if ($contentType->getLabelField()) {
                        $fields[] = $contentType->getLabelField();
                    }
                    if ($contentType->getColorField()) {
                        $fields[] = $contentType->getColorField();
                    }

                    $index = $this->contentTypeService->getIndex($contentType);

                    $termQuery = new Term();
                    $termQuery->setTerm('_id', $ouuid);
                    $search = new CommonSearch([$index], $termQuery);
                    try {
                        $document = $this->elasticaService->singleSearch($search);
                    } catch (NotSingleResultException $e) {
                        $document = null;
                    }

                    if ($document !== null && $contentType->getLabelField()) {
                        $label = $document->getSource()[$contentType->getLabelField()];
                        if ($label && strlen($label) > 0) {
                            $out = $label;
                        }
                    }
                    $out = $icon . $out;

                    if ($document !== null && $contentType->getColorField() && $document->getSource()[$contentType->getColorField()]) {
                        $color = $document->getSource()[$contentType->getColorField()];
                        $contrasted = $this->contrastRatio($color, '#000000') > $this->contrastRatio($color, '#ffffff') ? '#000000' : '#ffffff';

                        $out = '<span class="" style="color:' . $contrasted . ';">' . $out . '</span>';
                    }
                } catch (\Exception $e) {
                }
            }
        }
        return $out;
    }

    public function dataLink(string $key, string $revisionId = null, string $diffMod = null): string
    {
        $out = $key;
        $exploded = explode(':', $key);
        if (count($exploded) == 2 && strlen($exploded[0]) > 0 && strlen($exploded[1]) > 0) {
            $type = $exploded[0];
            $ouuid = $exploded[1];

            $addAttribute = "";

            /**@var \EMS\CoreBundle\Entity\ContentType $contentType */
            $contentType = $this->contentTypeService->getByName($type);
            if ($contentType) {
                if ($contentType->getIcon()) {
                    $icon = '<i class="' . $contentType->getIcon() . '"></i>&nbsp;&nbsp;';
                } else {
                    $icon = '<i class="fa fa-book"></i>&nbsp;&nbsp;';
                }

                try {
                    $fields = [];
                    if ($contentType->getLabelField()) {
                        $fields[] = $contentType->getLabelField();
                    }
                    if ($contentType->getColorField()) {
                        $fields[] = $contentType->getColorField();
                    }

                    $index = $this->contentTypeService->getIndex($contentType);

                    $termQuery = new Term();
                    $termQuery->setTerm('_id', $ouuid);
                    $search = new CommonSearch([$index], $termQuery);
                    try {
                        $document = $this->elasticaService->singleSearch($search);
                    } catch (NotSingleResultException $e) {
                        $document = null;
                    }

                    if ($document !== null && $contentType->getLabelField()) {
                        $label = $document->getSource()[$contentType->getLabelField()];
                        if ($label && strlen($label) > 0) {
                            $out = $label;
                        }
                    }
                    $out = $icon . $out;

                    if ($document !== null && $contentType->getColorField() && $document->getSource()[$contentType->getColorField()]) {
                        $color = $document->getSource()[$contentType->getColorField()];
                        $contrasted = $this->contrastRatio($color, '#000000') > $this->contrastRatio($color, '#ffffff') ? '#000000' : '#ffffff';

                        $out = '<span class="" style="color:' . $contrasted . ';">' . $out . '</span>';
                        $addAttribute = ' style="background-color: ' . $document->getSource()[$contentType->getColorField()] . ';border-color: ' . $document->getSource()[$contentType->getColorField()] . ';"';
                    }

                    if ($diffMod !== null) {
                        $out = '<' . $diffMod . ' class="diffmod">' . $out . '<' . $diffMod . '>';
                    }
                } catch (\Exception $e) {
                }
            }
            $out = '<a class="btn btn-primary btn-sm" href="' . $this->router->generate('data.revisions', [
                    'type' => $type,
                    'ouuid' => $ouuid,
                    'revisionId' => $revisionId,
                ], UrlGeneratorInterface::RELATIVE_PATH) . '" ' . $addAttribute . ' >' . $out . '</a>';
        }
        return $out;
    }

    public function propertyPath(FormError $error): string
    {
        $parent = $error->getOrigin();
        $out = '';
        while ($parent) {
            $out = $parent->getName() . $out;
            $parent = $parent->getParent();
            if ($parent) {
                $out = '_' . $out;
            }
        }
        return $out;
    }

    /**
     * @return array<mixed>|null
     */
    public function data(string $key, string $index = null): ?array
    {
        if (empty($key)) {
            return null;
        }

        $exploded = explode(':', $key);
        if (count($exploded) === 2) {
            $type = $exploded[0];
            $ouuid = $exploded[1];

            /**@var \EMS\CoreBundle\Entity\ContentType $contentType */
            $contentType = $this->contentTypeService->getByName($type);
            if ($contentType) {
                $singleTypeIndex = $this->contentTypeService->getIndex($contentType);

                $body = [
                    'index' => $index ?? $singleTypeIndex,
                    'body' => [
                        'query' => [
                            'bool' => [
                                'must' => [['term' => ['_id' => $ouuid]]],
                                'minimum_should_match' => 1,
                                'should' => [
                                    ['term' => ['_type' => $type]],
                                    ['term' => ['_contenttype' => $type]],
                                ]

                            ]
                        ]
                    ]

                ];

                $result = $this->client->search($body);
                $total = $result['hits']['total'];

                if (1 === $total) {
                    return $result['hits']['hits'][0]['_source'];
                }

                if ($total > 1) {
                    throw new ElasticmsException(\sprintf('Multiple hits for ems key "%s" (%d) on alias/index "%s"', $key, $total, $index));
                }

                return null; //zero hits
            }
        }
        return null;
    }

    /**
     * @param string[] $roles
     * @param bool $super
     * @return bool
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

    public function relativeLuminance(string $col): float
    {
        $col = trim($col, '#');
        if (strlen($col) == 3) {
            $col = $col[0] . $col[0] . $col[1] . $col[1] . $col[2] . $col[2];
        }
        $components = array(
            'r' => hexdec(substr($col, 0, 2)) / 255,
            'g' => hexdec(substr($col, 2, 2)) / 255,
            'b' => hexdec(substr($col, 4, 2)) / 255
        );
        foreach ($components as $c => $v) {
            if ($v <= 0.03928) {
                $components[$c] = $v / 12.92;
            } else {
                $components[$c] = pow((($v + 0.055) / 1.055), 2.4);
            }
        }
        return ($components['r'] * 0.2126) + ($components['g'] * 0.7152) + ($components['b'] * 0.0722);
    }

    public function contrastRatio(string $c1, string $c2): float
    {
        $y1 = $this->relativeLuminance($c1);
        $y2 = $this->relativeLuminance($c2);
        if ($y1 < $y2) {
            $y3 = $y1;
            $y1 = $y2;
            $y2 = $y3;
        }
        return ($y1 + 0.05) / ($y2 + 0.05);
    }

    public function md5(string $value): string
    {
        return \md5($value);
    }

    /**
     * @return Search[]
     */
    public function searchesList(string $username): array
    {
        $searchRepository = $this->doctrine->getRepository('EMSCoreBundle:Form\Search');
        $searches = $searchRepository->findBy([
            'user' => $username
        ]);
        $out = [];
        foreach ($searches as $search) {
            if (! $search instanceof Search) {
                throw new \RuntimeException('Unexpected class object');
            }
            $out[] = $search;
        }
        return $out;
    }

    /**
     * @deprecated
     * @see https://twig.symfony.com/doc/1.x/functions/dump.html
     */
    public function dump(): void
    {
        trigger_error('dump is now integrated by default in twig 1.5', E_USER_DEPRECATED);
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
     * @param mixed $needle
     * @param array<mixed> $haystack
     */
    public function inArray($needle, array $haystack): bool
    {
        return \array_search($needle, $haystack) !== false;
    }

    /**
     * @param mixed $needle
     * @param array<mixed> $haystack
     */
    public function firstInArray($needle, array $haystack): bool
    {
        return \array_search($needle, $haystack) === 0;
    }

    public function getContentType(string $name): ?ContentType
    {
        $contentType = $this->contentTypeService->getByName($name);
        if ($contentType !== false) {
            return $contentType;
        }
        return null;
    }

    /**
     * @return ContentType[]
     */
    public function getContentTypes(): array
    {
        return $this->contentTypeService->getAll();
    }

    /**
     * @return string[]
     */
    public function getDefaultEnvironments(): array
    {
        $defaultEnvironments = [];
        /**@var Environment $environment */
        foreach ($this->environmentService->getEnvironments() as $environment) {
            if ($environment->getInDefaultSearch()) {
                $defaultEnvironments[] = $environment->getName();
            }
        }
        return $defaultEnvironments;
    }

    /**
     * @return Environment[]
     */
    public function getEnvironments(): array
    {
        return $this->environmentService->getEnvironments();
    }

    public function getEnvironment(string $name): ?Environment
    {
        $environment = $this->environmentService->getAliasByName($name);
        if ($environment !== false) {
            return $environment;
        }
        return null;
    }


    /**
     * @param mixed $wsdl
     * @param array{function: string, options?: array, parameters?: mixed} $arguments
     * @return mixed
     */
    public function soapRequest($wsdl, array $arguments)
    {
        /** @var \SoapClient $soapClient */
        $soapClient = null;
        if (array_key_exists('options', $arguments)) {
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
}
