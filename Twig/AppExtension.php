<?php

namespace EMS\CoreBundle\Twig;

use Caxy\HtmlDiff\HtmlDiff;
use DateTime;
use Doctrine\Bundle\DoctrineBundle\Registry;
use Elasticsearch\Client;
use EMS\CommonBundle\Helper\EmsFields;
use EMS\CommonBundle\Storage\Processor\Config;
use EMS\CommonBundle\Twig\RequestRuntime;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Entity\I18n;
use EMS\CoreBundle\Entity\User;
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
use Monolog\Logger;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormFactory;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\Router;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Twig\TwigFilter;
use Twig\TwigFunction;

class AppExtension extends \Twig_Extension
{
    /**@var FormFactory */
    protected $formFactory;
    /**@var FileService */
    protected $fileService;
    /**@var RequestRuntime */
    protected $commonRequestRuntime;
    /**@var array */
    protected $assetConfig;

    private $doctrine;
    private $userService;
    private $authorizationChecker;
    /**@var ContentTypeService $contentTypeService */
    private $contentTypeService;
    /**@var Client $client */
    private $client;
    /**@var Router $router */
    private $router;
    /**@var \Twig_Environment $twig */
    private $twig;
    /**@var ObjectChoiceListFactory $objectChoiceListFactory */
    private $objectChoiceListFactory;
    /** @var EnvironmentService */
    private $environmentService;
    /** @var Logger */
    private $logger;
    /** @var \Swift_Mailer */
    private $mailer;

