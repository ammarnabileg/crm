<?php

declare(strict_types=1);

namespace App\Services\Ats;

use App\Models\Application;
use App\Models\Offer;
use RuntimeException;

/**
 * Offer management (docs/53 ATS): create → approve → send → accept/decline via
 * `offer_statuses`. Accepting an offer also advances the application to 'hired'.
 * Each transition dispatches a domain event. Tenant-scoped. (PDF/e-sign are handled
 * by the document layer; this manages the offer record + lifecycle.)
 */
final class OfferManager
{
    public function create(int $applicationId, array $attrs = [], ?int $userId = null): Offer
    {
        $application = Application::find($applicationId);
        if ($application === null) {
            throw new RuntimeException("Application {$applicationId} not found in this workspace.");
        }

        $offer = Offer::create(array_merge([
            'application_id'    => $applicationId,
            'offer_status_id'   => status_id('offer_statuses', 'draft'),
            'candidate_user_id' => (int) $application->user_id,
            'created_by'        => $userId,
        ], $attrs));

        AtsEvents::dispatch('offer.created', ['offer_id' => (int) $offer->getKey(), 'application_id' => $applicationId]);

        return $offer;
    }

    public function approve(int $offerId): Offer
    {
        $offer = $this->find($offerId);
        $offer->update(['offer_status_id' => status_id('offer_statuses', 'approved')]);

        return $offer;
    }

    public function send(int $offerId): Offer
    {
        $offer = $this->find($offerId);
        $offer->update(['offer_status_id' => status_id('offer_statuses', 'sent'), 'sent_at' => now()]);

        return $offer;
    }

    public function accept(int $offerId): Offer
    {
        $offer = $this->find($offerId);
        $offer->update(['offer_status_id' => status_id('offer_statuses', 'accepted'), 'responded_at' => now()]);

        // Advance the application to hired.
        $application = Application::find((int) $offer->application_id);
        if ($application !== null) {
            $application->update([
                'application_status_id' => status_id('application_statuses', 'hired'),
                'decided_at'            => now(),
            ]);
        }

        AtsEvents::dispatch('offer.accepted', ['offer_id' => $offerId, 'application_id' => (int) $offer->application_id]);

        return $offer;
    }

    public function decline(int $offerId): Offer
    {
        $offer = $this->find($offerId);
        $offer->update(['offer_status_id' => status_id('offer_statuses', 'declined'), 'responded_at' => now()]);
        AtsEvents::dispatch('offer.declined', ['offer_id' => $offerId]);

        return $offer;
    }

    private function find(int $offerId): Offer
    {
        $offer = Offer::find($offerId);
        if ($offer === null) {
            throw new RuntimeException("Offer {$offerId} not found in this workspace.");
        }

        return $offer;
    }
}
