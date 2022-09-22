<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Validator\Constraints;

use EMS\Helpers\Standard\DateTime;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class RevisionRawDataValidator extends ConstraintValidator
{
    /**
     * @param array<string, mixed> $value
     * @param RevisionRawData      $constraint
     */
    public function validate($value, Constraint $constraint): void
    {
        if ($constraint->contentType->hasVersionTags()) {
            $this->validateVersionDates($constraint, $value);
        }
    }

    /**
     * @param RevisionRawData      $constraint
     * @param array<string, mixed> $rawData
     */
    private function validateVersionDates(Constraint $constraint, array $rawData): void
    {
        $contentType = $constraint->contentType;

        $fromField = $contentType->getVersionDateFromField();
        $versionFromDate = $this->getVersionDateFromRawData($rawData, $fromField);

        if (null === $versionFromDate) {
            $this->context
                ->buildViolation($constraint->versionFromRequired)
                ->atPath(\sprintf('[%s]', $fromField))
                ->addViolation();
        }

        $toField = $contentType->getVersionDateToField();
        $versionToDate = $this->getVersionDateFromRawData($rawData, $toField);

        if ($fromField && $versionToDate && $versionToDate < $versionFromDate) {
            $formFieldType = $contentType->getFieldType()->findChildByName($fromField);
            $formFieldLabel = $formFieldType ? $formFieldType->getDisplayOption('label', $fromField) : $fromField;

            $this->context
                ->buildViolation($constraint->versionToInvalid)
                ->setParameter('%fromField%', $formFieldLabel)
                ->atPath(\sprintf('[%s]', $toField))
                ->addViolation();
        }
    }

    /**
     * @param array<string, mixed> $rawData
     */
    private function getVersionDateFromRawData(array $rawData, ?string $field): ?\DateTimeInterface
    {
        if (null === $field || !isset($rawData[$field])) {
            return null;
        }

        return DateTime::createFromFormat($rawData[$field]);
    }
}
