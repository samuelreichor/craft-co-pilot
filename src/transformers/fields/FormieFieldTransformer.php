<?php

namespace samuelreichor\coPilot\transformers\fields;

use craft\base\Element;
use craft\base\FieldInterface;
use samuelreichor\coPilot\helpers\PluginHelper;

/**
 * Handles Formie form selection fields.
 */
class FormieFieldTransformer implements FieldTransformerInterface
{
    public function getSupportedFieldClasses(): array
    {
        return [];
    }

    public function matchesField(FieldInterface $field): ?bool
    {
        if (!PluginHelper::isPluginInstalledAndEnabled('formie')) {
            return null;
        }

        return get_class($field) === 'verbb\formie\fields\Forms' ? true : null;
    }

    public function describeField(FieldInterface $field, array $fieldInfo): array
    {
        $fieldInfo['valueFormat'] = 'form handle (string)';
        $fieldInfo['hint'] = 'Use searchFormieForms to find the correct handle.';

        return $fieldInfo;
    }

    public function serializeValue(FieldInterface $field, mixed $value, int $depth): mixed
    {
        if ($value === null) {
            return null;
        }

        $form = is_object($value) && method_exists($value, 'one')
            ? $value->one()
            : $value;

        if ($form === null) {
            return null;
        }

        return [
            'id' => $form->id,
            'title' => $form->title,
            'handle' => $form->handle,
        ];
    }

    public function normalizeValue(FieldInterface $field, mixed $value, ?Element $element = null): mixed
    {
        if (is_string($value)) {
            $form = \verbb\formie\elements\Form::find() // @phpstan-ignore class.notFound
                ->handle($value)
                ->one();

            return $form ? [$form->id] : null;
        }

        return null;
    }
}
