<?php

namespace samuelreichor\coPilot\transformers\elements;

use craft\base\ElementInterface;
use craft\elements\Entry;
use samuelreichor\coPilot\CoPilot;
use samuelreichor\coPilot\events\SerializeEntryEvent;
use samuelreichor\coPilot\services\ContextService;
use samuelreichor\coPilot\transformers\SerializeFallbackTrait;

/**
 * Handles serialization of Entry elements for AI context.
 */
class EntryTransformer implements ElementTransformerInterface
{
    use SerializeFallbackTrait;
    public function getSupportedElementClasses(): array
    {
        return [
            Entry::class,
        ];
    }

    public function serializeElement(ElementInterface $element, int $depth = 2, ?array $fieldHandles = null): ?array
    {
        if (!$element instanceof Entry) {
            return null;
        }

        $contextService = CoPilot::getInstance()->contextService;

        $event = new SerializeEntryEvent();
        $event->entry = $element;
        $event->fields = $fieldHandles ?? $this->getFieldHandles($element);
        $contextService->trigger(ContextService::EVENT_BEFORE_SERIALIZE_ENTRY, $event);

        if ($event->cancel) {
            return null;
        }

        $data = [
            '_type' => 'entry',
            'id' => $element->id,
            'title' => $element->title ?: $element->getSection()?->name,
            'slug' => $element->slug,
            'section' => $element->getSection()?->handle,
            'type' => $element->getType()->handle,
            'status' => $element->getStatus(),
            'dateCreated' => $element->dateCreated?->format('c'),
            'dateUpdated' => $element->dateUpdated?->format('c'),
            'url' => $element->url,
        ];

        $author = $element->getAuthor();
        if ($author) {
            $data['author'] = $author->fullName ?? $author->username;
        }

        $data['fields'] = $this->serializeCustomFields($element, $depth, $event->fields);

        return $data;
    }

    public function getElementTypeLabel(): string
    {
        return 'Entry';
    }

    /**
     * @return string[]
     */
    private function getFieldHandles(Entry $entry): array
    {
        $fieldLayout = $entry->getFieldLayout();
        if (!$fieldLayout) {
            return [];
        }

        $registry = CoPilot::getInstance()->transformerRegistry;

        return array_map(
            fn($resolved) => $resolved['handle'],
            $registry->resolveFieldLayoutFields($fieldLayout),
        );
    }
}