    public function __construct(Registry $doctrine, AuthorizationCheckerInterface $authorizationChecker, UserService $userService, ContentTypeService $contentTypeService, Client $client, Router $router, $twig, ObjectChoiceListFactory $objectChoiceListFactory, EnvironmentService $environmentService, Logger $logger, FormFactory $formFactory, FileService $fileService, RequestRuntime $commonRequestRuntime, \Swift_Mailer $mailer, array $assetConfig)
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
            new TwigFilter('url_generator', array($this, 'toAscii')),
            new TwigFilter('data', array($this, 'data')),
            new TwigFilter('inArray', array($this, 'inArray')),
            new TwigFilter('firstInArray', array($this, 'firstInArray')),
            new TwigFilter('md5', array($this, 'md5')),
            new TwigFilter('convertJavaDateFormat', array($this, 'convertJavaDateFormat')),
            new TwigFilter('convertJavascriptDateFormat', array($this, 'convertJavascriptDateFormat')),
            new TwigFilter('convertJavascriptDateRangeFormat', array($this, 'convertJavascriptDateRangeFormat')),
            new TwigFilter('getTimeFieldTimeFormat', array($this, 'getTimeFieldTimeFormat')),
            new TwigFilter('soapRequest', array($this, 'soapRequest')),
            new TwigFilter('luma', array($this, 'relativeluminance')),
            new TwigFilter('contrastratio', array($this, 'contrastratio')),
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
            new TwigFilter('is_super', array($this, 'is_super')),
            new TwigFilter('i18n', array($this, 'i18n')),
            new TwigFilter('internal_links', array($this, 'internalLinks')),
            new TwigFilter('src_path', array($this, 'srcPath')),
            new TwigFilter('get_user', array($this, 'getUser')),
            new TwigFilter('displayname', array($this, 'displayname')),
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

        );
    }

    public function generateEmailMessage(string $title)
    {
        return (new \Swift_Message($title));
    }

    public function sendEmail(\Swift_Message $message)
    {
        $this->mailer->send($message);
    }

    /**
     * @param array $fileField
     * @param string $processorIdentifier
     * @param array $assetConfig
     * @param string $route
     * @param string $fileHashField
     * @param string $filenameField
     * @param string $mimeTypeField
     * @param int $referenceType
     * @return string
     */
    public function assetPath(array $fileField, string $processorIdentifier, array $assetConfig = [], string $route = 'ems_asset', string $fileHashField = EmsFields::CONTENT_FILE_HASH_FIELD, $filenameField = EmsFields::CONTENT_FILE_NAME_FIELD, $mimeTypeField = EmsFields::CONTENT_MIME_TYPE_FIELD, $referenceType = UrlGeneratorInterface::RELATIVE_PATH): string
    {
        $config = $assetConfig;
        if (!isset($config['_config_type'])) {
            $config['_config_type'] = 'image';
        }

        if (isset($this->assetConfig[$processorIdentifier])) {
            $config = array_merge($this->assetConfig[$processorIdentifier], $config);
        }

        // removes invalid options like _sha1, _finalized_by, ..
        $config = array_intersect_key($config, Config::getDefaults());

        //_published_datetime can also be removed as it has a sense only if the default config is updated
        if (isset($config['_published_datetime'])) {
            unset($config['_published_datetime']);
        }

        return $this->commonRequestRuntime->assetPath($fileField, $config, $route, $fileHashField, $filenameField, $mimeTypeField, $referenceType);
    }


    public function jsonDecode($json, $assoc = true, $depth = 512, $options = 0)
    {
        return json_decode($json, $assoc, $depth, $options);
    }


    public function getFieldByPath(ContentType $contentType, $path, $skipVirtualFields = false)
    {
        return $this->contentTypeService->getChildByPath($contentType->getFieldType(), $path, $skipVirtualFields);
    }


    public function getFile($hash, $cacheContext = false)
    {
        return $this->fileService->getFile($hash, $cacheContext);
    }

    public function getString($rawData, $field)
    {
        if (empty($rawData) or !isset($rawData[$field])) {
            return null;
        }
        if (is_string($rawData[$field])) {
            return $rawData[$field];
        }
        return json_encode($rawData[$field]);
    }


    public function diff($a, $b, $compare, $escape = false, $htmlDiff = false, $raw = false)
    {
        $tag = 'span';
        $textClass = '';
        $textLabel = '';

        if ($compare && $a !== $b) {
            if ($htmlDiff && $a && $b) {
                $textClass = 'text-orange';
                $htmlDiff = new HtmlDiff(($escape ? htmlentities($b) : $this->internalLinks($b)), ($escape ? htmlentities($a) : $this->internalLinks($a)));
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
            return $textLabel;
        }
        return '<' . $tag . ' class="' . $textClass . '">' . $textLabel . '</' . $tag . '>';
    }

    public function diffBoolean($rawData, $compare, $fieldName, $compareRawData)
    {
        $a = $rawData ? true : false;
        $b = isset($compareRawData[$fieldName]) && $compareRawData[$fieldName];

        $textClass = '';
        if ($compare && $a !== $b) {
            $textClass = 'text-orange';
        }

        return '<span class="' . $textClass . '"><i class="fa fa' . ($a ? '-check' : '') . '-square-o"></i></span>';
    }

    public function diffIcon($rawData, $compare, $fieldName, $compareRawData)
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


    public function diffTime($rawData, $compare, $fieldName, $compareRawData, $format1, $format2)
    {
        return $this->diffDate($rawData, $compare, $fieldName, $compareRawData, $format1, $format2, TimeFieldType::STOREFORMAT);
    }

    public function diffDate($rawData, $compare, $fieldName, $compareRawData, $format1, $format2 = false, $internalFormat = false)
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
                $date = new DateTime($item);
            }


            $value = $date->format($format1);
            $value2 = false;

            if ($internalFormat) {
                $internal = $date->format($internalFormat);
                $formatedA[] = $internal;
                $inArray = in_array($internal, $b);
            } elseif ($format2) {
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

                $value = $date->format($format1);
                $value2 = false;

                if ($internalFormat) {
                    $internal = $date->format($internalFormat);
                    $inArray = in_array($internal, $formatedA);
                } elseif ($format2) {
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


    public function diffChoice($rawData, $labels, $choices, $compare, $fieldName, $compareRawData)
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
                if (is_array($choices) && in_array($value, $choices)) {
                    $idx = array_search($value, $choices, true);
                    if (is_array($labels) && array_key_exists($idx, $labels)) {
                        $value = $labels[$idx] . ' (' . $value . ')';
                    }
                }
                if (!in_array($item, $a)) {
                    $out .= '<' . $tag . ' class="text-' . $delColor . '"><del class="diffmod">' . htmlentities($value) . '</del></' . $tag . '>';
                }
            }
        }

        foreach ($a as $item) {
            $value = $item;
            if (is_array($choices) && in_array($value, $choices)) {
                $idx = array_search($value, $choices, true);
                if (is_array($labels) && array_key_exists($idx, $labels)) {
                    $value = $this->isSuper() ? $labels[$idx] . ' (' . $item . ')' : $labels[$idx];
                }
            }
            if (!$compare || in_array($item, $b)) {
                $out .= '<' . $tag . ' class="" data-ems-id="' . $item . '">' . htmlentities($value) . '</' . $tag . '>';
            } else {
                $out .= '<' . $tag . ' class="text-' . $insColor . '"><ins class="diffmod">' . htmlentities($value) . '</ins></' . $tag . '>';
            }
        }


        if (empty($out)) {
            $out = '<span class="text-gray">[empty]</span>';
        }

        return $out;
    }


    public function diffDataLink($rawData, $compare, $fieldName, $compareRawData)
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
                    $out .= $this->dataLink($item, false, 'del') . ' ';
                }
            }
        }

        foreach ($a as $item) {
            if (!$compare || in_array($item, $b)) {
                $out .= $this->dataLink($item) . ' ';
            } else {
                $out .= $this->dataLink($item, false, 'ins') . ' ';
            }
        }


        return $out;
    }

    public function diffColor($rawData, $compare, $fieldName, $compareRawData)
    {
        $b = $a = null;
        if ($rawData) {
            $color = $rawData;
            $a = '<span style="background-color: ' . $color . '; color: ' . ($this->contrastratio($color, '#000000') > $this->contrastratio($color, '#ffffff') ? '#000000' : '#ffffff') . ';">' . $color . '</span> ';
        }

        if (isset($compareRawData[$fieldName]) && $compareRawData[$fieldName]) {
            $color = $compareRawData[$fieldName];
            $b = '<span style="background-color: ' . $color . '; color: ' . ($this->contrastratio($color, '#000000') > $this->contrastratio($color, '#ffffff') ? '#000000' : '#ffffff') . ';">' . $color . '</span> ';
        }
        return $this->diff($a, $b, $compare, false, false, true);
    }

    public function diffRaw($rawData, $compare, $fieldName, $compareRawData)
    {
        $b = isset($compareRawData[$fieldName]) ? $compareRawData[$fieldName] : null;
        return $this->diff($rawData, $b, $compare);
    }


    public function diffText($rawData, $compare, $fieldName, $compareRawData)
    {
        $b = isset($compareRawData[$fieldName]) ? $compareRawData[$fieldName] : null;

        return $this->diff($rawData, $b, $compare, true, true);
    }


    public function diffHtml($rawData, $compare, $fieldName, $compareRawData)
    {
        $b = isset($compareRawData[$fieldName]) ? $compareRawData[$fieldName] : null;
        return $this->diff($rawData, $b, $compare, false, true, true);
    }


    /**
     * Return a sequence next value
     * @param string $name
     * @return integer
     */
    public function getSequenceNextValue($name)
    {
        $em = $this->doctrine->getManager();
        /**@var SequenceRepository $repo */
        $repo = $em->getRepository('EMSCoreBundle:Sequence');
        $out = $repo->nextValue($name);
        return $out;
    }

    public function arrayIntersect(array $array1, $array2)
    {
        if (!is_array($array2)) {
            return [];
        }
        return array_intersect($array1, $array2);
    }


    public function arrayMergeRecursive(array $array1, array $_ = null)
    {
        return array_merge_recursive($array1, $_);
    }

    /**
     * Convert a tring into an url frendly string
     * http://cubiq.org/the-perfect-php-clean-url-generator
     *
     * @param string $str
     * @return string
     */
    public function toAscii($str)
    {
        $clean = $str;

        try {
            $clean = iconv('UTF-8', 'ASCII//TRANSLIT', $str);
        } catch (\Exception $e) {
            $clean = false;
        }

        if ($clean === false) {
            $a = array('À', 'Á', 'Â', 'Ã', 'Ä', 'Å', 'Æ', 'Ç', 'È', 'É', 'Ê', 'Ë', 'Ì', 'Í', 'Î', 'Ï', 'Ð', 'Ñ', 'Ò', 'Ó', 'Ô', 'Õ', 'Ö', 'Ø', 'Ù', 'Ú', 'Û', 'Ü', 'Ý', 'ß', 'à', 'á', 'â', 'ã', 'ä', 'å', 'æ', 'ç', 'è', 'é', 'ê', 'ë', 'ì', 'í', 'î', 'ï', 'ñ', 'ò', 'ó', 'ô', 'õ', 'ö', 'ø', 'ù', 'ú', 'û', 'ü', 'ý', 'ÿ', 'Ā', 'ā', 'Ă', 'ă', 'Ą', 'ą', 'Ć', 'ć', 'Ĉ', 'ĉ', 'Ċ', 'ċ', 'Č', 'č', 'Ď', 'ď', 'Đ', 'đ', 'Ē', 'ē', 'Ĕ', 'ĕ', 'Ė', 'ė', 'Ę', 'ę', 'Ě', 'ě', 'Ĝ', 'ĝ', 'Ğ', 'ğ', 'Ġ', 'ġ', 'Ģ', 'ģ', 'Ĥ', 'ĥ', 'Ħ', 'ħ', 'Ĩ', 'ĩ', 'Ī', 'ī', 'Ĭ', 'ĭ', 'Į', 'į', 'İ', 'ı', 'Ĳ', 'ĳ', 'Ĵ', 'ĵ', 'Ķ', 'ķ', 'Ĺ', 'ĺ', 'Ļ', 'ļ', 'Ľ', 'ľ', 'Ŀ', 'ŀ', 'Ł', 'ł', 'Ń', 'ń', 'Ņ', 'ņ', 'Ň', 'ň', 'ŉ', 'Ō', 'ō', 'Ŏ', 'ŏ', 'Ő', 'ő', 'Œ', 'œ', 'Ŕ', 'ŕ', 'Ŗ', 'ŗ', 'Ř', 'ř', 'Ś', 'ś', 'Ŝ', 'ŝ', 'Ş', 'ş', 'Š', 'š', 'Ţ', 'ţ', 'Ť', 'ť', 'Ŧ', 'ŧ', 'Ũ', 'ũ', 'Ū', 'ū', 'Ŭ', 'ŭ', 'Ů', 'ů', 'Ű', 'ű', 'Ų', 'ų', 'Ŵ', 'ŵ', 'Ŷ', 'ŷ', 'Ÿ', 'Ź', 'ź', 'Ż', 'ż', 'Ž', 'ž', 'ſ', 'ƒ', 'Ơ', 'ơ', 'Ư', 'ư', 'Ǎ', 'ǎ', 'Ǐ', 'ǐ', 'Ǒ', 'ǒ', 'Ǔ', 'ǔ', 'Ǖ', 'ǖ', 'Ǘ', 'ǘ', 'Ǚ', 'ǚ', 'Ǜ', 'ǜ', 'Ǻ', 'ǻ', 'Ǽ', 'ǽ', 'Ǿ', 'ǿ');
            $b = array('A', 'A', 'A', 'A', 'A', 'A', 'AE', 'C', 'E', 'E', 'E', 'E', 'I', 'I', 'I', 'I', 'D', 'N', 'O', 'O', 'O', 'O', 'O', 'O', 'U', 'U', 'U', 'U', 'Y', 's', 'a', 'a', 'a', 'a', 'a', 'a', 'ae', 'c', 'e', 'e', 'e', 'e', 'i', 'i', 'i', 'i', 'n', 'o', 'o', 'o', 'o', 'o', 'o', 'u', 'u', 'u', 'u', 'y', 'y', 'A', 'a', 'A', 'a', 'A', 'a', 'C', 'c', 'C', 'c', 'C', 'c', 'C', 'c', 'D', 'd', 'D', 'd', 'E', 'e', 'E', 'e', 'E', 'e', 'E', 'e', 'E', 'e', 'G', 'g', 'G', 'g', 'G', 'g', 'G', 'g', 'H', 'h', 'H', 'h', 'I', 'i', 'I', 'i', 'I', 'i', 'I', 'i', 'I', 'i', 'IJ', 'ij', 'J', 'j', 'K', 'k', 'L', 'l', 'L', 'l', 'L', 'l', 'L', 'l', 'l', 'l', 'N', 'n', 'N', 'n', 'N', 'n', 'n', 'O', 'o', 'O', 'o', 'O', 'o', 'OE', 'oe', 'R', 'r', 'R', 'r', 'R', 'r', 'S', 's', 'S', 's', 'S', 's', 'S', 's', 'T', 't', 'T', 't', 'T', 't', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'W', 'w', 'Y', 'y', 'Y', 'Z', 'z', 'Z', 'z', 'Z', 'z', 's', 'f', 'O', 'o', 'U', 'u', 'A', 'a', 'I', 'i', 'O', 'o', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'A', 'a', 'AE', 'ae', 'O', 'o');
            $clean = str_replace($a, $b, $str);
        }

        $clean = preg_replace("/[^a-zA-Z0-9\_\|\ \-\.]/", '', $clean);
        $clean = strtolower(trim($clean, '-'));
        $clean = preg_replace("/[\/\_\|\ \-]+/", '-', $clean);

        return $clean;
    }

    public function cantBeFinalized($message = null, $code = null, $previous = null)
    {
        throw new CantBeFinalizedException($message, $code, $previous);
    }

    public function callUserFunc($function)
    {
        return call_user_func($function);
    }

    public function search(array $params)
    {
        return $this->client->search($params);
    }

    public function debug($message, array $context = [])
    {
        $context['twig'] = 'twig';
        $this->logger->addDebug($message, $context);
    }

    public function dateDifference($date1, $date2, $detailed = false)
    {
        $datetime1 = date_create($date1);
        $datetime2 = date_create($date2);
        $interval = date_diff($datetime1, $datetime2);
        if ($detailed) {
            return $interval->format('%R%a days %h hours %i minutes');
        }
        return (intval($interval->format('%R%a')) + 1) . ' days';
    }

    public function getUser($username)
    {
        return $this->userService->getUser($username);
    }

    public function displayname($username)
    {
        /**@var User $user */
        $user = $this->userService->getUser($username);
        if (!empty($user)) {
            return $user->getDisplayName();
        }
        return $username;
    }

    public function srcPath($input, $fileName = false)
    {
        $path = $this->router->generate('ems_file_view', ['sha1' => '__SHA1__'], UrlGeneratorInterface::ABSOLUTE_PATH);
        $path = substr($path, 0, strlen($path) - 8);
        $out = preg_replace_callback(
            '/(ems:\/\/asset:)([^\n\r"\'\?]*)/i',
            function ($matches) use ($path, $fileName) {
                if ($fileName) {
                    return $this->fileService->getFile($matches[2]);
                }
                return $path . $matches[2];
            },
            $input
        );

        return $out;
    }

    public function internalLinks($input, $fileName = false)
    {
        $url = $this->router->generate('data.link', ['key' => 'object:'], UrlGeneratorInterface::ABSOLUTE_PATH);
        $out = preg_replace('/ems:\/\/object:/i', $url, $input);

        return $this->srcPath($out, $fileName);
    }


    public function i18n($key, $locale = null)
    {

        if (empty($locale)) {
            $locale = $this->router->getContext()->getParameter('_locale');
        }
        /**@var I18nRepository $repo */
        $repo = $this->doctrine->getManager()->getRepository('EMSCoreBundle:I18n');
        /**@var I18n $result */
        $result = $repo->findOneBy([
            'identifier' => $key,
        ]);

        if (empty($result)) {
            return $key;
        }

        return $result->getContentTextforLocale($locale);
    }

    /**
     * Test if the user has some superpowers
     * @return bool
     */
    public function isSuper()
    {
        return $this->authorizationChecker->isGranted('ROLE_SUPER');
    }

    public function allGranted($roles, $super = false)
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

    public function inMyCircles($circles)
    {

        if (!$circles) {
            return true;
        } else if ($this->authorizationChecker->isGranted('ROLE_ADMIN')) {
            return true;
        } else if (is_array($circles)) {
            if (count($circles) > 0) {
                $user = $this->userService->getCurrentUser(UserService::DONT_DETACH);
                return count(array_intersect($circles, $user->getCircles())) > 0;
            } else {
                return true;
            }
        } else if (is_string($circles)) {
            $user = $this->userService->getCurrentUser(UserService::DONT_DETACH);
            return in_array($circles, $user->getCircles());
        }


        return false;
    }

    public function objectChoiceLoader($contentTypeName)
    {
        return $this->objectChoiceListFactory->createLoader($contentTypeName, true)->loadAll();
    }

    public function groupedObjectLoader($contentTypeName)
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

    public function generateFromTemplate($template, array $params = [])
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

    public function dataLabel($key, $revisionId = false)
    {
        $out = $key;
        $splitted = explode(':', $key);
        if ($splitted && count($splitted) == 2 && strlen($splitted[0]) > 0 && strlen($splitted[1]) > 0) {
            $type = $splitted[0];
            $ouuid = $splitted[1];

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

                    $result = $this->client->get([
                        '_source' => $fields,
                        'id' => $ouuid,
                        'index' => $index,
                        'type' => $type,
                    ]);

                    if ($contentType->getLabelField()) {
                        $label = $result['_source'][$contentType->getLabelField()];
                        if ($label && strlen($label) > 0) {
                            $out = $label;
                        }
                    }
                    $out = $icon . $out;

                    if ($contentType->getColorField() && $result['_source'][$contentType->getColorField()]) {
                        $color = $result['_source'][$contentType->getColorField()];
                        $contrasted = $this->contrastratio($color, '#000000') > $this->contrastratio($color, '#ffffff') ? '#000000' : '#ffffff';

                        $out = '<span class="" style="color:' . $contrasted . ';">' . $out . '</span>';
                        $addAttribute = ' style="background-color: ' . $result['_source'][$contentType->getColorField()] . ';border-color: ' . $result['_source'][$contentType->getColorField()] . ';"';
                    }
                } catch (\Exception $e) {
                }
            }
        }
        return $out;
    }

    public function dataLink($key, $revisionId = false, $diffMod = false)
    {
        $out = $key;
        $splitted = explode(':', $key);
        if ($splitted && count($splitted) == 2 && strlen($splitted[0]) > 0 && strlen($splitted[1]) > 0) {
            $type = $splitted[0];
            $ouuid = $splitted[1];

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

                    $result = $this->client->get([
                        '_source' => $fields,
                        'id' => $ouuid,
                        'index' => $index,
                        'type' => $type,
                    ]);

                    if ($contentType->getLabelField()) {
                        $label = $result['_source'][$contentType->getLabelField()];
                        if ($label && strlen($label) > 0) {
                            $out = $label;
                        }
                    }
                    $out = $icon . $out;

                    if ($contentType->getColorField() && $result['_source'][$contentType->getColorField()]) {
                        $color = $result['_source'][$contentType->getColorField()];
                        $contrasted = $this->contrastratio($color, '#000000') > $this->contrastratio($color, '#ffffff') ? '#000000' : '#ffffff';

                        $out = '<span class="" style="color:' . $contrasted . ';">' . $out . '</span>';
                        $addAttribute = ' style="background-color: ' . $result['_source'][$contentType->getColorField()] . ';border-color: ' . $result['_source'][$contentType->getColorField()] . ';"';
                    }

                    if ($diffMod !== false) {
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

    public function propertyPath(FormError $error)
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

    public function data($key, string $index = null)
    {
        if (empty($key)) {
            return null;
        }

        $splitted = explode(':', $key);
        if ($splitted && count($splitted) == 2) {
            $type = $splitted[0];
            $ouuid = $splitted[1];

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

    public function oneGranted($roles, $super = false)
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

    /**
     * Calculate relative luminance in sRGB colour space for use in WCAG 2.0 compliance
     * @link http://www.w3.org/TR/WCAG20/#relativeluminancedef
     * @param string $col A 3 or 6-digit hex colour string
     * @return float
     * @author Marcus Bointon <marcus@synchromedia.co.uk>
     */
    public function relativeluminance($col)
    {
        //Remove any leading #
        $col = trim($col, '#');
        //Convert 3-digit to 6-digit
        if (strlen($col) == 3) {
            $col = $col[0] . $col[0] . $col[1] . $col[1] . $col[2] . $col[2];
        }
        //Convert hex to 0-1 scale
        $components = array(
            'r' => hexdec(substr($col, 0, 2)) / 255,
            'g' => hexdec(substr($col, 2, 2)) / 255,
            'b' => hexdec(substr($col, 4, 2)) / 255
        );
        //Correct for sRGB
        foreach ($components as $c => $v) {
            if ($v <= 0.03928) {
                $components[$c] = $v / 12.92;
            } else {
                $components[$c] = pow((($v + 0.055) / 1.055), 2.4);
            }
        }
        //Calculate relative luminance using ITU-R BT. 709 coefficients
        return ($components['r'] * 0.2126) + ($components['g'] * 0.7152) + ($components['b'] * 0.0722);
    }

    /**
     * Calculate contrast ratio acording to WCAG 2.0 formula
     * Will return a value between 1 (no contrast) and 21 (max contrast)
     * @link http://www.w3.org/TR/WCAG20/#contrast-ratiodef
     * @param string $c1 A 3 or 6-digit hex colour string
     * @param string $c2 A 3 or 6-digit hex colour string
     * @return float
     * @author Marcus Bointon <marcus@synchromedia.co.uk>
     */
    public function contrastratio($c1, $c2)
    {
        $y1 = $this->relativeluminance($c1);
        $y2 = $this->relativeluminance($c2);
        //Arrange so $y1 is lightest
        if ($y1 < $y2) {
            $y3 = $y1;
            $y1 = $y2;
            $y2 = $y3;
        }
        return ($y1 + 0.05) / ($y2 + 0.05);
    }

    public function md5($value)
    {
        return md5($value);
    }

    public function searchesList($username)
    {
        $searchRepository = $this->doctrine->getRepository('EMSCoreBundle:Form\Search');
        $searches = $searchRepository->findBy([
            'user' => $username
        ]);
        return $searches;
    }

    /**
     * @deprecated
     * @see https://twig.symfony.com/doc/1.x/functions/dump.html
     */
    public function dump($object)
    {
        trigger_error('dump is now integrated by default in twig 1.5', E_USER_DEPRECATED);
    }

    public function convertJavaDateFormat($format)
    {
        return DateFieldType::convertJavaDateFormat($format);
    }

    public function convertJavascriptDateFormat($format)
    {
        return DateFieldType::convertJavascriptDateFormat($format);
    }

    public function convertJavascriptDateRangeFormat($format)
    {
        return DateRangeFieldType::convertJavascriptDateRangeFormat($format);
    }

    public function getTimeFieldTimeFormat($options)
    {
        return TimeFieldType::getFormat($options);
    }

    public function inArray($needle, $haystack)
    {
        return is_int(array_search($needle, $haystack));
    }

    public function firstInArray($needle, $haystack)
    {
        return array_search($needle, $haystack) === 0;
    }

    public function getContentType($name)
    {
        return $this->contentTypeService->getByName($name);
    }

    public function getContentTypes()
    {
        return $this->contentTypeService->getAll();
    }

    public function getDefaultEnvironments()
    {
        $defaultEnvironments = [];
        /**@var Environment $environment */
        foreach ($this->environmentService->getAll() as $environment) {
            if ($environment->getInDefaultSearch()) {
                $defaultEnvironments[] = $environment->getName();
            }
        }
        return $defaultEnvironments;
    }

    public function getEnvironment($name)
    {
        return $this->environmentService->getAliasByName($name);
    }


    /*
     * $arguments should contain 'function' key. Optionally 'options' and/or 'parameters'
     */
    public function soapRequest($wsdl, $arguments = null)
    {
        /** @var \SoapClient $soapClient */
        $soapClient = null;
        if ($arguments && array_key_exists('options', $arguments)) {
            $soapClient = new \SoapClient($wsdl, $arguments['options']);
        } else {
            $soapClient = new \SoapClient($wsdl);
        }

        $function = null;
        if ($arguments && array_key_exists('function', $arguments)) {
            $function = $arguments['function'];
        } else {
            //TODO: throw error "argument 'function' is obligator"
        }

        $response = null;
        if ($arguments && array_key_exists('parameters', $arguments)) {
            $response = $soapClient->$function($arguments['parameters']);
        } else {
            $response = $soapClient->$function();
        }

        return $response;
    }

    public function csvEscaper($twig, $name, $charset)
    {
        return $name;
    }

    public function getName()
    {
        return 'app_extension';
    }
}
