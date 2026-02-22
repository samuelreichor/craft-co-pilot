<?php

namespace samuelreichor\coPilot\console\controllers;

use Craft;
use craft\console\Controller;
use craft\elements\Entry;
use craft\elements\User;
use craft\fields\Addresses as AddressesField;
use craft\fields\Assets as AssetsField;
use craft\fields\BaseOptionsField;
use craft\fields\Categories as CategoriesField;
use craft\fields\Color as ColorField;
use craft\fields\ContentBlock as ContentBlockField;
use craft\fields\Date as DateField;
use craft\fields\Entries as EntriesField;
use craft\fields\Lightswitch as LightswitchField;
use craft\fields\Matrix as MatrixField;
use craft\fields\Money as MoneyField;
use craft\fields\Number as NumberField;
use craft\fields\PlainText as PlainTextField;
use craft\fields\Range as RangeField;
use craft\fields\Table as TableField;
use craft\fields\Tags as TagsField;
use craft\fields\Time as TimeField;
use craft\fields\Users as UsersField;
use samuelreichor\coPilot\CoPilot;
use yii\console\ExitCode;

/**
 * Eval commands for exporting prompts and running E2E evaluations.
 */
class EvalController extends Controller
{
    /**
     * @var int|null Entry ID for context-aware export or E2E run.
     */
    public ?int $entryId = null;

    /**
     * @var string Scenario for E2E run: fill, clear, update, or clear-fill.
     */
    public string $scenario = 'fill';

    /**
     * @var string|null Override the active provider (e.g. openai, anthropic, gemini).
     */
    public ?string $provider = null;

    /**
     * @var string|null Override the model (e.g. gpt-4o, claude-sonnet-4-6).
     */
    public ?string $model = null;

    /**
     * @var bool Run eval against all configured providers.
     */
    public bool $all = false;

    public function options($actionID): array
    {
        $options = parent::options($actionID);

        if ($actionID === 'export') {
            $options[] = 'entryId';
        }

        if ($actionID === 'run') {
            $options[] = 'entryId';
            $options[] = 'scenario';
            $options[] = 'provider';
            $options[] = 'model';
            $options[] = 'all';
        }

        return $options;
    }

