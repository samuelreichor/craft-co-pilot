<?php

namespace samuelreichor\coPilot\services;

use Craft;
use craft\base\Component;
use craft\elements\Asset;
use craft\elements\Entry;
use samuelreichor\coPilot\CoPilot;
use samuelreichor\coPilot\enums\SectionAccess;
use yii\base\InvalidConfigException;

/**
 * Validates every tool call against Craft permissions and the plugin blocklist.
 */
class PermissionGuard extends Component
{
    /**
     * Checks if the current user can read the given entry.
     *
     * @return array{allowed: bool, reason: string|null}
     */
    public function canReadEntry(int $entryId): array
    {
        $entry = $this->findEntry($entryId);
        if (!$entry) {
            return $this->denied("Entry #{$entryId} not found.");
        }

        // For nested entries (Matrix blocks), check the root owner's permissions
        $rootEntry = $this->resolveRootEntry($entry);

        return $this->checkReadAccess($rootEntry);
    }

    /**
     * Checks if the current user can write to the given entry.
     *
     * @return array{allowed: bool, reason: string|null}
     */
    public function canWriteEntry(int $entryId): array
    {
        $entry = $this->findEntry($entryId);
        if (!$entry) {
            return $this->denied("Entry #{$entryId} not found.");
        }

        // For nested entries (Matrix blocks), resolve the root owner entry
        $rootEntry = $this->resolveRootEntry($entry);

        $readCheck = $this->checkReadAccess($rootEntry);
        if (!$readCheck['allowed']) {
            return $readCheck;
        }

        // Check read-only restriction from blocklist
        $section = $rootEntry->getSection();
        $settings = CoPilot::getInstance()->getSettings();
        $access = $settings->getSectionAccessLevel($section->uid);

        if ($access === SectionAccess::ReadOnly) {
            return $this->denied(
                "Access denied – the section '{$section->name}' is configured as read-only. "
                . 'You can read entries in this section but not modify them.'
            );
        }

        // Check Craft write permission
        $user = Craft::$app->getUser()->getIdentity();
        if (!$user) {
            return $this->denied('Access denied – no authenticated user.');
        }

        if (!$user->can("saveEntries:{$section->uid}")) {
            return $this->denied(
                "Access denied – you lack the 'Save Entries' permission for the section '{$section->name}'. "
                . 'Contact an admin to request access.'
            );
        }

        return $this->allowed();
    }

    /**
     * Checks if the current user can read the given asset.
     *
     * @return array{allowed: bool, reason: string|null}
     */
    public function canReadAsset(int $assetId): array
    {
        $asset = Asset::find()->id($assetId)->one();
        if (!$asset) {
            return $this->denied("Asset #{$assetId} not found.");
        }

        $settings = CoPilot::getInstance()->getSettings();
        if ($settings->isElementTypeBlocked(Asset::class)) {
            return $this->denied('Access denied – asset access is blocked by the data protection settings.');
        }

        $user = Craft::$app->getUser()->getIdentity();
        if (!$user) {
            return $this->denied('Access denied – no authenticated user.');
        }

        $volume = $asset->getVolume();
        if (!$user->can("viewAssets:{$volume->uid}")) {
            return $this->denied(
                "Access denied – you lack the 'View Assets' permission for the volume '{$volume->name}'. "
                . 'Contact an admin to request access.'
            );
        }

        return $this->allowed();
    }

    /**
     * Checks if a section is accessible for reading.
     *
     * @return array{allowed: bool, reason: string|null}
     */
    public function canReadSection(string $sectionUid): array
    {
        $settings = CoPilot::getInstance()->getSettings();
        $access = $settings->getSectionAccessLevel($sectionUid);

        if ($access === SectionAccess::Blocked) {
            return $this->denied('Access denied – this section is blocked by the data protection settings.');
        }

        $user = Craft::$app->getUser()->getIdentity();
        if (!$user) {
            return $this->denied('Access denied – no authenticated user.');
        }

        if (!$user->can("viewEntries:{$sectionUid}")) {
            return $this->denied(
                'Access denied – you lack the \'View Entries\' permission for this section. '
                . 'Contact an admin to request access.'
            );
        }

        return $this->allowed();
    }

    /**
     * For nested entries (Matrix blocks), walks up the owner chain to the root section entry.
     */
    private function resolveRootEntry(Entry $entry): Entry
    {
        $current = $entry;

        while ($current->primaryOwnerId !== null) {
            $owner = Entry::find()
                ->id($current->primaryOwnerId)
                ->status(null)
                ->drafts(null)
                ->one();

            if (!$owner) {
                break;
            }

            $current = $owner;
        }

        return $current;
    }

    private function findEntry(int $entryId): ?Entry
    {
        return Entry::find()
            ->id($entryId)
            ->site('*')
            ->status(null)
            ->drafts(null)
            ->one();
    }

    /**
     * @return array{allowed: bool, reason: string|null}
     * @throws InvalidConfigException
     */
    private function checkReadAccess(Entry $entry): array
    {
        $settings = CoPilot::getInstance()->getSettings();

        // Check element type blocklist
        if ($settings->isElementTypeBlocked(get_class($entry))) {
            return $this->denied('Access denied – this element type is blocked by the data protection settings.');
        }

        // Check section blocklist
        $section = $entry->getSection();
        $access = $settings->getSectionAccessLevel($section->uid);

        if ($access === SectionAccess::Blocked) {
            return $this->denied(
                "Access denied – the section '{$section->name}' is blocked by the data protection settings."
            );
        }

        // Check Craft user permission
        $user = Craft::$app->getUser()->getIdentity();
        if (!$user) {
            return $this->denied('Access denied – no authenticated user.');
        }

        if (!$user->can("viewEntries:{$section->uid}")) {
            return $this->denied(
                "Access denied – you lack the 'View Entries' permission for the section '{$section->name}'. "
                . 'Contact an admin to request access.'
            );
        }

        return $this->allowed();
    }

    /**
     * @return array{allowed: true, reason: null}
     */
    private function allowed(): array
    {
        return ['allowed' => true, 'reason' => null];
    }

    /**
     * @return array{allowed: false, reason: string}
     */
    private function denied(string $reason): array
    {
        return ['allowed' => false, 'reason' => $reason];
    }
}
