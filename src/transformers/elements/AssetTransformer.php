<?php

namespace samuelreichor\coPilot\transformers\elements;

use craft\base\ElementInterface;
use craft\elements\Asset;
use samuelreichor\coPilot\transformers\SerializeFallbackTrait;

/**
 * Handles serialization of Asset elements for AI context.
 */
class AssetTransformer implements ElementTransformerInterface
{
    use SerializeFallbackTrait;

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

        $data = [
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

        $fields = $this->serializeCustomFields($element, $depth);
        if ($fields !== []) {
            $data['fields'] = $fields;
        }

        return $data;
    }

    public function getElementTypeLabel(): string
    {
        return 'Asset';
    }
}
