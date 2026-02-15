<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Twig;

use Caxy\HtmlDiff\HtmlDiff;
use Doctrine\Bundle\DoctrineBundle\Registry;
use Elastica\ResultSet;
use EMS\CommonBundle\Common\EMSLink;
use EMS\CommonBundle\Elasticsearch\Document\DocumentInterface;
use EMS\CommonBundle\Elasticsearch\Exception\NotFoundException;
use EMS\CommonBundle\Helper\EmsFields;
use EMS\CommonBundle\Search\Search as CommonSearch;
use EMS\CommonBundle\Service\ElasticaService;
use EMS\CommonBundle\Storage\Processor\Config;
use EMS\CommonBundle\Storage\Service\StorageInterface;
use EMS\CommonBundle\Twig\AssetExtension;
use EMS\CoreBundle\Core\ContentType\ContentTypeFields;
use EMS\CoreBundle\Core\ContentType\ContentTypeRoles;
use EMS\CoreBundle\Core\Mail\MailerService;
use EMS\CoreBundle\Core\Revision\Json\JsonMenuRenderer;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Entity\Sequence;
use EMS\CoreBundle\Event\DispatchToWebhookEvent;
use EMS\CoreBundle\Exception\CantBeFinalizedException;
use EMS\CoreBundle\Exception\SkipNotificationException;
use EMS\CoreBundle\Form\DataField\DateRangeFieldType;
use EMS\CoreBundle\Form\DataField\TimeFieldType;
use EMS\CoreBundle\Form\Factory\ObjectChoiceListFactory;
use EMS\CoreBundle\Repository\SequenceRepository;
use EMS\CoreBundle\Roles;
use EMS\CoreBundle\Routes;
use EMS\CoreBundle\Service\ContentTypeService;
use EMS\CoreBundle\Service\FileService;
use EMS\CoreBundle\Service\Revision\RevisionService;
use EMS\CoreBundle\Service\SearchService;
use EMS\Helpers\Standard\Color;
use EMS\Helpers\Standard\DateTime;
use EMS\Helpers\Standard\Json;
use EMS\Helpers\Standard\Type;
use Psr\Log\LoggerInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Twig\Attribute\AsTwigFilter;
use Twig\Attribute\AsTwigFunction;
use Twig\Environment as TwigEnvironment;

