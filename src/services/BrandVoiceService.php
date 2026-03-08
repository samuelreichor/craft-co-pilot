<?php

namespace samuelreichor\coPilot\services;

use Craft;
use craft\base\Component;
use craft\db\Query;
use craft\helpers\StringHelper;
use Exception;
use samuelreichor\coPilot\constants\Constants;

/**
 * Manages per-site brand voice settings.
 */
class BrandVoiceService extends Component
{
    /**
     * @return array{brandVoice: string, glossary: string, forbiddenWords: string, languageInstructions: string}
     */
    public function getBySiteId(int $siteId): array
    {
        $row = (new Query())
            ->from(Constants::TABLE_BRAND_VOICE)
            ->where(['siteId' => $siteId])
            ->one();

        return [
            'brandVoice' => $row['brandVoice'] ?? '',
            'glossary' => $row['glossary'] ?? '',
            'forbiddenWords' => $row['forbiddenWords'] ?? '',
            'languageInstructions' => $row['languageInstructions'] ?? '',
        ];
    }

    /**
     * @param array{brandVoice?: string, glossary?: string, forbiddenWords?: string, languageInstructions?: string} $data
     * @throws Exception
     */
    public function saveBySiteId(int $siteId, array $data): void
    {
        $db = Craft::$app->getDb();

        $existing = (new Query())
            ->from(Constants::TABLE_BRAND_VOICE)
            ->where(['siteId' => $siteId])
            ->one();

        $columns = [
            'brandVoice' => $data['brandVoice'] ?? '',
            'glossary' => $data['glossary'] ?? '',
            'forbiddenWords' => $data['forbiddenWords'] ?? '',
            'languageInstructions' => $data['languageInstructions'] ?? '',
        ];

        if ($existing) {
            $columns['dateUpdated'] = Craft::$app->getFormatter()->asDatetime('now', 'php:Y-m-d H:i:s');

            $db->createCommand()->update(
                Constants::TABLE_BRAND_VOICE,
                $columns,
                ['siteId' => $siteId],
            )->execute();
        } else {
            $columns['siteId'] = $siteId;
            $columns['dateCreated'] = Craft::$app->getFormatter()->asDatetime('now', 'php:Y-m-d H:i:s');
            $columns['dateUpdated'] = $columns['dateCreated'];
            $columns['uid'] = StringHelper::UUID();

            $db->createCommand()->insert(
                Constants::TABLE_BRAND_VOICE,
                $columns,
            )->execute();
        }
    }
}
