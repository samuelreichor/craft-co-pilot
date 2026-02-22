<?php

namespace samuelreichor\coPilot\services;

use craft\base\Component;
use craft\elements\Asset;
use craft\elements\Entry;
use samuelreichor\coPilot\CoPilot;

/**
 * Serializes Craft elements for AI context.
 * Delegates to element and field handlers via the registries.
 */
class ContextService extends Component
{
    public const EVENT_BEFORE_SERIALIZE_ENTRY = 'beforeSerializeEntry';

    /**
     * Serializes an entry for AI context.
     *
     * @param string[]|null $fieldHandles Limit to specific fields
     * @return array<string, mixed>|null Returns null if cancelled by event
     */
    public function serializeEntry(Entry $entry, int $depth = 2, ?array $fieldHandles = null): ?array
    {
        $transformer = CoPilot::getInstance()->transformerRegistry->getTransformerForElement($entry);

        if ($transformer !== null) {
            return $transformer->serializeElement($entry, $depth, $fieldHandles);
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeAsset(Asset $asset): array
    {
        $transformer = CoPilot::getInstance()->transformerRegistry->getTransformerForElement($asset);

        if ($transformer !== null) {
            $result = $transformer->serializeElement($asset);
            if ($result !== null) {
                return $result;
            }
        }

        // Fallback (should not happen with built-in handler)
        return [
            '_type' => 'asset',
            'id' => $asset->id,
            'filename' => $asset->filename,
            'url' => $asset->url,
            'alt' => $asset->alt ?? '',
            'kind' => $asset->kind,
            'size' => $asset->size,
            'width' => $asset->width,
            'height' => $asset->height,
        ];
    }
}