readonly class CoreExtension
{
    /** @param array<mixed> $assetConfig */
    public function __construct(
        private MailerService $mailer,
        private JsonMenuRenderer $jsonMenuRenderer,
        private LoggerInterface $logger,
        private EventDispatcherInterface $dispatcher,
        private Registry $doctrine,
        private AuthorizationCheckerInterface $authorizationChecker,
        private RevisionService $revisionService,
        private ContentTypeService $contentTypeService,
        private RouterInterface $router,
        private TwigEnvironment $twig,
        private ObjectChoiceListFactory $objectChoiceListFactory,
        private FileService $fileService,
        private ElasticaService $elasticaService,
        private SearchService $searchService,
        private AssetExtension $assetExtension,
        private array $assetConfig,
    ) {
    }

    #[AsTwigFilter(name: 'emsco_property_path')]
    public static function propertyPath(FormError $error): string
    {
        $parent = $error->getOrigin();
        $out = '';
        while ($parent) {
            $out = $parent->getName().$out;
            $parent = $parent->getParent();
            if ($parent instanceof FormInterface) {
                $out = '_'.$out;
            }
        }

        return $out;
    }

    /**
     * @param array<mixed> $fileField
     * @param array<mixed> $assetConfig
     * @param int          $referenceType
     */
    #[AsTwigFunction(name: 'emsco_asset_path', isSafe: ['html'])]
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

        return $this->assetExtension->assetPath($fileField, $assetConfig, $route, $fileHashField, $filenameField, $mimeTypeField, $referenceType);
    }

    #[AsTwigFilter(name: 'emsco_call_user_func')]
    #[AsTwigFunction(name: 'emsco_call_user_func')]
    public function callUserFunc(mixed $function, mixed ...$parameter): mixed
    {
        return \call_user_func($function, $parameter);
    }

    #[AsTwigFunction(name: 'emsco_cant_be_finalized')]
    public function cantBeFinalized(string $message = '', int $code = 0, ?\Throwable $previous = null): never
    {
        throw new CantBeFinalizedException($message, $code, $previous);
    }

    #[AsTwigFilter(name: 'emsco_convert_javascript_date_range_format')]
    public function convertJavascriptDateRangeFormat(string $format): string
    {
        return DateRangeFieldType::convertJavascriptDateRangeFormat($format);
    }

    #[AsTwigFilter(name: 'emsco_convert_java_date_format')]
    public function covertJavaDateFormat(string $format): string
    {
        return DateTime::convertFormat('java', $format);
    }

    #[AsTwigFilter(name: 'emsco_convert_javascript_date_format')]
    public function covertJavascriptDateFormat(string $format): string
    {
        return DateTime::convertFormat('js', $format);
    }

    #[AsTwigFilter(name: 'emsco_data_link', isSafe: ['html'])]
    public function dataLink(string $key, ?string $revisionId = null, ?string $diffMod = null): string
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
            $label .= \sprintf('<span>%s</span>', \htmlentities($this->revisionService->display($document)));
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

        $link = $this->router->generate('emsco_view_revisions', [
            'type' => $emsLink->getContentType(),
            'ouuid' => $emsLink->getOuuid(),
            'revisionId' => $revisionId,
        ]);

        return \vsprintf(
            '<a class="ems-data-link btn btn-primary btn-sm" href="%s" %s>%s</a>',
            [$link, \implode(' ', $attributes), $out]
        );
    }

    #[AsTwigFilter(name: 'emsco_date_difference')]
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

        return ((int) $interval->format('%R%a') + 1).' days';
    }

    #[AsTwigFunction(name: 'emsco_diff', isSafe: ['html'])]
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
                    $textClass = $textClass ? 'text-orange' : 'text-green';
                    $textLabel .= ' <ins class="diffmod">'.($escape ? \htmlentities($a) : $this->internalLinks($a)).'</ins>';
                }
            }
        } elseif (null !== $a) {
            $textLabel = ($escape ? \htmlentities($a) : $this->internalLinks($a));
        } else {
            return '<span class="text-gray">[not defined]</span>';
        }

        if ($raw) {
            return $textLabel ?? '';
        }

        return '<'.$tag.' class="'.$textClass.'">'.$textLabel.'</'.$tag.'>';
    }

    /**
     * @param ?array<mixed> $compareRawData
     */
    #[AsTwigFunction(name: 'emsco_diff_boolean', isSafe: ['html'])]
    public function diffBoolean(mixed $rawData, bool $compare, string $fieldName, ?array $compareRawData): string
    {
        $a = (bool) $rawData;
        $b = isset($compareRawData[$fieldName]) && $compareRawData[$fieldName];

        $textClass = '';
        if ($compare && $a !== $b) {
            $textClass = 'text-orange';
        }

        return '<span class="'.$textClass.'"><i class="fa fa'.($a ? '-check' : '').'-square-o"></i></span>';
    }

    /**
     * @param ?array<mixed> $labels
     * @param ?array<mixed> $choices
     * @param ?array<mixed> $compareRawData
     */
    #[AsTwigFunction(name: 'emsco_diff_choice', isSafe: ['html'])]
    public function diffChoice(mixed $rawData, ?array $labels, ?array $choices, bool $compare, string $fieldName, ?array $compareRawData): string
    {
        $b = [];
        $a = [];
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
            if ([] !== $b) {
                $insColor = 'orange';
                $delColor = 'orange';
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
                    $value = $this->authorizationChecker->isGranted(Roles::ROLE_SUPER) ? $labels[$idx].' ('.$item.')' : $labels[$idx];
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
     * @param ?array<mixed> $compareRawData
     */
    #[AsTwigFunction(name: 'emsco_diff_color', isSafe: ['html'])]
    public function diffColor(?string $rawData, bool $compare, string $fieldName, ?array $compareRawData): string
    {
        $b = null;
        $a = null;
        if (null !== $rawData) {
            $color = new Color($rawData);
            $a = \sprintf('<span style="background-color: %s; color: %s;">%s</span> ', $rawData, $color->bestContrast(...Color::EMS_COLORS)->getRGB(), $rawData);
        }

        $compareData = Type::getAsNullableString($compareRawData[$fieldName] ?? null);
        if (null !== $compareData) {
            $color = new Color($compareData);
            $b = \sprintf('<span style="background-color: %s; color: %s;">%s</span> ', $compareData, $color->bestContrast(...Color::EMS_COLORS)->getRGB(), $compareData);
        }

        return $this->diff($a, $b, $compare, false, false, true);
    }

    /**
     * @param ?array<mixed> $compareRawData
     */
    #[AsTwigFunction(name: 'emsco_diff_data_link', isSafe: ['html'])]
    public function diffDataLink(mixed $rawData, bool $compare, string $fieldName, ?array $compareRawData): string
    {
        $b = [];
        $a = [];
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
     * @param ?array<mixed> $compareRawData
     */
    #[AsTwigFunction(name: 'emsco_diff_date', isSafe: ['html'])]
    public function diffDate(mixed $rawData, bool $compare, string $fieldName, ?array $compareRawData, string $format1, ?string $format2 = null, ?string $internalFormat = null): string
    {
        $b = [];
        $a = [];
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
            if ([] !== $b) {
                $insColor = 'orange';
                $delColor = 'orange';
            }
            $a = [$rawData];
        }

        $formatedA = [];

        foreach ($a as $item) {
            try {
                if ($item instanceof \DateTime) {
                    $date = $item;
                } elseif ($internalFormat) {
                    $date = \DateTime::createFromFormat($internalFormat, $item);
                } else {
                    $date = new \DateTime($item);
                }
            } catch (\Throwable) {
                $date = null;
            }

            if (!$date instanceof \DateTimeInterface) {
                $out .= '<'.$tag.' class="text-red">'.\htmlentities((string) $item).'</'.$tag.'>';
                continue;
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
     * @param ?array<mixed> $compareRawData
     */
    #[AsTwigFunction(name: 'emsco_diff_html', isSafe: ['html'])]
    public function diffHtml(mixed $rawData, bool $compare, string $fieldName, ?array $compareRawData): string
    {
        $b = Type::getAsNullableString($compareRawData[$fieldName] ?? null);

        return $this->diff(Type::getAsNullableString($rawData), $b, $compare, false, true, true);
    }

    /**
     * @param ?array<mixed> $compareRawData
     */
    #[AsTwigFunction(name: 'emsco_diff_icon', isSafe: ['html'])]
    public function diffIcon(?string $rawData, bool $compare, string $fieldName, ?array $compareRawData): string
    {
        $b = null;
        $a = null;
        if (null !== $rawData) {
            $a = \sprintf('<i class="%s"></i> %s', $rawData, $rawData);
        }

        $compareData = Type::getAsNullableString($compareRawData[$fieldName] ?? null);
        if (null !== $compareData) {
            $b = \sprintf('<i class="%s"></i> %s', $compareData, $compareData);
        }

        return $this->diff($a, $b, $compare);
    }

    /**
     * @param ?array<mixed> $compareRawData
     */
    #[AsTwigFunction(name: 'emsco_diff_raw', isSafe: ['html'])]
    public function diffRaw(mixed $rawData, bool $compare, string $fieldName, ?array $compareRawData): string
    {
        if (\is_array($rawData)) {
            $a = Json::encode($rawData);
        } else {
            $a = Type::getAsNullableString($rawData);
        }
        $b = $compareRawData[$fieldName] ?? null;
        $b = \is_array($b) ? Json::encode($b) : Type::getAsNullableString($b);

        return $this->diff($a, $b, $compare);
    }

    /**
     * @param ?array<mixed> $compareRawData
     */
    #[AsTwigFunction(name: 'emsco_diff_text', isSafe: ['html'])]
    public function diffText(mixed $rawData, bool $compare, string $fieldName, ?array $compareRawData): string
    {
        $b = Type::getAsNullableString($compareRawData[$fieldName] ?? null);

        return $this->diff(Type::getAsNullableString($rawData), $b, $compare, true, true);
    }

    /**
     * @param ?array<mixed> $compareRawData
     */
    #[AsTwigFunction(name: 'emsco_diff_time', isSafe: ['html'])]
    public function diffTime(mixed $rawData, bool $compare, string $fieldName, ?array $compareRawData, string $format1, string $format2): string
    {
        return $this->diffDate($rawData, $compare, $fieldName, $compareRawData, $format1, $format2, TimeFieldType::STOREFORMAT);
    }

    /**
     * @param mixed[] $data
     */
    #[AsTwigFunction(name: 'emsco_webhook')]
    public function dispatchWebhook(string $eventName, array $data = []): void
    {
        $this->dispatcher->dispatch(new DispatchToWebhookEvent($eventName, $data));
    }

    #[AsTwigFunction(name: 'emsco_generate_email')]
    public function emailGenerate(string $title): Email
    {
        $mail = new Email();
        $mail->subject($title);

        return $mail;
    }

    #[AsTwigFunction(name: 'emsco_send_email')]
    public function emailSend(Email $email): void
    {
        $this->mailer->sendMail($email);
    }

    /**
     * @param array<mixed> $params
     */
    #[AsTwigFilter(name: 'emsco_generate_from_template')]
    public function generateFromTemplate(?string $template, array $params = []): ?string
    {
        if (empty($template)) {
            return null;
        }
        try {
            $out = $this->twig->createTemplate($template)->render($params);
        } catch (\Exception $exception) {
            $out = 'Error in template: '.$exception->getMessage();
        }

        return $out;
    }

    /**
     * @param array<mixed> $options
     */
    #[AsTwigFunction(name: 'emsco_json_menu_nested', isSafe: ['html'])]
    public function generateNested(array $options, string $type = JsonMenuRenderer::TYPE_VIEW): string
    {
        return $this->jsonMenuRenderer->generateNested($options, $type);
    }

    #[AsTwigFilter(name: 'emsco_get')]
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

    #[AsTwigFilter(name: 'emsco_get_field_by_path')]
    public function getFieldByPath(ContentType $contentType, string $path, bool $skipVirtualFields = false): ?FieldType
    {
        $fieldType = $this->contentTypeService->getChildByPath($contentType->getFieldType(), $path, $skipVirtualFields);
        if (false === $fieldType) {
            return null;
        }

        return $fieldType;
    }

    #[AsTwigFilter(name: 'emsco_get_file')]
    public function getFile(string $hash): ?string
    {
        return $this->fileService->getFile($hash);
    }

    #[AsTwigFunction(name: 'emsco_sequence')]
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
     * @param array<mixed> $rawData
     */
    #[AsTwigFilter(name: 'emsco_get_string')]
    public function getString(array $rawData, string $field): ?string
    {
        if ([] === $rawData || !isset($rawData[$field])) {
            return null;
        }
        if (\is_string($rawData[$field])) {
            return $rawData[$field];
        }

        return Json::encode($rawData[$field]);
    }

    /**
     * @param array<array<string>> $options
     */
    #[AsTwigFilter(name: 'emsco_time_field_time_format')]
    public function getTimeFieldTimeFormat(array $options): string
    {
        return TimeFieldType::getFormat($options);
    }

    /**
     * @return array<int|string, array<int, mixed>>
     */
    #[AsTwigFilter(name: 'emsco_grouped_object_loader')]
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

    #[AsTwigFilter(name: 'emsco_internal_links')]
    public function internalLinks(string $input, bool $asFileName = false): ?string
    {
        $url = $this->router->generate(Routes::DATA_LINK, ['key' => 'object:'], UrlGeneratorInterface::ABSOLUTE_PATH);
        $out = \preg_replace('/ems:\/\/object:/i', $url, $input);

        if (null === $out) {
            throw new \RuntimeException('Unexpected null value');
        }

        return $this->srcPath($out, $asFileName);
    }

    /**
     * @param array<mixed> $context
     */
    #[AsTwigFilter(name: 'emsco_debug')]
    public function logDebug(string $message, array $context = []): void
    {
        $context['twig'] = 'twig';
        $this->logger->debug($message, $context);
    }

    /**
     * @param array<mixed> $context
     */
    #[AsTwigFilter(name: 'emsco_log_error')]
    #[AsTwigFunction(name: 'emsco_error')]
    public function logError(string $error, array $context = []): void
    {
        $this->logger->error($error, $context);
    }

    /**
     * @param array<mixed> $context
     */
    #[AsTwigFilter(name: 'emsco_log_notice')]
    #[AsTwigFunction(name: 'emsco_notice')]
    public function logNotice(string $notice, array $context = []): void
    {
        $this->logger->notice($notice, $context);
    }

    /**
     * @param array<mixed> $context
     */
    #[AsTwigFilter(name: 'emsco_log_warning')]
    #[AsTwigFunction(name: 'emsco_warning')]
    public function logWarning(string $warning, array $context = []): void
    {
        $this->logger->warning($warning, $context);
    }

    /**
     * @return array<string>
     */
    #[AsTwigFilter(name: 'emsco_object_choice_loader')]
    public function objectChoiceLoader(string $contentTypeName): array
    {
        return $this->objectChoiceListFactory->createLoader($contentTypeName, true)->loadAll();
    }

    /**
     * @return mixed[]
     */
    #[AsTwigFunction(name: 'emsco_save_contents')]
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
     * @param string|string[]     $indexes
     * @param string|array<mixed> $body
     * @param string|list<string> $contentTypes
     * @param array<mixed>|null   $sort
     * @param string[]|null       $sources
     */
    #[AsTwigFunction(name: 'emsco_search')]
    #[AsTwigFilter(name: 'emsco_search')]
    public function search(string|array $indexes, string|array $body = [], string|array $contentTypes = [], ?int $size = null, int $from = 0, ?array $sort = null, ?array $sources = null): ResultSet
    {
        if (\is_string($contentTypes)) {
            $contentTypes = [$contentTypes];
        }
        $query = $this->elasticaService->filterByContentTypes(null, $contentTypes);

        if (\is_string($body)) {
            $body = Json::decode($body);
        }
        $boolQuery = $this->elasticaService->getBoolQuery();
        if ([] !== $body && $query instanceof $boolQuery) {
            $query->addMust($body);
        } elseif ([] !== $body) {
            if (null !== $query) {
                $boolQuery->addMust($query);
            }
            $query = $boolQuery;
            $query->addMust($body);
        }
        $search = new CommonSearch(\is_array($indexes) ? $indexes : [$indexes], $query);
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
     * @param array<mixed> $params
     *
     * @return array<mixed>
     */
    #[AsTwigFilter(name: 'emsco_search_query')]
    public function searchQuery(array $params): array
    {
        $search = $this->elasticaService->convertElasticsearchSearch($params);

        return $this->elasticaService->search($search)->getResponse()->getData();
    }

    #[AsTwigFunction(name: 'emsco_skip_notification', isSafe: ['html'])]
    public function skipNotificationException(string $message = 'This notification has been skipped'): never
    {
        throw new SkipNotificationException($message);
    }

    /**
     * @param array{function: string, options?: array<mixed>, parameters?: mixed} $arguments
     */
    #[AsTwigFilter(name: 'emsco_soap_request')]
    public function soapRequest(mixed $wsdl, array $arguments): mixed
    {
        $soapClient = new \SoapClient($wsdl, $arguments['options'] ?? []);
        $function = $arguments['function'];

        if (\array_key_exists('parameters', $arguments)) {
            return $soapClient->$function($arguments['parameters']);
        }

        return $soapClient->$function();
    }

    #[AsTwigFilter(name: 'emsco_src_path')]
    public function srcPath(string $input, bool $asFileName = false): ?string
    {
        $path = $this->router->generate('ems_file_view', ['sha1' => '__SHA1__'], UrlGeneratorInterface::ABSOLUTE_PATH);
        $path = \substr($path, 0, \strlen($path) - 8);

        $out = \preg_replace_callback(
            '/(ems:\/\/asset:)(?P<hash>[^\n\r"\'\?]*)(?:\?(?P<query>(?:[^\n\r"|\']*)))?/i',
            function ($matches) use ($path, $asFileName) {
                if ($asFileName) {
                    return $this->fileService->getFile($matches['hash']) ?? $path.$matches['hash'];
                }

                $parameters = [];
                $query = \html_entity_decode($matches['query'] ?? '');
                \parse_str($query, $parameters);
                if (\is_string($parameters['name'] ?? null) && \is_string($parameters['type'] ?? null)) {
                    return $this->assetExtension->assetPath(
                        [
                            EmsFields::CONTENT_FILE_HASH_FIELD => $matches['hash'],
                            EmsFields::CONTENT_FILE_NAME_FIELD => $parameters['name'],
                            EmsFields::CONTENT_MIME_TYPE_FIELD => $parameters['type'],
                        ],
                        [
                        ],
                        'ems_asset',
                        EmsFields::CONTENT_FILE_HASH_FIELD,
                        EmsFields::CONTENT_FILE_NAME_FIELD,
                        EmsFields::CONTENT_MIME_TYPE_FIELD,
                        UrlGeneratorInterface::ABSOLUTE_PATH
                    );
                }

                return $path.$matches['hash'];
            },
            $input
        );
        $path = $this->router->generate(Routes::DATA_LINK, ['key' => '__KEY__'], UrlGeneratorInterface::ABSOLUTE_PATH);

        return \preg_replace_callback(
            '/ems:\/\/(?P<key>file:([^\n\r"\'\?]*))/i',
            fn ($matches) => \str_replace('__KEY__', $matches['key'], $path),
            Type::string($out)
        );
    }

    private function contrastRatio(string $c1, string $c2): float
    {
        $color1 = new Color($c1);
        $color2 = new Color($c2);

        return $color1->contrastRatio($color2);
    }
}
