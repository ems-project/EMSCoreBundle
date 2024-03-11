<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Validator\Constraints\MediaLibrary;

use EMS\CoreBundle\Core\Component\MediaLibrary\MediaLibraryDocumentDTO;
use EMS\CoreBundle\Core\Component\MediaLibrary\MediaLibraryService;
use EMS\CoreBundle\EMSCoreBundle;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

class DocumentDTOValidator extends ConstraintValidator
{
    public function __construct(private readonly MediaLibraryService $mediaLibraryService)
    {
    }

    /**
     * @param MediaLibraryDocumentDTO $dto
     */
    public function validate($dto, Constraint $constraint): void
    {
        if (!$dto instanceof MediaLibraryDocumentDTO) {
            throw new UnexpectedValueException($dto, MediaLibraryDocumentDTO::class);
        }

        if (!$constraint instanceof DocumentDTO) {
            throw new UnexpectedValueException($constraint, DocumentDTO::class);
        }

        if (null === $dto->name) {
            return;
        }

        if ($this->mediaLibraryService->count($dto->getPath(), $dto->id) > 0) {
            $this->context
                ->buildViolation('media_library.error.folder_exists')
                ->setTranslationDomain(EMSCoreBundle::TRANS_COMPONENT)
                ->atPath('name')
                ->addViolation();
        }
    }
}
