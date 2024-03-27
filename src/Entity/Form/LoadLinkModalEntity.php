<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Entity\Form;

use EMS\CommonBundle\Common\EMSLink;
use EMS\CommonBundle\Helper\EmsFields;
use EMS\CoreBundle\Form\Form\LoadLinkModalType;
use EMS\Helpers\Standard\Type;

final class LoadLinkModalEntity
{
    public const TARGET_BLANK = '_blank';
    private ?string $target;
    private ?string $dataLink = null;
    private ?string $href = null;
    private ?string $linkType;
    private ?string $mailto = null;
    private ?string $subject = null;
    private ?string $body = null;
    /** @var array{sha1: string, filename: string|null, mimetype: string|null}|null */
    private ?array $file = null;

    public function __construct(private readonly string $url, string $target)
    {
        $this->target = '' === $target ? null : $target;
        if (\str_starts_with($this->url, 'ems://object:')) {
            $this->dataLink = EMSLink::fromText($this->url)->getEmsId();
            $this->linkType = LoadLinkModalType::LINK_TYPE_INTERNAL;
        } elseif (\str_starts_with($this->url, 'ems://asset:')) {
            $this->file = EMSLink::fromText($this->url)->getFileTypeArray();
            $this->linkType = LoadLinkModalType::LINK_TYPE_FILE;
        } elseif (\str_starts_with($this->url, 'mailto:')) {
            \preg_match('/mailto:(?P<mailto>.*)\?(?P<query>.*)?/', $this->url, $matches);
            \parse_str($matches['query'] ?? '', $query);
            $this->mailto = $matches['mailto'] ?? '';
            $this->subject = Type::string($query['subject'] ?? '');
            $this->body = Type::string($query['body'] ?? '');
            $this->linkType = LoadLinkModalType::LINK_TYPE_MAILTO;
        } else {
            $this->href = $this->url;
            $this->linkType = LoadLinkModalType::LINK_TYPE_URL;
        }
    }

    public function getTarget(): ?string
    {
        return $this->target;
    }

    public function getDataLink(): ?string
    {
        return $this->dataLink;
    }

    public function setDataLink(?string $dataLink): void
    {
        $this->dataLink = $dataLink;
    }

    public function getHref(): ?string
    {
        return $this->href;
    }

    public function setHref(?string $href): void
    {
        $this->href = $href;
    }

    public function getLinkType(): ?string
    {
        return $this->linkType;
    }

    public function setLinkType(?string $linkType): void
    {
        $this->linkType = $linkType;
    }

    public function getMailto(): ?string
    {
        return $this->mailto;
    }

    public function setMailto(?string $mailto): void
    {
        $this->mailto = $mailto;
    }

    public function getSubject(): ?string
    {
        return $this->subject;
    }

    public function setSubject(?string $subject): void
    {
        $this->subject = $subject;
    }

    public function getBody(): ?string
    {
        return $this->body;
    }

    public function setBody(string $body): void
    {
        $this->body = $body;
    }

    public function hasTargetBlank(): bool
    {
        return \in_array($this->target, [null, self::TARGET_BLANK]);
    }

    public function getTargetBlank(): bool
    {
        return self::TARGET_BLANK === $this->target;
    }

    public function setTargetBlank(bool $targetBlank): void
    {
        if (!$this->hasTargetBlank()) {
            throw new \RuntimeException('A specific target has been defined');
        }
        $this->target = $targetBlank ? self::TARGET_BLANK : null;
    }

    public function generateUrl(): ?string
    {
        switch ($this->linkType) {
            case LoadLinkModalType::LINK_TYPE_URL:
                return $this->href;
            case LoadLinkModalType::LINK_TYPE_INTERNAL:
                return "ems://object:$this->dataLink";
            case LoadLinkModalType::LINK_TYPE_MAILTO:
                $subject = \rawurlencode($this->subject ?? '');
                $body = \rawurlencode($this->body ?? '');
                if (null === $this->mailto) {
                    return null;
                }

                return "mailto:$this->mailto?body=$body&subject=$subject";
            case LoadLinkModalType::LINK_TYPE_FILE:
                if (null !== $this->file) {
                    $hash = \rawurlencode($this->file[EmsFields::CONTENT_FILE_HASH_FIELD]);
                    $name = \rawurlencode($this->file[EmsFields::CONTENT_FILE_NAME_FIELD] ?? 'file.bin');
                    $type = \rawurlencode($this->file[EmsFields::CONTENT_MIME_TYPE_FIELD] ?? 'application/bin');

                    return "ems://asset:$hash?name=$name&type=$type";
                }
        }
        throw new \RuntimeException(\sprintf('Unsupported %s link type', $this->linkType));
    }

    /**
     * @return array{sha1: string, filename: string|null, mimetype: string|null}|null
     */
    public function getFile(): ?array
    {
        return $this->file;
    }

    /**
     * @param array{sha1: string, filename: string|null, mimetype: string|null}|null $file
     */
    public function setFile(?array $file): void
    {
        $this->file = $file;
    }
}