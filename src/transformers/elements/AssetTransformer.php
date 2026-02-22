<?php

namespace samuelreichor\coPilot\transformers\elements;

use craft\base\ElementInterface;
use craft\elements\Asset;

/**
 * Handles serialization of Asset elements for AI context.
 */
class AssetTransformer implements ElementTransformerInterface
{
    public function getSupportedElementClasses(): array
    {
        return [
            Asset::class,
        ];
    }

    public function serializeElement(ElementInterface $element, int $depth = 2, ?array $fieldHandles = null): ?array
    {
        if (!$element instanceof Asset) {
            return null;
        }

        return [
            '_type' => 'asset',
            'id' => $element->id,
            'filename' => $element->filename,
            'url' => $element->url,
            'alt' => $element->alt ?? '',
            'kind' => $element->kind,
            'size' => $element->size,
            'width' => $element->width,
            'height' => $element->height,
        ];
    }

    public function getElementTypeLabel(): string
    {
        return 'Asset';
    }
}
