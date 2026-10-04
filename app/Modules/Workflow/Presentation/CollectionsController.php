<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Workflow\Presentation;

use HaHireAI\Core\Contracts\AuditRecorder;
use HaHireAI\Core\Http\Request;
use HaHireAI\Core\Http\Response;
use HaHireAI\Core\Http\Session;
use HaHireAI\Modules\Authentication\Application\AuthContext;
use HaHireAI\Modules\Workflow\Application\WorkflowCollectionService;
use HaHireAI\Modules\Workspaces\Application\WorkspaceContext;
use HaHireAI\Modules\Workspaces\Presentation\WorkspaceShell;

/**
 * Dynamic Collections UI — a simple per-workspace "database" the no-code Database
 * nodes read/write, with manual add/edit and CSV export. No raw SQL, no new tables
 * (records are JSON rows via {@see WorkflowCollectionService}).
 */
final class CollectionsController
{
    public function __construct(
        private readonly WorkspaceShell $shell,
        private readonly WorkspaceContext $context,
        private readonly AuthContext $auth,
        private readonly WorkflowCollectionService $collections,
        private readonly Session $session,
        private readonly AuditRecorder $audit,
    ) {
    }

    public function index(): Response
    {
        if (($r = $this->gate('workflow.view')) !== null) {
            return $r;
        }

        return $this->shell->render($this->context, 'collections.index', [
            'collections' => $this->collections->listCollections((string) $this->context->workspaceId()),
            'canManage' => $this->context->can('workflow.create'),
            'status' => $this->session->pullFlash('status'),
        ]);
    }

    public function create(Request $request): Response
    {
        if (($r = $this->gate('workflow.create', $request)) !== null) {
            return $r;
        }

        $name = trim((string) $request->input('name', ''));
        if ($name === '') {
            $this->session->flash('status', 'Give the collection a name.');

            return Response::redirect('/collections');
        }

        $id = $this->collections->createCollection(
            (string) $this->context->workspaceId(),
            $name,
            $this->parseFields((string) $request->input('fields', '')),
            $this->context->userId(),
        );

        $this->audit->record('workflows.collection.created', [
            'workspace_id' => $this->context->workspaceId(),
            'actor_user_id' => $this->context->userId(),
            'entity_type' => 'collection',
            'entity_id' => $id,
            'changes' => ['name' => $name],
        ]);
        $this->session->flash('status', "Collection “{$name}” created.");

        return Response::redirect('/collections/' . $id);
    }

    public function show(string $id): Response
    {
        if (($r = $this->gate('workflow.view')) !== null) {
            return $r;
        }

        $workspaceId = (string) $this->context->workspaceId();
        $collection = $this->collections->findById($workspaceId, $id);
        if ($collection === null) {
            return Response::redirect('/collections');
        }

        return $this->shell->render($this->context, 'collections.show', [
            'collection' => $collection,
            'records' => $this->collections->listRecords($workspaceId, $id),
            'canManage' => $this->context->can('workflow.create'),
            'status' => $this->session->pullFlash('status'),
        ]);
    }

    public function addRecord(Request $request, string $id): Response
    {
        if (($r = $this->gate('workflow.create', $request)) !== null) {
            return $r;
        }

        $workspaceId = (string) $this->context->workspaceId();
        $collection = $this->collections->findById($workspaceId, $id);
        if ($collection === null) {
            return Response::redirect('/collections');
        }

        $data = [];
        foreach ($collection['fields'] as $field) {
            $key = (string) ($field['key'] ?? '');
            if ($key !== '') {
                $data[$key] = (string) $request->input('f_' . $key, '');
            }
        }
        $this->collections->createRecord($workspaceId, $id, $data, $this->context->userId());
        $this->session->flash('status', 'Record added.');

        return Response::redirect('/collections/' . $id);
    }

    public function deleteRecord(Request $request, string $id, string $recordId): Response
    {
        if (($r = $this->gate('workflow.create', $request)) !== null) {
            return $r;
        }

        $this->collections->deleteRecord((string) $this->context->workspaceId(), $recordId);
        $this->session->flash('status', 'Record removed.');

        return Response::redirect('/collections/' . $id);
    }

    /** Download the collection as a CSV file — the "report" / export. */
    public function export(string $id): Response
    {
        if (($r = $this->gate('workflow.view')) !== null) {
            return $r;
        }

        $workspaceId = (string) $this->context->workspaceId();
        $collection = $this->collections->findById($workspaceId, $id);
        if ($collection === null) {
            return Response::redirect('/collections');
        }

        $records = $this->collections->listRecords($workspaceId, $id);

        // Columns: declared fields, else the union of keys across records.
        $keys = [];
        foreach ($collection['fields'] as $field) {
            if (($field['key'] ?? '') !== '') {
                $keys[(string) $field['key']] = (string) ($field['label'] ?? $field['key']);
            }
        }
        if ($keys === []) {
            foreach ($records as $rec) {
                foreach (array_keys($rec['data']) as $k) {
                    $keys[$k] = $k;
                }
            }
        }

        $fh = fopen('php://temp', 'r+');
        fputcsv($fh, array_values($keys));
        foreach ($records as $rec) {
            $row = [];
            foreach (array_keys($keys) as $k) {
                $v = $rec['data'][$k] ?? '';
                $row[] = is_scalar($v) ? (string) $v : (string) json_encode($v);
            }
            fputcsv($fh, $row);
        }
        rewind($fh);
        $csv = (string) stream_get_contents($fh);
        fclose($fh);

        return Response::make($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . ((string) ($collection['key'] ?? 'collection')) . '.csv"',
        ]);
    }

    /**
     * Parse the simple fields box (one per line: "Label" or "Label : type").
     *
     * @return list<array{key:string,label:string,type:string}>
     */
    private function parseFields(string $raw): array
    {
        $out = [];
        foreach (preg_split('/[\r\n,]+/', $raw) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            [$label, $type] = array_pad(explode(':', $line, 2), 2, 'text');
            $label = trim($label);
            $key = strtolower(trim((string) preg_replace('/[^a-zA-Z0-9]+/', '_', $label), '_'));
            if ($key !== '') {
                $out[] = ['key' => $key, 'label' => $label, 'type' => trim($type) ?: 'text'];
            }
        }

        return $out;
    }

    private function gate(string $permission, ?Request $request = null): ?Response
    {
        if (! $this->auth->check()) {
            return Response::redirect('/login');
        }
        if (! $this->context->resolve()) {
            return Response::redirect('/dashboard');
        }
        if (! $this->context->can($permission)) {
            return Response::html('<h1>403</h1><p>You do not have permission.</p>', 403);
        }
        if ($request !== null && ! $this->session->verifyCsrf((string) $request->input('_csrf'))) {
            return Response::html('<h1>419</h1><p>Security check failed.</p>', 419);
        }

        return null;
    }
}
