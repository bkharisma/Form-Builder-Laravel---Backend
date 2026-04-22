<?php

namespace Modules\FormBuilder\Services;

class ConditionalLogicService
{
    public function isFieldVisible(array $field, array $formData): bool
    {
        $logic = $field['conditional_logic'] ?? null;

        if (!$logic || empty($logic['enabled']) || empty($logic['conditions'])) {
            return true;
        }

        $conditionsMet = false;
        $matchType = $logic['match_type'] ?? 'all';

        if ($matchType === 'all') {
            $conditionsMet = true;
            foreach ($logic['conditions'] as $condition) {
                if (!$this->evaluateCondition($condition, $formData)) {
                    $conditionsMet = false;
                    break;
                }
            }
        } else {
            $conditionsMet = false;
            foreach ($logic['conditions'] as $condition) {
                if ($this->evaluateCondition($condition, $formData)) {
                    $conditionsMet = true;
                    break;
                }
            }
        }

        $action = $logic['action'] ?? 'show';
        return $action === 'show' ? $conditionsMet : !$conditionsMet;
    }

    protected function evaluateCondition(array $condition, array $formData): bool
    {
        $fieldId = $condition['field_id'] ?? '';
        $operator = $condition['operator'] ?? 'equals';
        $targetValue = isset($condition['value']) ? (string)$condition['value'] : '';
        $actualValue = $formData[$fieldId] ?? null;

        if ($actualValue === null || $actualValue === '') {
            if ($operator === 'not_equals' && $targetValue !== '') return true;
            if ($operator === 'equals' && $targetValue === '') return true;
            return false;
        }

        if (is_bool($actualValue)) {
            $strActualValue = $actualValue ? 'true' : 'false';
        } else {
            $strActualValue = strtolower((string)$actualValue);
        }
        
        $strTargetValue = strtolower($targetValue);

        switch ($operator) {
            case 'equals':
                return $strActualValue === $strTargetValue;
            case 'not_equals':
                return $strActualValue !== $strTargetValue;
            case 'contains':
                return str_contains($strActualValue, $strTargetValue);
            case 'not_contains':
                return !str_contains($strActualValue, $strTargetValue);
            case 'greater_than':
                if (is_numeric($actualValue) && is_numeric($targetValue)) {
                    return (float)$actualValue > (float)$targetValue;
                }
                return false;
            case 'less_than':
                if (is_numeric($actualValue) && is_numeric($targetValue)) {
                    return (float)$actualValue < (float)$targetValue;
                }
                return false;
            default:
                return false;
        }
    }
}