    /**
     * Exports the current system prompt and tool definitions to evals/generated/.
     */
    public function actionExport(): int
    {
        if (!$this->ensureAdminUser()) {
            return ExitCode::NOPERM;
        }

        $plugin = CoPilot::getInstance();
        $basePath = $this->getEvalsBasePath();

        $this->stdout("Exporting system prompt and tool definitions...\n");

        // Build context entry if provided
        $contextEntry = null;
        if ($this->entryId) {
            $contextEntry = Entry::find()->id($this->entryId)->status(null)->drafts(null)->one();
            if (!$contextEntry) {
                $this->stderr("Entry #{$this->entryId} not found.\n");

                return ExitCode::DATAERR;
            }
            $this->stdout("  Using context entry: \"{$contextEntry->title}\" (ID: {$this->entryId})\n");
        }

        // 1. Build system prompt
        $systemPrompt = $plugin->systemPromptBuilder->build($contextEntry);

        $promptPayload = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => '{{message}}'],
        ];

        $promptPath = $basePath . '/generated/system-prompt.json';
        $this->writeJsonFile($promptPath, $promptPayload);
        $this->stdout("  Written: evals/generated/system-prompt.json\n");

        // 2. Build tool definitions
        $tools = $plugin->agentService->getTools();
        $toolDefs = [];

        foreach ($tools as $tool) {
            $toolDefs[] = [
                'type' => 'function',
                'function' => [
                    'name' => $tool->getName(),
                    'description' => $tool->getDescription(),
                    'parameters' => $tool->getParameters(),
                ],
            ];
        }

        $toolsPath = $basePath . '/generated/tools.json';
        $this->writeJsonFile($toolsPath, $toolDefs);
        $this->stdout("  Written: evals/generated/tools.json\n");

        $this->stdout("\nExport complete. Run 'composer eval' to evaluate.\n");

        return ExitCode::OK;
    }

    /**
     * Runs an E2E evaluation against a real Craft entry.
     */
    public function actionRun(): int
    {
        if (!$this->ensureAdminUser()) {
            return ExitCode::NOPERM;
        }

        if (!$this->entryId) {
            $this->stderr("--entry-id is required for the run action.\n");

            return ExitCode::USAGE;
        }

        $validScenarios = ['fill', 'clear', 'update', 'clear-fill'];
        if (!in_array($this->scenario, $validScenarios, true)) {
            $this->stderr("Invalid scenario '{$this->scenario}'. Valid: " . implode(', ', $validScenarios) . "\n");

            return ExitCode::USAGE;
        }

        $plugin = CoPilot::getInstance();
        $entry = Entry::find()->id($this->entryId)->status(null)->drafts(null)->one();

        if (!$entry) {
            $this->stderr("Entry #{$this->entryId} not found.\n");

            return ExitCode::DATAERR;
        }

        $settings = $plugin->getSettings();

        // Apply provider/model overrides
        if ($this->provider) {
            $providers = $plugin->providerService->getProviders();
            if (!isset($providers[$this->provider])) {
                $this->stderr("Provider '{$this->provider}' not found. Available: " . implode(', ', array_keys($providers)) . "\n");

                return ExitCode::USAGE;
            }
            $settings->activeProvider = $this->provider;
        }

        if ($this->all) {
            return $this->runMatrix($entry);
        }

        $modelProperty = $settings->activeProvider . 'Model';
        $displayModel = $this->model ?? $settings->$modelProperty ?? 'unknown';

        $this->stdout("CoPilot E2E Eval — Scenario: {$this->scenario}\n");
        $this->stdout("Entry: \"{$entry->title}\" (ID: {$entry->id}, Type: {$entry->getType()->handle})\n");
        $this->stdout("Provider: {$settings->activeProvider} ({$displayModel})\n");
        $this->stdout(str_repeat('=', 50) . "\n\n");

        $result = $this->executeScenario($entry);
        if ($result === null) {
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $this->printFieldResults($result);

        return $result['passed'] === $result['total'] ? ExitCode::OK : ExitCode::UNSPECIFIED_ERROR;
    }

    /**
     * Executes the current scenario and returns structured results.
     *
     * @return array{passed: int, total: int, inputTokens: int, outputTokens: int, fieldResults: array<int, array{handle: string, pass: bool, detail: string}>}|null
     */
    private function executeScenario(Entry $entry): ?array
    {
        if ($this->scenario === 'clear-fill') {
            return $this->executeClearFill($entry);
        }

        return $this->executeSingleScenario($entry);
    }

    /**
     * @return array{passed: int, total: int, inputTokens: int, outputTokens: int, fieldResults: array<int, array{handle: string, pass: bool, detail: string}>}|null
     */
    private function executeSingleScenario(Entry $entry): ?array
    {
        $plugin = CoPilot::getInstance();

        $beforeState = $plugin->contextService->serializeEntry($entry);

        $message = $this->getScenarioMessage();
        $this->stdout("Running agent...\n");
        $result = $plugin->agentService->handleMessage($message, $this->entryId, [], $this->model);

        $this->stdout("  Completed ({$result['inputTokens']} input / {$result['outputTokens']} output tokens)\n\n");

        $entry = $this->reloadEntry();
        if (!$entry) {
            $this->stderr("Entry disappeared after agent run.\n");

            return null;
        }

        $afterState = $plugin->contextService->serializeEntry($entry);
        $fieldResults = $this->compareFields($entry, $beforeState, $afterState);
        $passed = count(array_filter($fieldResults, fn($r) => $r['pass']));

        return [
            'passed' => $passed,
            'total' => count($fieldResults),
            'inputTokens' => $result['inputTokens'],
            'outputTokens' => $result['outputTokens'],
            'fieldResults' => $fieldResults,
        ];
    }

    /**
     * @return array{passed: int, total: int, inputTokens: int, outputTokens: int, fieldResults: array<int, array{handle: string, pass: bool, detail: string}>}|null
     */
    private function executeClearFill(Entry $entry): ?array
    {
        $plugin = CoPilot::getInstance();
        $totalInput = 0;
        $totalOutput = 0;

        // Phase 1: Clear
        $this->stdout("Phase 1/2: Clearing all fields...\n");
        $clearResult = $plugin->agentService->handleMessage(
            "Leere alle bearbeitbaren Felder des Eintrags #{$this->entryId}.",
            $this->entryId,
            [],
            $this->model,
        );
        $totalInput += $clearResult['inputTokens'];
        $totalOutput += $clearResult['outputTokens'];
        $this->stdout("  Clear completed ({$clearResult['inputTokens']} input / {$clearResult['outputTokens']} output tokens)\n\n");

        $entry = $this->reloadEntry();
        if (!$entry) {
            $this->stderr("Entry disappeared after clear phase.\n");

            return null;
        }

        // Phase 2: Fill
        $this->stdout("Phase 2/2: Filling all fields...\n");
        $fillMessage = "Fülle ALLE Felder des Eintrags #{$this->entryId} mit sinnvollen, realistischen Testdaten. "
            . 'Nutze die verfügbaren Assets, Entries, Tags und User. Überspringe kein Feld.';
        $fillResult = $plugin->agentService->handleMessage($fillMessage, $this->entryId, [], $this->model);
        $totalInput += $fillResult['inputTokens'];
        $totalOutput += $fillResult['outputTokens'];
        $this->stdout("  Fill completed ({$fillResult['inputTokens']} input / {$fillResult['outputTokens']} output tokens)\n\n");

        $entry = $this->reloadEntry();
        if (!$entry) {
            $this->stderr("Entry disappeared after fill phase.\n");

            return null;
        }

        $afterState = $plugin->contextService->serializeEntry($entry);
        $fieldResults = $this->compareFields($entry, null, $afterState, 'fill');
        $passed = count(array_filter($fieldResults, fn($r) => $r['pass']));

        return [
            'passed' => $passed,
            'total' => count($fieldResults),
            'inputTokens' => $totalInput,
            'outputTokens' => $totalOutput,
            'fieldResults' => $fieldResults,
        ];
    }

    /**
     * Runs the scenario against all configured providers and prints a comparison table.
     */
    private function runMatrix(Entry $entry): int
    {
        $plugin = CoPilot::getInstance();
        $settings = $plugin->getSettings();
        $providers = $plugin->providerService->getProviders();
        $originalProvider = $settings->activeProvider;

        $this->stdout("CoPilot E2E Eval Matrix — Scenario: {$this->scenario}\n");
        $this->stdout("Entry: \"{$entry->title}\" (ID: {$entry->id}, Type: {$entry->getType()->handle})\n");
        $this->stdout(str_repeat('=', 50) . "\n\n");

        /** @var array<int, array{provider: string, model: string, passed: int, total: int, inputTokens: int, outputTokens: int, failed: array<string>}> $matrix */
        $matrix = [];

        foreach ($providers as $handle => $provider) {
            $settings->activeProvider = $handle;
            $modelProperty = $handle . 'Model';
            $model = $settings->$modelProperty ?? 'default';

            $this->stdout(str_repeat('─', 50) . "\n");
            $this->stdout("  {$provider->getName()} ({$model})\n");
            $this->stdout(str_repeat('─', 50) . "\n");

            $result = $this->executeScenario($entry);
            if ($result === null) {
                $matrix[] = [
                    'provider' => $handle,
                    'model' => $model,
                    'passed' => 0,
                    'total' => 0,
                    'inputTokens' => 0,
                    'outputTokens' => 0,
                    'failed' => ['ERROR'],
                ];

                continue;
            }

            $failed = array_map(
                fn($r) => $r['handle'],
                array_filter($result['fieldResults'], fn($r) => !$r['pass']),
            );

            $matrix[] = [
                'provider' => $handle,
                'model' => $model,
                'passed' => $result['passed'],
                'total' => $result['total'],
                'inputTokens' => $result['inputTokens'],
                'outputTokens' => $result['outputTokens'],
                'failed' => array_values($failed),
            ];

            $pct = $result['total'] > 0 ? round(($result['passed'] / $result['total']) * 100, 1) : 0;
            $this->stdout("  Result: {$result['passed']}/{$result['total']} ({$pct}%)\n\n");
        }

        // Restore original provider
        $settings->activeProvider = $originalProvider;

        // Print comparison table
        $this->printComparisonTable($matrix);

        $allPassed = array_reduce($matrix, fn($carry, $r) => $carry && $r['passed'] === $r['total'], true);

        return $allPassed ? ExitCode::OK : ExitCode::UNSPECIFIED_ERROR;
    }

    /**
     * Prints field results for a single run.
     *
     * @param array{passed: int, total: int, inputTokens: int, outputTokens: int, fieldResults: array<int, array{handle: string, pass: bool, detail: string}>} $result
     */
    private function printFieldResults(array $result): void
    {
        $this->stdout("Field Results:\n");
        foreach ($result['fieldResults'] as $r) {
            $status = $r['pass'] ? 'PASS' : 'FAIL';
            $padding = str_repeat('.', max(1, 20 - strlen($r['handle'])));
            $this->stdout("  [{$status}] {$r['handle']} {$padding} {$r['detail']}\n");
        }

        $percentage = $result['total'] > 0 ? round(($result['passed'] / $result['total']) * 100, 1) : 0;
        $this->stdout("\nSummary: {$result['passed']}/{$result['total']} fields passed ({$percentage}%)\n");
    }

    /**
     * Prints a comparison table for matrix runs.
     *
     * @param array<int, array{provider: string, model: string, passed: int, total: int, inputTokens: int, outputTokens: int, failed: array<string>}> $matrix
     */
    private function printComparisonTable(array $matrix): void
    {
        $this->stdout("\n" . str_repeat('=', 70) . "\n");
        $this->stdout("  COMPARISON\n");
        $this->stdout(str_repeat('=', 70) . "\n\n");

        // Header
        $this->stdout(sprintf("  %-25s %-8s %-12s %s\n", 'Provider / Model', 'Score', 'Tokens', 'Failed'));
        $this->stdout('  ' . str_repeat('─', 65) . "\n");

        foreach ($matrix as $row) {
            $pct = $row['total'] > 0 ? round(($row['passed'] / $row['total']) * 100, 1) : 0;
            $score = "{$row['passed']}/{$row['total']}";
            $tokens = number_format($row['inputTokens'] + $row['outputTokens']);
            $failedStr = empty($row['failed']) ? '—' : implode(', ', $row['failed']);
            $label = "{$row['provider']} ({$row['model']})";

            if (mb_strlen($label) > 25) {
                $label = mb_substr($label, 0, 22) . '...';
            }

            $this->stdout(sprintf("  %-25s %-8s %-12s %s\n", $label, $score, $tokens, $failedStr));
        }

        $this->stdout("\n");
    }

    /**
     * Sets the first admin as the current user for permission checks.
     */
    private function ensureAdminUser(): bool
    {
        $admin = User::find()->admin()->one();
        if (!$admin) {
            $this->stderr("No admin user found. Create one first.\n");

            return false;
        }

        Craft::$app->getUser()->setIdentity($admin);
        $this->stdout("Running as: {$admin->email}\n");

        return true;
    }

    private function getScenarioMessage(): string
    {
        return match ($this->scenario) {
            'fill' => "Fülle ALLE Felder des Eintrags #{$this->entryId} mit sinnvollen, realistischen Testdaten. "
                . 'Nutze die verfügbaren Assets, Entries, Tags und User. Überspringe kein Feld.',
            'clear' => "Leere alle bearbeitbaren Felder des Eintrags #{$this->entryId}.",
            default => "Ändere den Titel auf 'Eval Test Title' und den Slug auf 'eval-test-slug'.",
        };
    }

    /**
     * Compares field states and returns pass/fail results per field.
     *
     * @param array<string, mixed>|null $beforeState
     * @param array<string, mixed>|null $afterState
     * @return array<int, array{handle: string, pass: bool, detail: string}>
     */
    private function compareFields(Entry $entry, ?array $beforeState, ?array $afterState, ?string $evalMode = null): array
    {
        $mode = $evalMode ?? $this->scenario;
        $results = [];
        $fieldLayout = $entry->getFieldLayout();

        if (!$fieldLayout) {
            return $results;
        }

        $registry = CoPilot::getInstance()->transformerRegistry;

        foreach ($registry->resolveFieldLayoutFields($fieldLayout) as $resolved) {
            $field = $resolved['field'];
            $handle = $resolved['handle'];
            $value = $entry->getFieldValue($handle);

            // Expand ContentBlock sub-fields for fill scenario
            if ($mode === 'fill' && $field instanceof ContentBlockField) {
                $results = array_merge($results, $this->compareContentBlockFields($field, $value, $handle));

                continue;
            }

            $filled = $this->isFieldFilled($field, $value);

            $result = match ($mode) {
                'fill' => [
                    'handle' => $handle,
                    'pass' => $filled,
                    'detail' => $filled ? $this->describeValue($field, $value) : 'empty',
                ],
                'clear' => [
                    'handle' => $handle,
                    'pass' => !$filled
                        || $this->isUnclearable($field)
                        || (property_exists($field, 'required') && $field->required),
                    'detail' => $this->getClearDetail($field, $filled),
                ],
                default => [
                    'handle' => $handle,
                    'pass' => $this->fieldUnchanged($handle, $beforeState, $afterState),
                    'detail' => $this->fieldUnchanged($handle, $beforeState, $afterState) ? 'unchanged' : 'changed unexpectedly',
                ],
            };

            $results[] = $result;
        }

        return $results;
    }

    /**
     * Expands a ContentBlock into per-sub-field results for the fill scenario.
     *
     * @return array<int, array{handle: string, pass: bool, detail: string}>
     */
    private function compareContentBlockFields(ContentBlockField $field, mixed $value, string $parentHandle): array
    {
        $results = [];
        $registry = CoPilot::getInstance()->transformerRegistry;
        $resolvedSubFields = $registry->resolveFieldLayoutFields($field->getFieldLayout());

        if (!($value instanceof \craft\elements\ContentBlock)) {
            foreach ($resolvedSubFields as $resolved) {
                $results[] = [
                    'handle' => $parentHandle . '.' . $resolved['handle'],
                    'pass' => false,
                    'detail' => 'empty (no content block)',
                ];
            }

            return $results;
        }

        foreach ($resolvedSubFields as $resolved) {
            $subField = $resolved['field'];
            $subHandle = $resolved['handle'];
            $subValue = $value->getFieldValue($subHandle);
            $filled = $this->isFieldFilled($subField, $subValue);
            $results[] = [
                'handle' => $parentHandle . '.' . $subHandle,
                'pass' => $filled,
                'detail' => $filled ? $this->describeValue($subField, $subValue) : 'empty',
            ];
        }

        return $results;
    }

    /**
     * Checks if a field value is considered "filled" based on field type.
     */
    private function isFieldFilled(mixed $field, mixed $value): bool
    {
        if ($field instanceof LightswitchField) {
            return true; // Always considered filled
        }

        if ($field instanceof PlainTextField || str_contains(get_class($field), 'ckeditor')) {
            return !empty($value);
        }

        if ($field instanceof AssetsField || $field instanceof EntriesField
            || $field instanceof CategoriesField || $field instanceof TagsField
            || $field instanceof UsersField || $field instanceof AddressesField
            || $field instanceof MatrixField) {
            return $value->count() > 0;
        }

        if ($field instanceof ColorField) {
            return $value instanceof \craft\fields\data\ColorData;
        }

        if ($field instanceof NumberField) {
            return $value !== null && $value !== 0 && $value !== 0.0;
        }

        if ($field instanceof MoneyField) {
            return $value !== null;
        }

        if ($field instanceof DateField || $field instanceof TimeField) {
            return $value !== null;
        }

        if ($field instanceof RangeField) {
            return $value !== null;
        }

        if ($field instanceof TableField) {
            return is_array($value) && count($value) > 0;
        }

        if ($field instanceof ContentBlockField) {
            if (!($value instanceof \craft\elements\ContentBlock)) {
                return false;
            }
            $registry = CoPilot::getInstance()->transformerRegistry;
            foreach ($registry->resolveFieldLayoutFields($field->getFieldLayout()) as $resolved) {
                $subValue = $value->getFieldValue($resolved['handle']);
                if ($this->isFieldFilled($resolved['field'], $subValue)) {
                    return true;
                }
            }

            return false;
        }

        // Default: check for non-empty
        return !empty($value);
    }

    /**
     * Fields that always carry a value and cannot be "cleared" to empty.
     */
    private function isUnclearable(mixed $field): bool
    {
        if ($field instanceof LightswitchField) {
            return true;
        }

        if ($field instanceof BaseOptionsField) {
            return true;
        }

        return false;
    }

    private function getClearDetail(mixed $field, bool $filled): string
    {
        if (!$filled) {
            return 'cleared';
        }

        if ($this->isUnclearable($field)) {
            return 'skipped (unclearable)';
        }

        if (property_exists($field, 'required') && $field->required) {
            return 'still set (required)';
        }

        return 'still set';
    }

    /**
     * Returns a human-readable description of a field value.
     */
    private function describeValue(mixed $field, mixed $value): string
    {
        if ($field instanceof PlainTextField || str_contains(get_class($field), 'ckeditor')) {
            $text = strip_tags((string)$value);

            return '"' . mb_substr($text, 0, 40) . (mb_strlen($text) > 40 ? '...' : '') . '"';
        }

        if ($field instanceof AssetsField || $field instanceof EntriesField
            || $field instanceof CategoriesField || $field instanceof TagsField
            || $field instanceof UsersField || $field instanceof AddressesField
            || $field instanceof MatrixField) {
            $count = $value->count();

            return "{$count} item(s)";
        }

        if ($field instanceof ColorField && $value instanceof \craft\fields\data\ColorData) {
            return $value->getHex();
        }

        if ($field instanceof NumberField) {
            return (string)$value;
        }

        if ($field instanceof MoneyField && $value instanceof \Money\Money) {
            return $value->getAmount() . ' ' . $value->getCurrency()->getCode();
        }

        if ($field instanceof DateField && $value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if ($field instanceof TimeField && $value instanceof \DateTimeInterface) {
            return $value->format('H:i');
        }

        if ($field instanceof RangeField) {
            return (string)$value;
        }

        if ($field instanceof TableField && is_array($value)) {
            return count($value) . ' row(s)';
        }

        if ($field instanceof LightswitchField) {
            return $value ? 'on' : 'off';
        }

        if ($field instanceof ContentBlockField) {
            return 'set';
        }

        if (is_string($value)) {
            return '"' . mb_substr($value, 0, 40) . '"';
        }

        return 'set';
    }

    /**
     * Checks if a field value is unchanged between before/after state.
     *
     * @param array<string, mixed>|null $beforeState
     * @param array<string, mixed>|null $afterState
     */
    private function fieldUnchanged(string $handle, ?array $beforeState, ?array $afterState): bool
    {
        $before = $beforeState['fields'][$handle] ?? null;
        $after = $afterState['fields'][$handle] ?? null;

        return json_encode($before) === json_encode($after);
    }

    /**
     * Forces a fresh entry load by clearing Craft's element caches.
     */
    private function reloadEntry(): ?Entry
    {
        // Invalidate element query caches so we read from the database
        Craft::$app->getElements()->invalidateCachesForElementType(Entry::class);

        return Entry::find()
            ->id($this->entryId)
            ->status(null)
            ->drafts(null)
            ->one();
    }

    /**
     * @param array<string, mixed> $data
     */
    private function writeJsonFile(string $path, array $data): void
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");
    }

    private function getEvalsBasePath(): string
    {
        return dirname(__DIR__, 3) . '/evals';
    }
}
