<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Validator\Constraints\MediaLibrary;

use EMS\CoreBundle\Core\Component\MediaLibrary\File\MediaLibraryFile;
use EMS\CoreBundle\Core\Component\MediaLibrary\Folder\MediaLibraryFolder;
use EMS\CoreBundle\Core\Component\MediaLibrary\MediaLibraryDocument;
use EMS\CoreBundle\Core\Component\MediaLibrary\MediaLibraryService;
use EMS\CoreBundle\EMSCoreBundle;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

class DocumentValidator extends ConstraintValidator
{
    public function __construct(private readonly MediaLibraryService $mediaLibraryService)
    {
    }

    /**
     * @param MediaLibraryDocument $value
     */
    public function validate($value, Constraint $constraint): void
    {
        if (!$constraint instanceof Document) {
            throw new UnexpectedValueException($constraint, Document::class);
        }

        match ($value::class) {
            MediaLibraryFile::class, MediaLibraryFolder::class => $this->existsValidation($value),
            default => throw new UnexpectedValueException($value, MediaLibraryDocument::class),
        };
    }

    private function existsValidation(MediaLibraryFile|MediaLibraryFolder $value): void
    {
        if (!$value->hasName() || !$this->mediaLibraryService->exists($value)) {
            return;
        }

        $message = match (true) {
            $value instanceof MediaLibraryFile => 'media_library.error.file_exists',
            $value instanceof MediaLibraryFolder => 'media_library.error.folder_exists',
        };

        $this->context
            ->buildViolation($message)
            ->setTranslationDomain(EMSCoreBundle::TRANS_COMPONENT)
            ->atPath('name')
            ->addViolation();
    }
}
