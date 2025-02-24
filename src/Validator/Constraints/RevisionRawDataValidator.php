<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Validator\Constraints;

use EMS\CoreBundle\Core\ContentType\Version\VersionFields;
use EMS\CoreBundle\Core\ContentType\Version\VersionOptions;
use EMS\CoreBundle\Entity\ContentType;
use EMS\Helpers\ArrayHelper\ArrayHelper;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class RevisionRawDataValidator extends ConstraintValidator
{
    /**
     * @param array<string, mixed> $value
     * @param RevisionRawData      $constraint
     */
    #[\Override]
    public function validate($value, Constraint $constraint): void
    {
        if ($constraint->contentType->getVersioning()->enabled()) {
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
        $versioning = $contentType->getVersioning();

        if (null === $fromField = $versioning->field(VersionFields::DATE_FROM)) {
            throw new \RuntimeException('Version from field is required.');
        }
        if (null === $toField = $versioning->field(VersionFields::DATE_TO)) {
            throw new \RuntimeException('Version to field is required.');
        }

        $format = $versioning->dateFormat();
        $versionFromDate = ArrayHelper::findDateTime($fromField, $rawData, $format);
        $versionToDate = ArrayHelper::findDateTime($toField, $rawData, $format);

        if (null === $versionFromDate) {
            $this->addViolation($contentType, $constraint->versionFromRequired, $fromField);

            return;
        }
        if (null === $versionToDate) {
            return;
        }

        $formFieldType = $constraint->contentType->getFieldType()->findChildByName($fromField);
        $formFieldLabel = $formFieldType ? $formFieldType->getDisplayOption('label', $fromField) : $fromField;

        if ($versionToDate <= $versionFromDate) {
            $this->addViolation($contentType, $constraint->versionToGreater, $toField, [
                '%fromField%' => $formFieldLabel,
            ]);
        }

        $intervalOneDay = $constraint->contentType->getVersioning()->option(VersionOptions::DATES_INTERVAL_ONE_DAY);
        $diffDays = $versionFromDate->diff($versionToDate)->days;

        if ($versionToDate > $versionFromDate && $intervalOneDay && 0 === $diffDays) {
            $this->addViolation($contentType, $constraint->versionToGreaterOneDay, $toField, [
                '%fromField%' => $formFieldLabel,
            ]);
        }
    }

    /**
     * @param array<string, string> $parameters
     */
    private function addViolation(ContentType $contentType, string $message, string $field, array $parameters = []): void
    {
        if (null === $fieldType = $contentType->getFieldType()->findChildByName($field)) {
            return;
        }

        $this->context
            ->buildViolation($message)
            ->setParameters($parameters)
            ->atPath($fieldType->getPath())
            ->addViolation()
        ;
    }
}
