<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Recruitment\Presentation;

use HaHireAI\Core\Database\Connection;
use HaHireAI\Core\Http\Request;
use HaHireAI\Core\Http\Response;
use HaHireAI\Core\Http\Session;
use HaHireAI\Modules\Audit\Application\AuditLogger;
use HaHireAI\Modules\Authentication\Application\AuthContext;
use HaHireAI\Modules\Recruitment\Application\OfferService;
use HaHireAI\Modules\Workspaces\Application\WorkspaceContext;
use Throwable;

/** Make/send offers and record acceptance (→ hire + Employee). */
final class OffersController
{
    public function __construct(
        private readonly WorkspaceContext $context,
        private readonly AuthContext $auth,
        private readonly OfferService $offers,
        private readonly Connection $connection,
        private readonly Session $session,
        private readonly AuditLogger $audit,
    ) {
    }

    public function make(Request $request, string $userId): Response
    {
        if (($r = $this->gate('offer.create', $request)) !== null) {
            return $r;
        }

        $application = $this->connection->selectOne(
            'SELECT id FROM applications WHERE workspace_id = ? AND user_id = ? AND deleted_at IS NULL ORDER BY applied_at DESC LIMIT 1',
            [(string) $this->context->workspaceId(), $userId],
        );

        if ($application === null) {
            $this->session->flash('error', 'This candidate has no application to make an offer on.');

            return Response::redirect('/candidates/' . $userId);
        }

        try {
            $offerId = $this->offers->create(
                (string) $this->context->workspaceId(),
                (string) $application['id'],
                trim((string) $request->input('title', 'Offer')) ?: 'Offer',
                (int) $request->input('salary', 0) ?: null,
                (string) $request->input('currency', 'USD'),
                $this->context->userId(),
            );
            $this->offers->send((string) $this->context->workspaceId(), $offerId);
            $this->audit->record('recruitment.offer.sent', [
                'workspace_id' => $this->context->workspaceId(),
                'actor_user_id' => $this->context->userId(),
                'entity_type' => 'offer',
                'entity_id' => $offerId,
            ]);
            $this->session->flash('status', 'Offer created and sent.');
        } catch (Throwable $e) {
            $this->session->flash('error', $e->getMessage());
        }

        return Response::redirect('/candidates/' . $userId);
    }

    public function accept(Request $request, string $offerId): Response
    {
        if (($r = $this->gate('offer.send', $request)) !== null) {
            return $r;
        }

        $userId = (string) $request->input('user_id', '');

        try {
            $this->offers->accept((string) $this->context->workspaceId(), $offerId);
            $this->audit->record('recruitment.offer.accepted', [
                'workspace_id' => $this->context->workspaceId(),
                'actor_user_id' => $this->context->userId(),
                'entity_type' => 'offer',
                'entity_id' => $offerId,
            ]);
            $this->session->flash('status', 'Offer accepted — candidate hired and added as an employee.');
        } catch (Throwable $e) {
            $this->session->flash('error', $e->getMessage());
        }

        return Response::redirect($userId !== '' ? '/candidates/' . $userId : '/candidates');
    }

    private function gate(string $permission, Request $request): ?Response
    {
        if (! $this->auth->check()) {
            return Response::redirect('/login');
        }
        if (! $this->context->resolve()) {
            return Response::redirect('/dashboard');
        }
        if (! $this->context->can($permission) || ! $this->session->verifyCsrf((string) $request->input('_csrf'))) {
            return Response::html('<h1>403</h1><p>You do not have permission.</p>', 403);
        }

        return null;
    }
}
