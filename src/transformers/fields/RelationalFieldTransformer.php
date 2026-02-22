<?php

namespace samuelreichor\coPilot\transformers\fields;

use Craft;
use craft\base\FieldInterface;
use craft\elements\Address;
use craft\elements\Asset;
use craft\elements\Entry;
use craft\elements\User;
use craft\fields\Addresses as AddressesField;
use craft\fields\Assets as AssetsField;
use craft\fields\BaseRelationField;
use craft\fields\Categories as CategoriesField;
use craft\fields\Entries as EntriesField;
use craft\fields\Tags as TagsField;
use craft\fields\Users as UsersField;
use samuelreichor\coPilot\CoPilot;

/**
 * Handles relational field types: Entries, Assets, Categories, Tags, Users, Addresses.
 */
class RelationalFieldTransformer implements FieldTransformerInterface
{
    public function getSupportedFieldClasses(): array
    {
        return [
            EntriesField::class,
            AssetsField::class,
            CategoriesField::class,
            TagsField::class,
            UsersField::class,
            AddressesField::class,
        ];
    }

    public function matchesField(FieldInterface $field): ?bool
    {
        return null;
    }

    public function describeField(FieldInterface $field, array $fieldInfo): array
    {
        if ($field instanceof AddressesField) {
            $fieldInfo['valueFormat'] = 'array of address objects';
            $fieldInfo['maxAddresses'] = $field->maxAddresses;
            $fieldInfo['nativeFields'] = $this->describeAddressNativeFields();

            return $fieldInfo;
        }

        if ($field instanceof BaseRelationField) {
            if ($field->maxRelations) {
                $fieldInfo['maxItems'] = $field->maxRelations;
            }
        }

        if ($field instanceof AssetsField) {
            $fieldInfo['valueFormat'] = 'array of asset IDs';
            $hint = 'Use searchAssets to find IDs.';
            if ($field->maxRelations === 1) {
                $hint = 'Single asset. ' . $hint;
            }
            $fieldInfo['hint'] = $hint;

            $allowedSources = $this->describeAllowedSources($field, 'volume');
            if ($allowedSources !== null) {
                $fieldInfo['allowedSources'] = $allowedSources;
            }
        } elseif ($field instanceof EntriesField) {
            $fieldInfo['valueFormat'] = 'array of entry IDs';
            $hint = 'Use searchEntries to find IDs.';
            if ($field->maxRelations === 1) {
                $hint = 'Single entry. ' . $hint;
            }
            $fieldInfo['hint'] = $hint;

            $allowedSources = $this->describeAllowedSources($field, 'section');
            if ($allowedSources !== null) {
                $fieldInfo['allowedSources'] = $allowedSources;
            }
        } elseif ($field instanceof TagsField) {
            $fieldInfo['valueFormat'] = 'array of tag IDs';
            $fieldInfo['hint'] = 'Use searchTags to find IDs.';
        } elseif ($field instanceof UsersField) {
            $fieldInfo['valueFormat'] = 'array of user IDs';
            $fieldInfo['hint'] = 'Use searchUsers to find IDs.';
        } elseif ($field instanceof CategoriesField) {
            $fieldInfo['valueFormat'] = 'array of category IDs';
            $fieldInfo['hint'] = 'Use searchCategories to find IDs.';

            if ($field->source && str_starts_with($field->source, 'group:')) {
                $uid = substr($field->source, 6);
                $group = Craft::$app->getCategories()->getGroupByUid($uid);
                if ($group) {
                    $fieldInfo['categoryGroup'] = $group->handle;
                }
            }
        }

        return $fieldInfo;
    }

    public function serializeValue(FieldInterface $field, mixed $value, int $depth): mixed
    {
        if ($field instanceof EntriesField) {
            if ($depth <= 0) {
                return ['_truncated' => true, '_count' => $value->count()];
            }

            $contextService = CoPilot::getInstance()->contextService;

            return array_map(
                fn(Entry $related) => $contextService->serializeEntry($related, $depth - 1),
                $value->all(),
            );
        }

        if ($field instanceof CategoriesField || $field instanceof TagsField) {
            return array_map(fn($item) => [
                '_type' => $field instanceof CategoriesField ? 'category' : 'tag',
                'id' => $item->id,
                'title' => $item->title,
                'slug' => $item->slug,
            ], $value->all());
        }

        if ($field instanceof AssetsField) {
            $contextService = CoPilot::getInstance()->contextService;

            return array_map(
                fn(Asset $asset) => $contextService->serializeAsset($asset),
                $value->all(),
            );
        }

        if ($field instanceof UsersField) {
            return array_map(fn(User $user) => [
                '_type' => 'user',
                'id' => $user->id,
                'name' => $user->fullName ?? $user->username,
                'email' => $user->email,
            ], $value->all());
        }

        if ($field instanceof AddressesField) {
            return array_map(fn(Address $addr) => [
                '_type' => 'address',
                'id' => $addr->id,
                'addressLine1' => $addr->addressLine1,
                'addressLine2' => $addr->addressLine2,
                'locality' => $addr->locality,
                'postalCode' => $addr->postalCode,
                'countryCode' => $addr->countryCode,
            ], $value->all());
        }

        return $value;
    }

