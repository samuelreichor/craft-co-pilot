<?php

namespace samuelreichor\coPilot\services;

use craft\base\Component;
use craft\base\ElementInterface;
use craft\base\FieldInterface;
use craft\fieldlayoutelements\CustomField;
use craft\models\FieldLayout;
use samuelreichor\coPilot\constants\Constants;
use samuelreichor\coPilot\events\RegisterElementTransformersEvent;
use samuelreichor\coPilot\events\RegisterFieldTransformersEvent;
use samuelreichor\coPilot\transformers\elements\AssetTransformer;
use samuelreichor\coPilot\transformers\elements\ElementTransformerInterface;
use samuelreichor\coPilot\transformers\elements\EntryTransformer;
use samuelreichor\coPilot\transformers\fields\ComplexFieldTransformer;
use samuelreichor\coPilot\transformers\fields\FieldTransformerInterface;
use samuelreichor\coPilot\transformers\fields\OptionsFieldTransformer;
use samuelreichor\coPilot\transformers\fields\RelationalFieldTransformer;
use samuelreichor\coPilot\transformers\fields\RichTextFieldTransformer;
use samuelreichor\coPilot\transformers\fields\ScalarFieldTransformer;

/**
 * Unified registry for field and element transformers.
 * Resolves the appropriate transformer for a given field or element.
 */
class TransformerRegistry extends Component
{
    public const EVENT_REGISTER_FIELD_TRANSFORMERS = 'registerFieldTransformers';
    public const EVENT_REGISTER_ELEMENT_TRANSFORMERS = 'registerElementTransformers';

    /** @var FieldTransformerInterface[]|null */
    private ?array $fieldTransformers = null;

    /** @var ElementTransformerInterface[]|null */
    private ?array $elementTransformers = null;

    // ---- Field transformer resolution ----

    /**
     * Returns the transformer for a given field, or null if none matches.
     */
    public function getTransformerForField(FieldInterface $field): ?FieldTransformerInterface
    {
        foreach ($this->getFieldTransformers() as $transformer) {
            $customMatch = $transformer->matchesField($field);

            if ($customMatch === true) {
                return $transformer;
            }

            if ($customMatch === false) {
                continue;
            }

            foreach ($transformer->getSupportedFieldClasses() as $className) {
                if ($field instanceof $className) {
                    return $transformer;
                }
            }
        }

        return null;
    }

    /**
     * Returns all registered field transformers (custom first, then built-in).
     *
     * @return FieldTransformerInterface[]
     */
    public function getFieldTransformers(): array
    {
        if ($this->fieldTransformers !== null) {
            return $this->fieldTransformers;
        }

        $event = new RegisterFieldTransformersEvent();
        $this->trigger(self::EVENT_REGISTER_FIELD_TRANSFORMERS, $event);

        $this->fieldTransformers = array_merge($event->transformers, $this->getBuiltInFieldTransformers());

        return $this->fieldTransformers;
    }

    // ---- Element transformer resolution ----

    /**
     * Returns the transformer for a given element, or null if none matches.
     */
    public function getTransformerForElement(ElementInterface $element): ?ElementTransformerInterface
    {
        foreach ($this->getElementTransformers() as $transformer) {
            foreach ($transformer->getSupportedElementClasses() as $className) {
                if ($element instanceof $className) {
                    return $transformer;
                }
            }
        }

        return null;
    }

    /**
     * Returns all registered element transformers (custom first, then built-in).
     *
     * @return ElementTransformerInterface[]
     */
    public function getElementTransformers(): array
    {
        if ($this->elementTransformers !== null) {
            return $this->elementTransformers;
        }

        $event = new RegisterElementTransformersEvent();
        $this->trigger(self::EVENT_REGISTER_ELEMENT_TRANSFORMERS, $event);

        $this->elementTransformers = array_merge($event->transformers, $this->getBuiltInElementTransformers());

        return $this->elementTransformers;
    }

    // ---- Shared field layout iteration ----

    /**
     * Resolves all non-excluded custom fields from a field layout,
     * using layout handles (custom overrides) instead of raw field handles.
     *
     * Used by both SchemaService (for describing) and element transformers (for serializing).
     *
     * @return array<int, array{layoutElement: CustomField, field: FieldInterface, handle: string}>
     */
    public function resolveFieldLayoutFields(FieldLayout $fieldLayout): array
    {
        $resolved = [];

        foreach ($fieldLayout->getCustomFieldElements() as $layoutElement) {
            try {
                $field = $layoutElement->getField();

                if ($this->isExcludedField($field)) {
                    continue;
                }

                $resolved[] = [
                    'layoutElement' => $layoutElement,
                    'field' => $field,
                    'handle' => $layoutElement->attribute(),
                ];
            } catch (\Throwable) {
                continue;
            }
        }

        return $resolved;
    }

    /**
     * Checks whether a field class is excluded from transformer output.
     */
    private function isExcludedField(FieldInterface $field): bool
    {
        $fieldClass = get_class($field);

        foreach (Constants::EXCLUDED_FIELD_CLASSES as $excludedClass) {
            if ($fieldClass === $excludedClass || is_subclass_of($field, $excludedClass)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return FieldTransformerInterface[]
     */
    private function getBuiltInFieldTransformers(): array
    {
        return [
            new ScalarFieldTransformer(),
            new OptionsFieldTransformer(),
            new RichTextFieldTransformer(),
            new RelationalFieldTransformer(),
            new ComplexFieldTransformer(),
        ];
    }

    /**
     * @return ElementTransformerInterface[]
     */
    private function getBuiltInElementTransformers(): array
    {
        return [
            new EntryTransformer(),
            new AssetTransformer(),
        ];
    }
}
