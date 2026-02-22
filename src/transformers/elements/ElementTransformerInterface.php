<?php

namespace samuelreichor\coPilot\transformers\elements;

use craft\base\ElementInterface;

/**
 * Handles element type serialization for the AI agent.
 *
 * Built-in transformers cover Entry and Asset elements. Third-party plugins can register
 * their own transformers via the {@see \samuelreichor\coPilot\services\TransformerRegistry}.
 */
interface ElementTransformerInterface
{
    /**
     * Returns the FQCN of supported element classes.
     *
     * @return string[]
     */
    public function getSupportedElementClasses(): array;

    /**
     * Serializes an element for AI context.
     *
     * @param string[]|null $fieldHandles Limit to specific fields
     * @return array<string, mixed>|null
     */
    public function serializeElement(ElementInterface $element, int $depth = 2, ?array $fieldHandles = null): ?array;

    /**
     * Returns a label for schema context (e.g. "Entry", "Product").
     */
    public function getElementTypeLabel(): string;
}
