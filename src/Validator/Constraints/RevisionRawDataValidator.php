<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Validator\Constraints;

use EMS\CoreBundle\Core\ContentType\Version\VersionOptions;
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
        if (null === $fromField = $constraint->contentType->getVersionDateFromField()) {
            return;
        }

        if (null === $versionFromDate = $this->getVersionDateFromRawData($rawData, $fromField)) {
            $this->addViolation($constraint->versionFromRequired, $fromField);

            return;
        }

        $toField = $constraint->contentType->getVersionDateToField();
        $versionToDate = $this->getVersionDateFromRawData($rawData, $toField);

        if (null === $toField || null === $versionToDate) {
            return;
        }

        $formFieldType = $constraint->contentType->getFieldType()->findChildByName($fromField);
        $formFieldLabel = $formFieldType ? $formFieldType->getDisplayOption('label', $fromField) : $fromField;

        if ($versionToDate <= $versionFromDate) {
            $this->addViolation($constraint->versionToGreater, $toField, ['%fromField%' => $formFieldLabel]);
        }

        $intervalOneDay = $constraint->contentType->getVersionOptions()[VersionOptions::DATES_INTERVAL_ONE_DAY];
        $diffDays = $versionFromDate->diff($versionToDate)->days;

        if ($versionToDate > $versionFromDate && $intervalOneDay && 0 === $diffDays) {
            $this->addViolation($constraint->versionToGreaterOneDay, $toField, ['%fromField%' => $formFieldLabel]);
        }
    }

    /**
     * @param array<string, string> $parameters
     */
    private function addViolation(string $message, string $field, array $parameters = []): void
    {
        $this->context
            ->buildViolation($message)
            ->setParameters($parameters)
            ->atPath(\sprintf('[%s]', $field))
            ->addViolation()
        ;
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
