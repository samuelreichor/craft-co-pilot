<?php

namespace samuelreichor\coPilot\services;

use Craft;
use craft\base\Component;
use craft\base\FieldInterface;
use craft\fieldlayoutelements\CustomField;
use craft\fields\ContentBlock as ContentBlockField;
use craft\fields\Matrix as MatrixField;
use craft\models\EntryType;
use craft\models\FieldLayout;
use samuelreichor\coPilot\constants\Constants;
use samuelreichor\coPilot\CoPilot;
use samuelreichor\coPilot\enums\SectionAccess;

/**
 * Builds Craft schema descriptions for the AI.
 */
class SchemaService extends Component
{
    /**
     * Returns schema info for all sections accessible to the AI.
     *
     * @return array<string, mixed>
     */
    public function getAccessibleSchema(): array
    {
        if (Craft::$app->getConfig()->getGeneral()->devMode) {
            return $this->buildSchema();
        }

        $cacheKey = Constants::CACHE_SCHEMA_PREFIX . 'all';
        $cached = Craft::$app->getCache()->get($cacheKey);

        if ($cached !== false) {
            return $cached;
        }

        $schema = $this->buildSchema();
        Craft::$app->getCache()->set($cacheKey, $schema, 3600);

        return $schema;
    }

    /**
     * Invalidates the schema cache.
     */
    public function invalidateCache(): void
    {
        Craft::$app->getCache()->delete(Constants::CACHE_SCHEMA_PREFIX . 'all');
    }

    /**
     * @return array<string, mixed>
     */
    private function buildSchema(): array
    {
        $settings = CoPilot::getInstance()->getSettings();
        $permissionGuard = CoPilot::getInstance()->permissionGuard;
        $sections = Craft::$app->getEntries()->getAllSections();
        $result = ['sections' => []];

        foreach ($sections as $section) {
            $access = $settings->getSectionAccessLevel($section->uid);

            if ($access === SectionAccess::Blocked) {
                continue;
            }

            $guardCheck = $permissionGuard->canReadSection($section->uid);
            if (!$guardCheck['allowed']) {
                continue;
            }

            $permissions = ['read'];
            if ($access === SectionAccess::ReadWrite) {
                $permissions[] = 'write';
            }

            $entryTypes = [];
            foreach ($section->getEntryTypes() as $entryType) {
                $entryTypes[] = $this->describeEntryType($entryType);
            }

            $result['sections'][] = [
                'handle' => $section->handle,
                'name' => $section->name,
                'type' => $section->type,
                'permissions' => $permissions,
                'entryTypes' => $entryTypes,
            ];
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    private function describeEntryType(EntryType $entryType): array
    {
        $fields = [];

        // Native fields: title and slug
        if ($entryType->hasTitleField) {
            $fields[] = [
                'handle' => 'title',
                'name' => 'Title',
                'type' => 'native',
                'required' => true,
            ];
        }

        if ($entryType->showSlugField) {
            $fields[] = [
                'handle' => 'slug',
                'name' => 'Slug',
                'type' => 'native',
            ];
        }

        // Custom and generated fields
        $fieldLayout = $entryType->getFieldLayout();
        $fields = array_merge($fields, $this->describeFieldLayoutFields($fieldLayout));

        return [
            'handle' => $entryType->handle,
            'name' => $entryType->name,
            'fields' => $fields,
        ];
    }

    /**
     * Describes a single custom field from its layout element and field instance.
     *
     * @return array<string, mixed>
     */
    private function describeCustomField(CustomField $layoutElement, FieldInterface $field): array
    {
        // Use the layout handle (custom override) or fall back to the field's handle
        $handle = $layoutElement->attribute();

        $fieldInfo = [
            'handle' => $handle,
            'name' => $field->name,
            'type' => $this->getFieldTypeName($field),
        ];

        if (property_exists($field, 'required') && $field->required) {
            $fieldInfo['required'] = true;
        }

        if (property_exists($field, 'charLimit') && $field->charLimit) {
            $fieldInfo['maxLength'] = $field->charLimit;
        }

        $fieldInfo = $this->describeFieldMetadata($field, $fieldInfo);

        // ContentBlock sub-field descriptions
        if ($field instanceof ContentBlockField) {
            $fieldInfo['fields'] = $this->describeContentBlockFields($field);
        }

        // Matrix block type descriptions
        if ($field instanceof MatrixField) {
            $fieldInfo['blockTypes'] = $this->describeMatrixBlockTypes($field);
        }

        return $fieldInfo;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function describeContentBlockFields(ContentBlockField $field): array
    {
        return $this->describeFieldLayoutFields($field->getFieldLayout());
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function describeMatrixBlockTypes(MatrixField $field): array
    {
        $blockTypes = [];

        foreach ($field->getEntryTypes() as $entryType) {
            $blockFields = [];

            // Native fields for matrix block entry types
            if ($entryType->hasTitleField) {
                $blockFields[] = [
                    'handle' => 'title',
                    'name' => 'Title',
                    'type' => 'native',
                    'required' => true,
                ];
            }

            $fieldLayout = $entryType->getFieldLayout();
            $blockFields = array_merge($blockFields, $this->describeFieldLayoutFields($fieldLayout));

            $blockTypes[] = [
                'handle' => $entryType->handle,
                'name' => $entryType->name,
                'fields' => $blockFields,
            ];
        }

        return $blockTypes;
    }

    /**
     * Describes all custom and generated fields in a field layout.
     *
     * @return array<int, array<string, mixed>>
     */
    private function describeFieldLayoutFields(FieldLayout $fieldLayout): array
    {
        $registry = CoPilot::getInstance()->transformerRegistry;
        $fields = [];

        foreach ($registry->resolveFieldLayoutFields($fieldLayout) as $resolved) {
            $fields[] = $this->describeCustomField($resolved['layoutElement'], $resolved['field']);
        }

        // Generated fields (dynamic fields added by Craft or plugins)
        if (method_exists($fieldLayout, 'getGeneratedFields')) {
            foreach ($fieldLayout->getGeneratedFields() as $generated) {
                if (!is_array($generated) || !isset($generated['handle'])) {
                    continue;
                }

                $handle = $generated['handle'];

                // Skip fields already described as native
                if ($handle === 'title' || $handle === 'slug') {
                    continue;
                }

                $fields[] = [
                    'handle' => $handle,
                    'name' => $handle,
                    'type' => 'generated',
                ];
            }
        }

        return $fields;
    }

    /**
     * Returns a meaningful type name for a field.
     *
     * Falls back to displayName() when getShortName() is too generic (e.g. craft\ckeditor\Field → "Field").
     */
    private function getFieldTypeName(FieldInterface $field): string
    {
        $shortName = (new \ReflectionClass($field))->getShortName();

        if ($shortName === 'Field') {
            return $field::displayName();
        }

        return $shortName;
    }

    /**
     * Enriches a field info array with type-specific metadata via the transformer registry.
     *
     * @param array<string, mixed> $fieldInfo
     * @return array<string, mixed>
     */
    private function describeFieldMetadata(FieldInterface $field, array $fieldInfo): array
    {
        $transformer = CoPilot::getInstance()->transformerRegistry->getTransformerForField($field);

        if ($transformer !== null) {
            return $transformer->describeField($field, $fieldInfo);
        }

        return $fieldInfo;
    }
}
