<?php

namespace samuelreichor\coPilot\tools;

use craft\elements\Asset;
use samuelreichor\coPilot\CoPilot;

class ReadAssetTool implements ToolInterface
{
    public function getName(): string
    {
        return 'readAsset';
    }

    public function getDescription(): string
    {
        return 'Reads information about a Craft asset (image, file). Returns metadata and URL.';
    }

    public function getParameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'assetId' => [
                    'type' => 'integer',
                    'description' => 'The Craft asset ID',
                ],
            ],
            'required' => ['assetId'],
        ];
    }

    public function execute(array $arguments): array
    {
        $assetId = $arguments['assetId'];

        $plugin = CoPilot::getInstance();

        // Permission check
        $guard = $plugin->permissionGuard->canReadAsset($assetId);
        if (!$guard['allowed']) {
            return ['error' => $guard['reason']];
        }

        $asset = Asset::find()->id($assetId)->one();
        if (!$asset) {
            return ['error' => "Asset #{$assetId} not found."];
        }

        return $plugin->contextService->serializeAsset($asset);
    }
}
