<?php

namespace App\Services;

use App\Jobs\NotifyEditorsOfPendingProfileUpdatesJob;
use App\Models\Person;
use App\Models\ProfileUpdateRequest;
use Illuminate\Support\Facades\Log;

class NameNormalizationService
{
    /**
     * Regex pattern to identify "عبد " followed by divine names starting with "ال" or "الله" / "الإله" / "الرب".
     * Ensures words boundary so regular lineage spaces (e.g. "يوسف حمد") are untouched.
     */
    public const ABD_COMPOUND_PATTERN = '/\bعبد\s+(ال[^\s]+|الله|الإله|الاله|الرب)\b/u';

    /**
     * Normalize compound names starting with "عبد" by removing the erroneous space
     * between "عبد" and the divine name (e.g. "عبد الله" -> "عبدالله", "عبد الرحمن" -> "عبدالرحمن").
     * Leaves all other inter-name spaces (like lineage "بن" / "بنت" or father names) completely intact.
     */
    public function normalizeCompoundAbdName(?string $name): ?string
    {
        if ($name === null) {
            return null;
        }

        $trimmed = trim($name);
        if ($trimmed === '') {
            return $name;
        }

        // Replace "عبد [اسم_إلهي]" with "عبد[اسم_إلهي]"
        $normalized = preg_replace_callback(self::ABD_COMPOUND_PATTERN, function (array $matches): string {
            return 'عبد' . $matches[1];
        }, $trimmed);

        // Clean up any double spaces that might exist elsewhere without altering words
        $normalized = preg_replace('/\s{2,}/u', ' ', (string) $normalized);

        return trim((string) $normalized);
    }

    /**
     * Check whether a given name contains "عبد " followed by a divine name with space.
     */
    public function hasCompoundAbdNameSpacing(?string $name): bool
    {
        if ($name === null || trim($name) === '') {
            return false;
        }

        return preg_match(self::ABD_COMPOUND_PATTERN, $name) === 1;
    }

    /**
     * Determine proposed normalized name changes for a given Person model.
     *
     * @return array<string, string> Key-value pairs of changed attributes (e.g. ['first_name' => 'عبدالرحمن'])
     */
    public function getProposedChangesForPerson(Person $person): array
    {
        $changes = [];

        if ($this->hasCompoundAbdNameSpacing($person->first_name)) {
            $normalizedFirst = $this->normalizeCompoundAbdName($person->first_name);
            if ($normalizedFirst !== $person->first_name) {
                $changes['first_name'] = $normalizedFirst;
            }
        }

        if ($this->hasCompoundAbdNameSpacing($person->last_name)) {
            $normalizedLast = $this->normalizeCompoundAbdName($person->last_name);
            if ($normalizedLast !== $person->last_name) {
                $changes['last_name'] = $normalizedLast;
            }
        }

        return $changes;
    }

    /**
     * Create a pending ProfileUpdateRequest for the person with the proposed name normalization.
     * Prevents creating duplicate pending requests if an identical request is already pending.
     */
    public function createNormalizationRequest(Person $person, array $changes): ?ProfileUpdateRequest
    {
        if (empty($changes)) {
            return null;
        }

        // Check if there is already a pending request for this person with the same proposed data
        $existing = ProfileUpdateRequest::where('person_id', $person->id)
            ->where('status', 'pending')
            ->where('change_type', 'profile_info')
            ->where('requested_by_auth', 'system')
            ->first();

        if ($existing) {
            $existingProposed = $existing->proposed_data ?? [];
            $alreadyProposed = true;
            foreach ($changes as $key => $val) {
                if (($existingProposed[$key] ?? null) !== $val) {
                    $alreadyProposed = false;
                    break;
                }
            }

            if ($alreadyProposed) {
                return $existing;
            }
        }

        $oldData = [];
        foreach ($changes as $key => $val) {
            $oldData[$key] = $person->{$key};
        }

        $request = ProfileUpdateRequest::create([
            'person_id'         => $person->id,
            'requested_by_auth' => 'system',
            'change_type'       => 'profile_info',
            'target_id'         => null,
            'old_data'          => $oldData,
            'proposed_data'     => $changes,
            'attached_files'    => null,
            'status'            => 'pending',
        ]);

        try {
            NotifyEditorsOfPendingProfileUpdatesJob::dispatch($request->id);
        } catch (\Throwable $e) {
            Log::channel('single')->error('[NameNormalizationService] Failed to dispatch admin alert: ' . $e->getMessage(), [
                'request_id' => $request->id,
            ]);
        }

        return $request;
    }
}
