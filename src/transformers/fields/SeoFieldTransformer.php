<?php

namespace samuelreichor\coPilot\transformers\fields;

use craft\base\FieldInterface;
use craft\elements\Entry;

/**
 * Handles nystudio107 SEOmatic SeoSettings fields.
 *
 * The field value is a MetaBundle object with metaGlobalVars containing
 * seoTitle, seoDescription, seoKeywords, seoImage, and social overrides.
 */
class SeoFieldTransformer implements FieldTransformerInterface
{
    public function getSupportedFieldClasses(): array
    {
        return [];
    }

    public function matchesField(FieldInterface $field): ?bool
    {
        $class = get_class($field);

        if ($class === 'nystudio107\seomatic\fields\SeoSettings') {
            return true;
        }

        return null;
    }

    public function describeField(FieldInterface $field, array $fieldInfo): array
    {
        $fieldInfo['hint'] = 'SEO metadata (SEOmatic). Object with keys: "seoTitle", "seoDescription", '
            . '"seoKeywords", "seoImage" (URL), "canonicalUrl", "robots", '
            . '"ogTitle", "ogDescription", "ogImage", "twitterTitle", "twitterDescription", "twitterImage". '
            . 'Only include keys you want to override.';

        return $fieldInfo;
    }

    public function serializeValue(FieldInterface $field, mixed $value, int $depth): mixed
    {
        if (!is_object($value)) {
            return $value;
        }

        // MetaBundle → extract metaGlobalVars
        if (!property_exists($value, 'metaGlobalVars') || $value->metaGlobalVars === null) {
            return null;
        }

        $vars = $value->metaGlobalVars;
        $data = [];

        $keys = [
            'seoTitle',
            'seoDescription',
            'seoKeywords',
            'seoImage',
            'canonicalUrl',
            'robots',
            'ogTitle',
            'ogDescription',
            'ogImage',
            'twitterTitle',
            'twitterDescription',
            'twitterImage',
        ];

        foreach ($keys as $key) {
            if (property_exists($vars, $key) && $vars->{$key} !== null && $vars->{$key} !== '') {
                $data[$key] = $vars->{$key};
            }
        }

        return $data !== [] ? $data : null;
    }

    public function normalizeValue(FieldInterface $field, mixed $value, ?Entry $entry = null): mixed
    {
        return null;
    }
}
