<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Recruitment\Presentation;

use HaHireAI\Core\Contracts\AuditRecorder;
use HaHireAI\Core\Http\Request;
use HaHireAI\Core\Http\Response;
use HaHireAI\Core\Http\Session;
use HaHireAI\Modules\Authentication\Application\AuthContext;
use HaHireAI\Modules\Recruitment\Application\SegmentService;
use HaHireAI\Modules\Recruitment\Application\TalentPoolService;
use HaHireAI\Modules\Recruitment\Domain\TalentSegment\SegmentField;
use HaHireAI\Modules\Workspaces\Application\WorkspaceContext;
use HaHireAI\Modules\Workspaces\Presentation\WorkspaceShell;

/**
 * Smart Segments — named, saved candidate filters inside the Talent Pool. Viewing
 * needs `talent.view`; creating/editing/deleting/bulk-adding needs `talent.manage`
 * (no new permission). Reuses {@see TalentPoolService::addCandidates()} for
 * bulk-add, so a segment result flows straight into an existing pool.
 */
final class SegmentController
{
    public function __construct(
        private readonly WorkspaceShell $shell,
        private readonly WorkspaceContext $context,
        private readonly AuthContext $auth,
        private readonly SegmentService $segments,
        private readonly TalentPoolService $pools,
        private readonly Session $session,
        private readonly AuditRecorder $audit,
    ) {
    }

    public function index(): Response
    {
        if (($r = $this->gate('talent.view')) !== null) {
            return $r;
        }

        return $this->shell->render($this->context, 'recruitment.talent.segments', [
            'segments' => $this->segments->listSegments((string) $this->context->workspaceId()),
            'fields' => SegmentField::catalog(),
            'canManage' => $this->context->can('talent.manage'),
            'status' => $this->session->pullFlash('status'),
        ]);
    }

    public function show(string $segmentId): Response
    {
        if (($r = $this->gate('talent.view')) !== null) {
            return $r;
        }

        $ws = (string) $this->context->workspaceId();
        $segment = $this->segments->findSegment($ws, $segmentId);
        if ($segment === null) {
            return Response::redirect('/talent-pool/segments');
        }

        return $this->shell->render($this->context, 'recruitment.talent.segment', [
            'segment' => $segment,
            'rules' => $this->segments->rules($ws, $segmentId),
            'matches' => $this->segments->evaluate($ws, $segmentId),
            'pools' => $this->pools->listPools($ws),
            'fields' => SegmentField::catalog(),
            'canManage' => $this->context->can('talent.manage'),
            'status' => $this->session->pullFlash('status'),
        ]);
    }

    public function create(Request $request): Response
    {
        if (($r = $this->gate('talent.manage', $request)) !== null) {
            return $r;
        }

        $name = trim((string) $request->input('name', ''));
        if ($name === '') {
            $this->session->flash('status', 'A segment name is required.');

            return Response::redirect('/talent-pool/segments');
        }

        $ws = (string) $this->context->workspaceId();
        $id = $this->segments->createSegment($ws, $name, (string) $request->input('match_type', 'all'), $this->context->userId());
        $this->segments->replaceRules($ws, $id, $this->rulesFromRequest($request));
        $this->audit->record('recruitment.talent_segment.created', [
            'workspace_id' => $ws,
            'actor_user_id' => $this->context->userId(),
            'entity_type' => 'talent_segment',
            'entity_id' => $id,
        ]);
        $this->session->flash('status', "Segment “{$name}” saved.");

        return Response::redirect('/talent-pool/segments/' . $id);
    }

    public function update(Request $request, string $segmentId): Response
    {
        if (($r = $this->gate('talent.manage', $request)) !== null) {
            return $r;
        }

        $ws = (string) $this->context->workspaceId();
        if ($this->segments->findSegment($ws, $segmentId) === null) {
            return Response::redirect('/talent-pool/segments');
        }

        $name = trim((string) $request->input('name', ''));
        if ($name !== '') {
            $this->segments->updateSegment($ws, $segmentId, $name, (string) $request->input('match_type', 'all'));
        }
        $this->segments->replaceRules($ws, $segmentId, $this->rulesFromRequest($request));
        $this->session->flash('status', 'Segment updated.');

        return Response::redirect('/talent-pool/segments/' . $segmentId);
    }

    public function delete(Request $request, string $segmentId): Response
    {
        if (($r = $this->gate('talent.manage', $request)) !== null) {
            return $r;
        }

        $this->segments->deleteSegment((string) $this->context->workspaceId(), $segmentId);
        $this->session->flash('status', 'Segment deleted.');

        return Response::redirect('/talent-pool/segments');
    }

    /** Bulk-add a segment's matched candidates into a pool (reuses TalentPoolService). */
    public function bulkAdd(Request $request, string $segmentId): Response
    {
        if (($r = $this->gate('talent.manage', $request)) !== null) {
            return $r;
        }

        $ids = $request->input('candidate_user_ids', []);
        $ids = is_array($ids) ? $ids : [];
        $n = $this->pools->addCandidates(
            (string) $this->context->workspaceId(),
            (string) $request->input('pool_id', ''),
            $ids,
            $this->context->userId(),
        );
        $this->session->flash('status', $n > 0 ? "Added {$n} candidate(s) to the pool." : 'Nothing added.');

        return Response::redirect('/talent-pool/segments/' . $segmentId);
    }

    /**
     * Zip the parallel rule_field[] / rule_value[] form arrays into rule maps.
     *
     * @return list<array{field: string, value: string}>
     */
    private function rulesFromRequest(Request $request): array
    {
        $fields = $request->input('rule_field', []);
        $values = $request->input('rule_value', []);
        $fields = is_array($fields) ? array_values($fields) : [];
        $values = is_array($values) ? array_values($values) : [];

        $rules = [];
        foreach ($fields as $i => $field) {
            $rules[] = ['field' => (string) $field, 'value' => (string) ($values[$i] ?? '')];
        }

        return $rules;
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
