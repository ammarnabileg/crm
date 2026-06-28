<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Recruitment\Application;

use HaHireAI\Core\Database\Connection;
use HaHireAI\Modules\Recruitment\Application\Exceptions\ApplicationException;
use HaHireAI\Shared\Ulid;

/**
 * Offers and the resulting hire (STATE_DIAGRAMS §4, §6). Accepting an offer
 * marks the application Hired and creates the Employee context for that user.
 */
final class OfferService
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function create(string $workspaceId, string $applicationId, string $title, ?int $salary, string $currency, ?string $createdBy, ?string $note = null, string $proposedBy = 'company'): string
    {
        $application = $this->connection->selectOne('SELECT id FROM applications WHERE id = ? AND workspace_id = ?', [$applicationId, $workspaceId]);
        if ($application === null) {
            throw new ApplicationException('Application not found in this workspace.');
        }

        // A candidate proposal starts life as 'proposed' (awaiting the company);
        // a company offer starts as a 'draft' it then sends.
        $proposedBy = $proposedBy === 'candidate' ? 'candidate' : 'company';
        $status = $proposedBy === 'candidate' ? 'proposed' : 'draft';

        $id = Ulid::generate();
        $now = gmdate('Y-m-d H:i:s');
        $this->connection->statement(
            'INSERT INTO offers (id, workspace_id, application_id, title, salary, currency, status, note, proposed_by, created_by, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$id, $workspaceId, $applicationId, $title, $salary, $currency, $status, $note, $proposedBy, $createdBy, $now, $now],
        );

        return $id;
    }

    /**
     * A candidate proposes a counter-offer back to the company, with a short
     * message explaining why (spec #3). The application must belong to the user.
     */
    public function counter(string $workspaceId, string $applicationId, string $userId, string $title, ?int $salary, string $currency, ?string $note): string
    {
        $owned = $this->connection->selectOne(
            'SELECT id FROM applications WHERE id = ? AND workspace_id = ? AND user_id = ? AND deleted_at IS NULL',
            [$applicationId, $workspaceId, $userId],
        );
        if ($owned === null) {
            throw new ApplicationException('That application is not yours.');
        }

        return $this->create($workspaceId, $applicationId, $title, $salary, $currency, $userId, $note, 'candidate');
    }

    /** Accept an offer the candidate owns (verifies the offer belongs to them). */
    public function acceptAsCandidate(string $workspaceId, string $offerId, string $userId): string
    {
        $this->assertCandidateOwns($workspaceId, $offerId, $userId);

        return $this->accept($workspaceId, $offerId);
    }

    /** Decline an offer the candidate owns. */
    public function declineAsCandidate(string $workspaceId, string $offerId, string $userId): void
    {
        $this->assertCandidateOwns($workspaceId, $offerId, $userId);
        $this->decline($workspaceId, $offerId);
    }

    private function assertCandidateOwns(string $workspaceId, string $offerId, string $userId): void
    {
        $owned = $this->connection->selectOne(
            'SELECT o.id FROM offers o JOIN applications a ON a.id = o.application_id
              WHERE o.id = ? AND o.workspace_id = ? AND a.user_id = ? AND o.deleted_at IS NULL',
            [$offerId, $workspaceId, $userId],
        );
        if ($owned === null) {
            throw new ApplicationException('That offer is not yours.');
        }
    }

    public function send(string $workspaceId, string $offerId): void
    {
        $this->transition($workspaceId, $offerId, from: 'draft', to: 'sent', stamp: 'sent_at');
    }

    /** Accept an offer → mark the application Hired and create the Employee. */
    public function accept(string $workspaceId, string $offerId): string
    {
        return $this->connection->transaction(function () use ($workspaceId, $offerId): string {
            $offer = $this->find($workspaceId, $offerId);
            if ($offer === null) {
                throw new ApplicationException('Offer not found in this workspace.');
            }
            if ((string) $offer['status'] !== 'sent') {
                throw new ApplicationException('Only a sent offer can be accepted.');
            }

            $now = gmdate('Y-m-d H:i:s');
            $this->connection->statement('UPDATE offers SET status = ?, decided_at = ?, updated_at = ? WHERE id = ?', ['accepted', $now, $now, $offerId]);

            $application = $this->connection->selectOne('SELECT * FROM applications WHERE id = ?', [(string) $offer['application_id']]);
            $this->connection->statement('UPDATE applications SET status = ?, updated_at = ? WHERE id = ?', ['hired', $now, (string) $offer['application_id']]);

            return $this->createEmployee($workspaceId, (string) $application['user_id'], (string) $offer['application_id'], (string) ($offer['title'] ?? ''));
        });
    }

    public function decline(string $workspaceId, string $offerId): void
    {
        $this->transition($workspaceId, $offerId, from: 'sent', to: 'declined', stamp: 'decided_at');
    }

    /** @return array<string, mixed>|null */
    public function find(string $workspaceId, string $offerId): ?array
    {
        return $this->connection->selectOne('SELECT * FROM offers WHERE id = ? AND workspace_id = ? AND deleted_at IS NULL', [$offerId, $workspaceId]);
    }

    /** @return list<array<string, mixed>> */
    public function forApplication(string $workspaceId, string $applicationId): array
    {
        return $this->connection->select('SELECT * FROM offers WHERE workspace_id = ? AND application_id = ? AND deleted_at IS NULL ORDER BY created_at DESC', [$workspaceId, $applicationId]);
    }

    /** @return list<array<string, mixed>> all offers for a candidate (across their applications) */
    public function forCandidate(string $workspaceId, string $userId): array
    {
        return $this->connection->select(
            'SELECT o.*, j.title AS job_title FROM offers o
               JOIN applications a ON a.id = o.application_id
               JOIN jobs j ON j.id = a.job_id
              WHERE o.workspace_id = ? AND a.user_id = ? AND o.deleted_at IS NULL ORDER BY o.created_at DESC',
            [$workspaceId, $userId],
        );
    }

    private function createEmployee(string $workspaceId, string $userId, string $applicationId, string $title): string
    {
        $existing = $this->connection->selectOne('SELECT id FROM employees WHERE workspace_id = ? AND user_id = ?', [$workspaceId, $userId]);
        if ($existing !== null) {
            return (string) $existing['id'];
        }

        $id = Ulid::generate();
        $now = gmdate('Y-m-d H:i:s');
        $this->connection->statement(
            'INSERT INTO employees (id, workspace_id, user_id, application_id, status, title, start_date, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$id, $workspaceId, $userId, $applicationId, 'onboarding', $title, $now, $now, $now],
        );

        return $id;
    }

    private function transition(string $workspaceId, string $offerId, string $from, string $to, string $stamp): void
    {
        $offer = $this->find($workspaceId, $offerId);
        if ($offer === null) {
            throw new ApplicationException('Offer not found in this workspace.');
        }
        if ((string) $offer['status'] !== $from) {
            throw new ApplicationException("Offer must be '{$from}' to become '{$to}'.");
        }

        $now = gmdate('Y-m-d H:i:s');
        $this->connection->statement("UPDATE offers SET status = ?, {$stamp} = ?, updated_at = ? WHERE id = ?", [$to, $now, $now, $offerId]);
    }
}
