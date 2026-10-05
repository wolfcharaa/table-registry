<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Select\Query;

use Wolfcharaa\TableRegistry\Select\Definition\SelectCriterionDefinition;
use Wolfcharaa\TableRegistry\Select\Definition\SelectDefinition;
use Wolfcharaa\TableRegistry\Select\Definition\SelectValueType;

/**
 * @api
 */
final class SelectQueryValidator
{
    public function validate(SelectDefinition $definition, SelectQueryInput $input): SelectQueryValidationResult
    {
        $issues = [];

        $this->validateCriteria($definition, $input, $issues);
        $this->validateSearch($definition, $input, $issues);
        $this->validateSort($definition, $input, $issues);
        $this->validatePagination($definition, $input, $issues);

        return new SelectQueryValidationResult($issues);
    }

    /** @param list<SelectQueryValidationIssue> $issues */
    private function validateCriteria(SelectDefinition $definition, SelectQueryInput $input, array &$issues): void
    {
        foreach ($input->criteria() as $key => $value) {
            $criterion = $definition->criterion($key);
            if ($criterion === null) {
                $issues[] = $this->issue(
                    'unknown_criterion',
                    'criteria.' . $key,
                    \sprintf('Select criterion "%s" is not defined.', $key)
                );
                continue;
            }

            if (!$this->validCriterionValue($criterion, $value)) {
                $issues[] = $this->issue(
                    'invalid_criterion_value',
                    'criteria.' . $key,
                    \sprintf('Select criterion "%s" has an invalid canonical value.', $key)
                );
            }
        }
    }

    /** @param list<SelectQueryValidationIssue> $issues */
    private function validateSearch(SelectDefinition $definition, SelectQueryInput $input, array &$issues): void
    {
        $value = $input->search();
        if ($value === null) {
            return;
        }

        $search = $definition->search();
        if ($search === null) {
            $issues[] = $this->issue('search_disabled', 'search', 'Select search is not enabled.');
            return;
        }

        $length = \mb_strlen($value);
        if ($value === '' || $length < $search->minLength()) {
            $issues[] = $this->issue(
                'search_too_short',
                'search',
                \sprintf('Select search must contain at least %d character(s).', $search->minLength())
            );
        }

        if ($search->maxLength() !== null && $length > $search->maxLength()) {
            $issues[] = $this->issue(
                'search_too_long',
                'search',
                \sprintf('Select search must not exceed %d character(s).', $search->maxLength())
            );
        }
    }

    /** @param list<SelectQueryValidationIssue> $issues */
    private function validateSort(SelectDefinition $definition, SelectQueryInput $input, array &$issues): void
    {
        $key = $input->sortKey();
        $direction = $input->sortDirection();

        if ($key === null) {
            if ($direction !== null) {
                $issues[] = $this->issue(
                    'sort_direction_without_key',
                    'sortDirection',
                    'Select sort direction requires a sort key.'
                );
            }
            return;
        }

        $sort = $definition->sort($key);
        if ($sort === null) {
            $issues[] = $this->issue(
                'unknown_sort',
                'sortKey',
                \sprintf('Select sort "%s" is not defined.', $key)
            );
            return;
        }

        if ($direction === null || !$sort->allows($direction)) {
            $issues[] = $this->issue(
                'sort_direction_not_allowed',
                'sortDirection',
                \sprintf('Select sort direction is not allowed for "%s".', $key)
            );
        }
    }

    /** @param list<SelectQueryValidationIssue> $issues */
    private function validatePagination(SelectDefinition $definition, SelectQueryInput $input, array &$issues): void
    {
        $pagination = $definition->pagination();
        if ($pagination === null) {
            if ($input->limit() !== null) {
                $issues[] = $this->issue(
                    'pagination_disabled',
                    'limit',
                    'Select pagination is not enabled.'
                );
            }
            if ($input->offset() !== null) {
                $issues[] = $this->issue(
                    'pagination_disabled',
                    'offset',
                    'Select pagination is not enabled.'
                );
            }
            return;
        }

        if ($input->limit() !== null && $input->limit() < 1) {
            $issues[] = $this->issue('invalid_limit', 'limit', 'Select limit must be positive.');
        } elseif ($input->limit() !== null && $input->limit() > $pagination->maxLimit()) {
            $issues[] = $this->issue(
                'limit_exceeds_max',
                'limit',
                \sprintf('Select limit must not exceed %d.', $pagination->maxLimit())
            );
        }

        if ($input->offset() !== null && $input->offset() < 0) {
            $issues[] = $this->issue('invalid_offset', 'offset', 'Select offset must not be negative.');
        }
    }

    private function validCriterionValue(SelectCriterionDefinition $criterion, mixed $value): bool
    {
        $operator = $criterion->operator();

        if ($operator === SelectCriterionDefinition::NULLABILITY) {
            return $value instanceof SelectNullability;
        }

        $type = $criterion->valueType();
        if ($type === null) {
            return false;
        }

        if ($operator === SelectCriterionDefinition::RANGE) {
            if (!$value instanceof SelectRange || (!$value->hasFrom() && !$value->hasTo())) {
                return false;
            }

            return (!$value->hasFrom() || $this->validScalar($value->fromValue(), $type))
                && (!$value->hasTo() || $this->validScalar($value->toValue(), $type));
        }

        if ($operator === SelectCriterionDefinition::ONE_OF) {
            return $this->validList($value, $type);
        }

        if ($operator === SelectCriterionDefinition::EQUAL_OR_ONE_OF && \is_array($value)) {
            return $this->validList($value, $type);
        }

        if ($operator === SelectCriterionDefinition::CONTAINS) {
            return \is_string($value) && $value !== '';
        }

        return $this->validScalar($value, $type);
    }

    private function validList(mixed $value, SelectValueType $type): bool
    {
        if (!\is_array($value) || $value === [] || !\array_is_list($value)) {
            return false;
        }

        foreach ($value as $item) {
            if (!$this->validScalar($item, $type)) {
                return false;
            }
        }

        return true;
    }

    private function validScalar(mixed $value, SelectValueType $type): bool
    {
        return match ($type) {
            SelectValueType::String => \is_string($value),
            SelectValueType::Integer => \is_int($value),
            SelectValueType::Float => \is_float($value),
            SelectValueType::Boolean => \is_bool($value),
            SelectValueType::Date => \is_string($value) && $this->validDate($value, 'Y-m-d'),
            SelectValueType::Time => \is_string($value) && $this->validDate($value, 'H:i:s'),
            SelectValueType::DateTime => \is_string($value) && $this->validDate($value, 'Y-m-d H:i:s'),
        };
    }

    private function validDate(string $value, string $format): bool
    {
        $date = \DateTimeImmutable::createFromFormat('!' . $format, $value);
        if ($date === false || $date->format($format) !== $value) {
            return false;
        }

        $errors = \DateTimeImmutable::getLastErrors();

        return $errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0);
    }

    private function issue(string $code, string $key, string $message): SelectQueryValidationIssue
    {
        return new SelectQueryValidationIssue($code, $key, $message);
    }
}
