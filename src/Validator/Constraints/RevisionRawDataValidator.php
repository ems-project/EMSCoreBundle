<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Validator\Constraints;

use EMS\CoreBundle\Core\ContentType\Version\VersionFields;
use EMS\CoreBundle\Core\ContentType\Version\VersionOptions;
use EMS\Helpers\ArrayHelper\ArrayHelper;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

use function Symfony\Component\Translation\t;

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

        $formFieldType = $contentType->getFieldType()->getChildByName($fromField);
        $toFieldType = $contentType->getFieldType()->getChildByName($toField);

        if (null === $formFieldType || null === $toFieldType) {
            throw new \RuntimeException('Missing fields');
        }

        $formFieldLabel = $formFieldType->getDisplayOption('label', $fromField);

        if (null === $versionFromDate) {
            $this->context
                ->buildViolation(t('revision.version_from_required', [], 'validators'))
                ->atPath($formFieldType->getPath())
                ->addViolation()
            ;

            return;
        }

        if (null === $versionToDate) {
            return;
        }

        if ($versionToDate <= $versionFromDate) {
            $this->context
                ->buildViolation(t('revision.version_to_greater', [], 'validators'))
                ->setParameters(['fromField' => $formFieldLabel])
                ->atPath($toFieldType->getPath())
                ->addViolation()
            ;
        }

        $intervalOneDay = $constraint->contentType->getVersioning()->option(VersionOptions::DATES_INTERVAL_ONE_DAY);
        $diffDays = $versionFromDate->diff($versionToDate)->days;

        if ($versionToDate > $versionFromDate && $intervalOneDay && 0 === $diffDays) {
            $this->context
                ->buildViolation(t('revision.version_to_greater_one_day', [], 'validators'))
                ->setParameters(['fromField' => $formFieldLabel])
                ->atPath($toFieldType->getPath())
                ->addViolation()
            ;
        }
    }
}
