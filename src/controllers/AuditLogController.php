<?php

namespace samuelreichor\coPilot\controllers;

use Craft;
use craft\helpers\AdminTable;
use craft\helpers\Cp;
use craft\helpers\UrlHelper;
use craft\web\Controller;
use samuelreichor\coPilot\constants\Constants;
use samuelreichor\coPilot\CoPilot;
use samuelreichor\coPilot\helpers\Logger;
use yii\web\Response;

class AuditLogController extends Controller
{
    protected array|bool|int $allowAnonymous = false;

    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        $this->requirePermission(Constants::PERMISSION_VIEW_AUDIT_LOG);

        return true;
    }

    /**
     * GET /admin/co-pilot/audit-log
     */
    public function actionIndex(): Response
    {
        return $this->renderTemplate('co-pilot/audit-log/index', [
            'selectedSite' => Cp::requestedSite(),
        ]);
    }

    /**
     * GET /actions/co-pilot/audit-log/table-data
     */
    public function actionTableData(): Response
    {
        $this->requireAcceptsJson();

        $request = $this->request;
        $page = (int) ($request->getParam('page') ?? 1);
        $perPage = (int) ($request->getParam('per_page') ?? 25);
        $search = $request->getParam('search');
        $siteId = $request->getParam('siteId') ? (int) $request->getParam('siteId') : null;

        $sortField = $request->getParam('sort.0.field');
        $sortDir = ($request->getParam('sort.0.direction') ?? 'desc') === 'asc' ? SORT_ASC : SORT_DESC;

        $orderBy = match ($sortField) {
            'dateCreated' => 'a.dateCreated',
            'user' => 'u.fullName',
            'action' => 'a.action',
            'tool' => 'a.toolName',
            '__slot:title' => 'a.elementId',
            default => 'a.dateCreated',
        };

        $action = $request->getParam('action');

        $result = CoPilot::getInstance()->auditService->getWriteLogs($page, $perPage, $search, $siteId, [$orderBy => $sortDir], $action);

        $tableData = array_map(fn(array $item) => $this->formatRow($item), $result['items']);

        return $this->asSuccess(data: [
            'pagination' => AdminTable::paginationLinks($page, $result['total'], $perPage),
            'data' => $tableData,
        ]);
    }

    /**
     * Formats a single audit log row for VueAdminTable.
     *
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    private function formatRow(array $item): array
    {
        $details = $item['details'] ?? [];
        $summary = $details['resultSummary'] ?? [];

        $entryTitle = $summary['entryTitle'] ?? null;
        $cpEditUrl = $summary['cpEditUrl'] ?? null;

        $userUrl = UrlHelper::cpUrl('users/' . $item['userId']);
        $userName = $item['fullName'] ?: $item['username'];

        $entry = $this->buildEntryCell($entryTitle, $cpEditUrl, (int) ($item['elementId'] ?? 0));
        $conversationId = $item['conversationId'] ? (int) $item['conversationId'] : null;

        return [
            'id' => (int) $item['id'],
            'title' => $entry['title'],
            'url' => $entry['url'],
            'user' => [
                'name' => $userName,
                'url' => $userUrl,
            ],
            'action' => $item['action'],
            'tool' => $this->toolLabel($item['toolName']),
            'status' => $item['status'],
            'conversation' => $conversationId
                ? ['id' => $conversationId, 'url' => UrlHelper::cpUrl('co-pilot/' . $conversationId)]
                : null,
            'dateCreated' => $item['dateCreated'],
            'detail' => $this->buildDetailCell($summary, $item),
        ];
    }

    /**
     * @return array{title: string, url: string|null}
     */
    private function buildEntryCell(?string $entryTitle, ?string $cpEditUrl, int $elementId): array
    {
        if ($entryTitle && $cpEditUrl) {
            return ['title' => $entryTitle, 'url' => $cpEditUrl];
        }

        if ($elementId) {
            $entry = Craft::$app->getElements()->getElementById($elementId);

            if ($entry) {
                return ['title' => (string) $entry, 'url' => $entry->getCpEditUrl()];
            }
        }

        return ['title' => '—', 'url' => null];
    }

    /**
     * @param array<string, mixed> $summary
     * @param array<string, mixed> $item
     * @return array{handle: string, content: string}
     */
    private function buildDetailCell(array $summary, array $item): array
    {
        $diff = $summary['diff'] ?? null;
        $error = $summary['error'] ?? null;
        $fieldHandle = $item['fieldHandle'] ?? null;

        $html = '';

        if ($error) {
            $html .= '<div class="co-pilot-audit-error"><strong>Error:</strong> '
                . htmlspecialchars($error) . '</div>';
        }

        if ($fieldHandle && $item['toolName'] === 'updateField') {
            $html .= '<div class="co-pilot-audit-meta"><strong>Field:</strong> '
                . htmlspecialchars($fieldHandle) . '</div>';
        }

        if (is_array($diff) && $diff !== []) {
            foreach ($diff as $handle => $fieldDiff) {
                if (!is_array($fieldDiff)) {
                    continue;
                }

                $old = $fieldDiff['old'] ?? null;
                $new = $fieldDiff['new'] ?? null;

                if ($handle !== '_value') {
                    $html .= '<div class="co-pilot-audit-field-name">'
                        . htmlspecialchars((string) $handle) . '</div>';
                }

                $html .= $this->renderDiffHtml($old, $new);
            }
        } elseif (!$error) {
            $html .= '<div class="co-pilot-audit-no-diff">'
                . '<em>No diff data available. Only entries logged after the audit update contain diff data.</em></div>';
        }

        return [
            'handle' => '<span data-icon="info" aria-label="Details"></span>',
            'content' => '<div class="co-pilot-audit-detail">' . $html . '</div>',
        ];
    }

    private function renderDiffHtml(?string $old, ?string $new): string
    {
        $isCreation = $old === null || $old === '';
        $isDeletion = $new === null || $new === '';

        if ($isCreation && !$isDeletion) {
            return '<div class="co-pilot-diff-single co-pilot-diff-single--added">'
                . '<div class="co-pilot-diff-header">New value</div>'
                . '<pre class="co-pilot-diff-content">' . htmlspecialchars((string) $new) . '</pre>'
                . '</div>';
        }

        if ($isDeletion && !$isCreation) {
            return '<div class="co-pilot-diff-single co-pilot-diff-single--removed">'
                . '<div class="co-pilot-diff-header">Removed value</div>'
                . '<pre class="co-pilot-diff-content">' . htmlspecialchars((string) $old) . '</pre>'
                . '</div>';
        }

        return '<div class="co-pilot-diff-split">'
            . '<div class="co-pilot-diff-side co-pilot-diff-side--old">'
            . '<div class="co-pilot-diff-header">Before</div>'
            . '<pre class="co-pilot-diff-content">' . htmlspecialchars((string) $old) . '</pre>'
            . '</div>'
            . '<div class="co-pilot-diff-side co-pilot-diff-side--new">'
            . '<div class="co-pilot-diff-header">After</div>'
            . '<pre class="co-pilot-diff-content">' . htmlspecialchars((string) $new) . '</pre>'
            . '</div>'
            . '</div>';
    }

    private function toolLabel(string $toolName): string
    {
        try {
            $tools = CoPilot::getInstance()->agentService->getTools();

            if (isset($tools[$toolName])) {
                return $tools[$toolName]->getLabel();
            }
        } catch (\Throwable $e) {
            Logger::error("Failed to load tool label for '{$toolName}': {$e->getMessage()}");
        }

        return $toolName;
    }
}
