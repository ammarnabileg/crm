<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Recruitment\Presentation;

use HaHireAI\Core\Http\Request;
use HaHireAI\Core\Http\Response;
use HaHireAI\Core\Http\Session;
use HaHireAI\Core\Contracts\AuditRecorder;
use HaHireAI\Modules\Authentication\Application\AuthContext;
use HaHireAI\Core\View\View;
use HaHireAI\Modules\Recruitment\Application\OfferService;
use HaHireAI\Modules\Workspaces\Application\WorkspaceContext;
use HaHireAI\Modules\Workspaces\Presentation\WorkspaceShell;
use Throwable;

/** Make/send offers, record acceptance (→ hire + Employee), and print offer letters. */
final class OffersController
{
    public function __construct(
        private readonly WorkspaceShell $shell,
        private readonly WorkspaceContext $context,
        private readonly AuthContext $auth,
        private readonly OfferService $offers,
        private readonly View $view,
        private readonly Session $session,
        private readonly AuditRecorder $audit,
    ) {
    }

    /** The workspace's offers (sidebar: Offers). */
    public function index(): Response
    {
        if (($r = $this->viewGate('offer.view')) !== null) {
            return $r;
        }

        return $this->shell->render($this->context, 'recruitment.offers.index', [
            'offers' => $this->offers->listForWorkspace((string) $this->context->workspaceId()),
            'canManage' => $this->context->can('offer.create'),
            'status' => $this->session->pullFlash('status'),
            'error' => $this->session->pullFlash('error'),
        ]);
    }

    /** A printable offer letter (use the browser's "Save as PDF"). */
    public function print(string $offerId): Response
    {
        if (($r = $this->viewGate('offer.view')) !== null) {
            return $r;
        }

        $offer = $this->offers->findDetailed((string) $this->context->workspaceId(), $offerId);
        if ($offer === null) {
            return Response::redirect('/offers');
        }

        return Response::html($this->view->page('recruitment.offers.print', [
            'offer' => $offer,
            'workspace' => $this->context->workspace(),
        ], 'layouts.print', ['title' => 'Offer letter']));
    }

    public function send(Request $request, string $offerId): Response
    {
        return $this->transition($request, $offerId, 'send', 'Offer sent.');
    }

    public function decline(Request $request, string $offerId): Response
    {
        return $this->transition($request, $offerId, 'decline', 'Offer marked declined.');
    }

    public function withdraw(Request $request, string $offerId): Response
    {
        return $this->transition($request, $offerId, 'withdraw', 'Offer withdrawn.');
    }

    private function transition(Request $request, string $offerId, string $action, string $ok): Response
    {
        if (($r = $this->gate('offer.send', $request)) !== null) {
            return $r;
        }
        try {
            $this->offers->{$action}((string) $this->context->workspaceId(), $offerId);
            $this->audit->record('recruitment.offer.' . $action, [
                'workspace_id' => $this->context->workspaceId(),
                'actor_user_id' => $this->context->userId(),
                'entity_type' => 'offer',
                'entity_id' => $offerId,
            ]);
            $this->session->flash('status', $ok);
        } catch (Throwable $e) {
            $this->session->flash('error', $e->getMessage());
        }

        return Response::redirect('/offers');
    }

    public function make(Request $request, string $userId): Response
    {
        if (($r = $this->gate('offer.create', $request)) !== null) {
            return $r;
        }

        $applicationId = $this->offers->latestApplicationId((string) $this->context->workspaceId(), $userId);

        if ($applicationId === null) {
            $this->session->flash('error', 'This candidate has no application to make an offer on.');

            return Response::redirect('/candidates/' . $userId);
        }

        try {
            $offerId = $this->offers->create(
                (string) $this->context->workspaceId(),
                $applicationId,
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

    private function viewGate(string $permission): ?Response
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

        return null;
    }
}
