<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AppVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'platform',
        'bundle_id',
        'latest_version',
        'min_supported_version',
        'store_url',
        'store_detected_version',
        'store_synced_at',
        'title_ar',
        'title_en',
        'release_notes_ar',
        'release_notes_en',
        'is_under_maintenance',
        'maintenance_message_ar',
        'maintenance_message_en',
    ];

    protected $casts = [
        'is_under_maintenance' => 'boolean',
        'store_synced_at' => 'datetime',
    ];

    /**
     * Check if a client version requires a hard/force update.
     */
    public function requiresForceUpdate(string $clientVersion): bool
    {
        return version_compare($clientVersion, $this->min_supported_version, '<');
    }

    /**
     * Check if a client version has a soft/optional update available.
     */
    public function hasOptionalUpdate(string $clientVersion): bool
    {
        return version_compare($clientVersion, $this->min_supported_version, '>=')
            && version_compare($clientVersion, $this->latest_version, '<');
    }

    /**
     * Compare detected store version with system latest version.
     * Returns true if store has a higher version that is not yet configured in the dashboard.
     */
    public function isStoreVersionNewer(): bool
    {
        if (empty($this->store_detected_version)) {
            return false;
        }

        return version_compare($this->store_detected_version, $this->latest_version, '>');
    }

    /**
     * Returns true if min_supported_version is dangerously higher than what the store actually has.
     */
    public function isMinSupportedHigherThanStore(): bool
    {
        if (empty($this->store_detected_version)) {
            return false;
        }

        return version_compare($this->min_supported_version, $this->store_detected_version, '>');
    }

    /**
     * Check if dashboard version matches the store version.
     */
    public function isSyncedWithStore(): bool
    {
        if (empty($this->store_detected_version)) {
            return false;
        }

        return version_compare($this->latest_version, $this->store_detected_version, '=');
    }
}