    public function normalizeValue(FieldInterface $field, mixed $value, ?Entry $entry = null): mixed
    {
        if ($field instanceof AddressesField && is_array($value)) {
            return $this->normalizeAddressesValue($value);
        }

        // For other relational fields: extract IDs from object format
        if ($field instanceof BaseRelationField && is_array($value)) {
            return $this->normalizeRelationalIds($value);
        }

        return null;
    }

    /**
     * Extracts element IDs from mixed formats (plain IDs or objects with 'id' key).
     * Weak AI models may echo back full serialized objects instead of plain IDs.
     *
     * @param array<int, mixed> $value
     * @return array<int, int>
     */
    private function normalizeRelationalIds(array $value): array
    {
        $ids = [];

        foreach ($value as $item) {
            if (is_int($item)) {
                $ids[] = $item;
            } elseif (is_string($item) && ctype_digit($item)) {
                $ids[] = (int) $item;
            } elseif (is_array($item) && isset($item['id'])) {
                $ids[] = (int) $item['id'];
            }
        }

        return $ids;
    }

    /**
     * Converts an AI-friendly indexed array of address objects into Craft's keyed format.
     *
     * @param array<int|string, mixed> $value
     * @return array<string, mixed>
     */
    private function normalizeAddressesValue(array $value): array
    {
        $firstKey = array_key_first($value);
        if (is_string($firstKey)) {
            return $this->ensureAddressTitles($value);
        }

        $result = [];
        $index = 1;
        foreach ($value as $address) {
            if (is_array($address)) {
                $result['new' . $index++] = $address;
            }
        }

        return $this->ensureAddressTitles($result);
    }

    /**
     * @param array<string, mixed> $addresses
     * @return array<string, mixed>
     */
    private function ensureAddressTitles(array $addresses): array
    {
        if (!$this->isAddressTitleRequired()) {
            return $addresses;
        }

        $index = 1;
        foreach ($addresses as $key => $address) {
            if (!is_array($address) || !empty($address['title'])) {
                continue;
            }

            $addresses[$key]['title'] = $address['addressLine1'] ?? ('Address ' . $index);
            $index++;
        }

        return $addresses;
    }

    private function isAddressTitleRequired(): bool
    {
        try {
            $fieldLayout = Craft::$app->getAddresses()->getFieldLayout();
            $address = new Address();
            $labelField = $fieldLayout->getFirstVisibleElementByType(
                \craft\fieldlayoutelements\addresses\LabelField::class,
                $address,
            );

            return $labelField !== null && $labelField->required;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Resolves allowed source handles from a relational field's sources config.
     *
     * @return array<int, string>|null Resolved handles, or null if all sources are allowed.
     */
    private function describeAllowedSources(FieldInterface $field, string $sourceType): ?array
    {
        if (!$field instanceof BaseRelationField) {
            return null;
        }

        $sources = $field->sources;

        if ($sources === '*' || $sources === null) {
            return null;
        }

        if (!is_array($sources)) {
            return null;
        }

        $handles = [];
        $prefix = $sourceType . ':';

        foreach ($sources as $source) {
            if (!is_string($source) || !str_starts_with($source, $prefix)) {
                continue;
            }

            $uid = substr($source, strlen($prefix));

            if ($sourceType === 'section') {
                $resolved = Craft::$app->getEntries()->getSectionByUid($uid);
            } elseif ($sourceType === 'volume') {
                $resolved = Craft::$app->getVolumes()->getVolumeByUid($uid);
            } else {
                continue;
            }

            if ($resolved) {
                $handles[] = $resolved->handle;
            }
        }

        return $handles !== [] ? $handles : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function describeAddressNativeFields(): array
    {
        $fieldLayout = Craft::$app->getAddresses()->getFieldLayout();
        $address = new Address();
        $fields = [];

        $fields[] = ['attribute' => 'countryCode', 'required' => true];
        $fields[] = ['attribute' => 'addressLine1', 'required' => true];

        $nativeFieldTypes = [
            \craft\fieldlayoutelements\addresses\LabelField::class => 'title',
            \craft\fieldlayoutelements\FullNameField::class => 'fullName',
            \craft\fieldlayoutelements\addresses\OrganizationField::class => 'organization',
            \craft\fieldlayoutelements\addresses\OrganizationTaxIdField::class => 'organizationTaxId',
            \craft\fieldlayoutelements\addresses\LatLongField::class => 'latLong',
        ];

        foreach ($nativeFieldTypes as $class => $attribute) {
            $layoutElement = $fieldLayout->getFirstVisibleElementByType($class, $address);

            if ($layoutElement === null) {
                continue;
            }

            $info = ['attribute' => $attribute];

            if ($layoutElement->required) {
                $info['required'] = true;
            }

            $fields[] = $info;
        }

        return $fields;
    }
}
