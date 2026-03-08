<?php

namespace samuelreichor\coPilot\models;

use craft\base\Model;
use samuelreichor\coPilot\enums\ElementCreationBehavior;
use samuelreichor\coPilot\enums\ElementUpdateBehavior;
use samuelreichor\coPilot\enums\Provider;
use samuelreichor\coPilot\enums\SectionAccess;

/**
 * Plugin settings model.
 */
class Settings extends Model
{
    // Provider settings
    public string $activeProvider = 'openai';
    public string $openaiModel = 'gpt-4.1';
    public string $anthropicModel = 'claude-sonnet-4-6';
    public string $geminiModel = 'gemini-2.5-flash';

    // Environment variable names for API keys
    public string $openaiApiKeyEnvVar = 'OPENAI_API_KEY';
    public string $anthropicApiKeyEnvVar = 'ANTHROPIC_API_KEY';
    public string $geminiApiKeyEnvVar = 'GEMINI_API_KEY';

    /**
     * Section access configuration.
     * Maps section UIDs to SectionAccess enum values.
     *
     * @var array<string, string>
     */
    public array $sectionAccess = [];

    /**
     * Plugin data blocklist.
     * List of blocked plugin handles.
     *
     * @var array<int, string>
     */
    public array $blockedPlugins = [
        'formie',
        'freeform',
        'campaign',
    ];

    /**
     * Element type blocklist.
     * List of blocked element type classes.
     *
     * @var array<int, string>
     */
    public array $blockedElementTypes = [
        'craft\commerce\elements\Order',
    ];

    // Brand voice settings
    public string $brandVoice = '';
    public string $glossary = '';
    public string $forbiddenWords = '';

    /**
     * Language-specific instructions.
     *
     * @var array<string, string>
     */
    public array $languageInstructions = [];

    // Web Search
    public bool $webSearchEnabled = false;

    // Debugging
    public bool $debug = false;

    // Agent behavior
    public int $maxAgentIterations = 10;
    public int $defaultSerializationDepth = 2;
    public int $maxSerializationDepth = 4;
    public int $maxContextTokens = 8000;
    public int $defaultSearchLimit = 20;

    // Element persistence behavior
    public string $elementUpdateBehavior = 'provisionalDraft';
    public string $elementCreationBehavior = 'draft';

    // Data retention
    public int $auditLogRetentionDays = 30;

    public function defineRules(): array
    {
        return [
            [['activeProvider', 'openaiModel', 'anthropicModel', 'geminiModel'], 'required'],
            ['activeProvider', 'in', 'range' => array_column(Provider::cases(), 'value')],
            ['maxAgentIterations', 'integer', 'min' => 1, 'max' => 50],
            ['defaultSerializationDepth', 'integer', 'min' => 1, 'max' => 10],
            ['maxSerializationDepth', 'integer', 'min' => 1, 'max' => 10],
            ['maxContextTokens', 'integer', 'min' => 1000, 'max' => 32000],
            ['defaultSearchLimit', 'integer', 'min' => 1, 'max' => 100],
            ['auditLogRetentionDays', 'integer', 'min' => 1, 'max' => 365],
            [['brandVoice', 'glossary', 'forbiddenWords'], 'string'],
            [['openaiApiKeyEnvVar', 'anthropicApiKeyEnvVar', 'geminiApiKeyEnvVar'], 'string'],
            ['webSearchEnabled', 'boolean'],
            ['debug', 'boolean'],
            ['elementUpdateBehavior', 'in', 'range' => array_column(ElementUpdateBehavior::cases(), 'value')],
            ['elementCreationBehavior', 'in', 'range' => array_column(ElementCreationBehavior::cases(), 'value')],
        ];
    }

    /**
     * Returns the access level for a given section UID.
     */
    public function getSectionAccessLevel(string $sectionUid): SectionAccess
    {
        $value = $this->sectionAccess[$sectionUid] ?? SectionAccess::ReadWrite->value;

        return SectionAccess::tryFrom($value) ?? SectionAccess::ReadWrite;
    }

    /**
     * Checks if a plugin handle is blocked.
     */
    public function isPluginBlocked(string $pluginHandle): bool
    {
        return in_array($pluginHandle, $this->blockedPlugins, true);
    }

    /**
     * Checks if an element type class is blocked.
     */
    public function isElementTypeBlocked(string $elementTypeClass): bool
    {
        return in_array($elementTypeClass, $this->blockedElementTypes, true);
    }
}
