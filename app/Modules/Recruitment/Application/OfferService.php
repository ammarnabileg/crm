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

    /**
     * Accept an offer the candidate owns (verifies the offer belongs to them),
     * capturing the earliest start date they can begin and an optional note.
     */
    public function acceptAsCandidate(string $workspaceId, string $offerId, string $userId, ?string $startDate = null, ?string $acceptNote = null): string
    {
        $this->assertCandidateOwns($workspaceId, $offerId, $userId);

        return $this->accept($workspaceId, $offerId, $startDate, $acceptNote);
    }

    /**
     * The company accepts a candidate's counter-proposal (status 'proposed'),
     * ending the negotiation with a hire. Mirror of {@see self::accept()} for the
     * candidate-initiated side of the loop.
     */
    public function acceptProposal(string $workspaceId, string $offerId, ?string $startDate = null, ?string $acceptNote = null): string
    {
        return $this->finalizeHire($workspaceId, $offerId, 'proposed', $startDate, $acceptNote);
    }

    /** The company rejects a candidate's counter-proposal without countering (ends the loop). */
    public function declineProposal(string $workspaceId, string $offerId): void
    {
        $this->transition($workspaceId, $offerId, from: 'proposed', to: 'declined', stamp: 'decided_at');
    }

    /**
     * The company counters back with a new offer (a fresh company offer, sent
     * immediately so the candidate can respond). Continues the negotiation loop.
     */
    public function counterFromCompany(string $workspaceId, string $applicationId, string $title, ?int $salary, string $currency, ?string $createdBy, ?string $note = null): string
    {
        $id = $this->create($workspaceId, $applicationId, $title, $salary, $currency, $createdBy, $note, 'company');
        $this->send($workspaceId, $id);

        return $id;
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

    /**
     * Accept a SENT company offer → mark the application Hired and create the
     * Employee. Optionally records the candidate's earliest start date and note.
     */
    public function accept(string $workspaceId, string $offerId, ?string $startDate = null, ?string $acceptNote = null): string
    {
        return $this->finalizeHire($workspaceId, $offerId, 'sent', $startDate, $acceptNote);
    }

    /**
     * Shared final step of the negotiation: whichever side accepts the other's
     * latest open offer (a SENT company offer or a PROPOSED candidate counter)
     * finalises the hire — offer accepted, application hired (+ start date), and
     * the Employee created. One code path so both directions behave identically.
     */
    private function finalizeHire(string $workspaceId, string $offerId, string $requiredStatus, ?string $startDate, ?string $acceptNote): string
    {
        return $this->connection->transaction(function () use ($workspaceId, $offerId, $requiredStatus, $startDate, $acceptNote): string {
            $offer = $this->find($workspaceId, $offerId);
            if ($offer === null) {
                throw new ApplicationException('Offer not found in this workspace.');
            }
            if ((string) $offer['status'] !== $requiredStatus) {
                throw new ApplicationException("Only a '{$requiredStatus}' offer can be accepted here.");
            }

            $now = gmdate('Y-m-d H:i:s');
            $note = $this->appendNote((string) ($offer['note'] ?? ''), $acceptNote);
            $this->connection->statement('UPDATE offers SET status = ?, note = ?, decided_at = ?, updated_at = ? WHERE id = ?', ['accepted', $note, $now, $now, $offerId]);

            $application = $this->connection->selectOne('SELECT * FROM applications WHERE id = ?', [(string) $offer['application_id']]);
            $start = $this->normalizeDate($startDate);
            if ($start !== null) {
                $this->connection->statement('UPDATE applications SET status = ?, available_from = ?, updated_at = ? WHERE id = ?', ['hired', $start, $now, (string) $offer['application_id']]);
            } else {
                $this->connection->statement('UPDATE applications SET status = ?, updated_at = ? WHERE id = ?', ['hired', $now, (string) $offer['application_id']]);
            }

            return $this->createEmployee($workspaceId, (string) $application['user_id'], (string) $offer['application_id'], (string) ($offer['title'] ?? ''));
        });
    }

    private function appendNote(string $existing, ?string $acceptNote): string
    {
        $acceptNote = $acceptNote !== null ? trim($acceptNote) : '';
        if ($acceptNote === '') {
            return $existing;
        }
        $line = 'Accepted: ' . $acceptNote;

        return trim($existing) === '' ? $line : trim($existing) . ' — ' . $line;
    }

    private function normalizeDate(?string $date): ?string
    {
        $date = $date !== null ? trim($date) : '';
        if ($date === '' || preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
            return null;
        }

        return $date;
    }

    public function decline(string $workspaceId, string $offerId): void
    {
        $this->transition($workspaceId, $offerId, from: 'sent', to: 'declined', stamp: 'decided_at');
    }

    /** The company withdraws an offer it created (draft or sent). */
    public function withdraw(string $workspaceId, string $offerId): void
    {
        $offer = $this->find($workspaceId, $offerId);
        if ($offer === null) {
            throw new ApplicationException('Offer not found in this workspace.');
        }
        if (! in_array((string) $offer['status'], ['draft', 'sent', 'proposed'], true)) {
            throw new ApplicationException('Only an open offer can be withdrawn.');
        }
        $now = gmdate('Y-m-d H:i:s');
        $this->connection->statement('UPDATE offers SET status = ?, decided_at = ?, updated_at = ? WHERE id = ?', ['revoked', $now, $now, $offerId]);
    }

    /** @return list<array<string,mixed>> all offers in the workspace, with candidate + job. */
    public function listForWorkspace(string $workspaceId): array
    {
        return $this->connection->select(
            'SELECT o.*, u.name AS candidate_name, u.id AS candidate_user_id, j.title AS job_title
               FROM offers o
               JOIN applications a ON a.id = o.application_id
               JOIN users u ON u.id = a.user_id
               JOIN jobs j ON j.id = a.job_id
              WHERE o.workspace_id = ? AND o.deleted_at IS NULL
              ORDER BY o.created_at DESC',
            [$workspaceId],
        );
    }

    /** @return array<string,mixed>|null one offer joined with candidate + job (for the printable view). */
    public function findDetailed(string $workspaceId, string $offerId): ?array
    {
        return $this->connection->selectOne(
            'SELECT o.*, u.name AS candidate_name, u.email AS candidate_email, j.title AS job_title
               FROM offers o
               JOIN applications a ON a.id = o.application_id
               JOIN users u ON u.id = a.user_id
               JOIN jobs j ON j.id = a.job_id
              WHERE o.id = ? AND o.workspace_id = ? AND o.deleted_at IS NULL',
            [$offerId, $workspaceId],
        );
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

    /** The candidate's most recent application id in this workspace, or null. */
    public function latestApplicationId(string $workspaceId, string $userId): ?string
    {
        $row = $this->connection->selectOne(
            'SELECT id FROM applications WHERE workspace_id = ? AND user_id = ? AND deleted_at IS NULL ORDER BY applied_at DESC LIMIT 1',
            [$workspaceId, $userId],
        );

        return $row !== null ? (string) $row['id'] : null;
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
