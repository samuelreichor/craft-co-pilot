<?php

namespace samuelreichor\coPilot\transformers\fields;

use craft\base\FieldInterface;
use craft\elements\Entry;

/**
 * Handles field type description, serialization, and normalization for the AI agent.
 *
 * Built-in transformers cover all core Craft field types. Third-party plugins can register
 * their own transformers via the {@see \samuelreichor\coPilot\services\TransformerRegistry}.
 */
interface FieldTransformerInterface
{
    /**
     * Returns the FQCN of supported field classes.
     *
     * @return string[]
     */
    public function getSupportedFieldClasses(): array;

    /**
     * Custom matching for fields without a fixed class (e.g. CKEditor).
     * Return true/false to override class matching, or null to use class matching only.
     */
    public function matchesField(FieldInterface $field): ?bool;

    /**
     * Enriches a field info array with type-specific metadata (hints, options, etc.).
     *
     * @param array<string, mixed> $fieldInfo
     * @return array<string, mixed>
     */
    public function describeField(FieldInterface $field, array $fieldInfo): array;

    /**
     * Serializes a field value for AI context.
     */
    public function serializeValue(FieldInterface $field, mixed $value, int $depth): mixed;

    /**
     * Normalizes an AI-provided value back into Craft's expected format.
     * Return null if no normalization is needed.
     */
    public function normalizeValue(FieldInterface $field, mixed $value, ?Entry $entry = null): mixed;
}
