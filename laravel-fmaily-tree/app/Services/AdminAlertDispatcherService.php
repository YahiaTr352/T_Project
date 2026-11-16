<?php

namespace App\Services;

use App\Models\UltramsgSendLog;
use App\Models\User;
use App\Support\NotificationSettings;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class AdminAlertDispatcherService
{
    public function __construct(
        protected UltramsgService $ultramsg
    ) {}

    /**
     * Get active editors and administrators with valid phone numbers.
     *
     * @param  array<string>  $permissions
     * @param  array<string>  $roles
     * @return Collection<int, User>
     */
    public function getEligibleEditors(
        array $permissions = [],
        array $roles = ['editor', 'admin', 'super_admin']
    ): Collection {
        return User::query()
            ->where('status', 'active')
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->where(function ($query) use ($roles, $permissions) {
                if (!empty($roles)) {
                    $query->whereHas('roles', function ($r) use ($roles) {
                        $r->whereIn('name', $roles);
                    });
                }

                if (!empty($permissions)) {
                    $method = !empty($roles) ? 'orWhereHas' : 'whereHas';
                    $query->{$method}('permissions', function ($p) use ($permissions) {
                        $p->whereIn('name', $permissions);
                    });
                }
            })
            ->get();
    }

    /**
     * Dispatch a WhatsApp message to all eligible editors/admins.
     *
     * @param  string  $message
     * @param  array<string>  $permissions
     * @param  array<string>  $roles
     * @param  string  $logContext
     * @return int Count of sent messages
     */
    public function sendWhatsAppToAdmins(
        string $message,
        array $permissions = [],
        array $roles = ['editor', 'admin', 'super_admin'],
        string $logContext = 'AdminAlertDispatcher'
    ): int {
        if (!NotificationSettings::isChannelEnabled('whatsapp')) {
            Log::channel('single')->info("{$logContext}: WhatsApp channel is disabled in settings. Skipping admin alerts.");
            return 0;
        }

        $editors = $this->getEligibleEditors($permissions, $roles);

        if ($editors->isEmpty()) {
            Log::channel('single')->info("{$logContext}: No active editors with phone numbers found.");
            return 0;
        }

        $sentCount = 0;

        foreach ($editors as $editor) {
            $phone = trim((string) $editor->phone);
            if ($phone === '') {
                continue;
            }

            $log = UltramsgSendLog::create([
                'notification_id' => null,
                'person_id'      => null,
                'recipient_name' => $editor->name,
                'to_number'      => $phone,
                'message_sent'   => $message,
                'media_type'     => null,
                'media_path'     => null,
                'status'         => 'pending',
            ]);

            try {
                $result = $this->ultramsg->sendText($phone, $message);
                if (!empty($result['success'])) {
                    $log->update([
                        'status'      => 'sent',
                        'ultramsg_id' => $result['id'] ?? null,
                        'sent_at'     => now(),
                    ]);
                    $sentCount++;
                    Log::channel('single')->info("{$logContext}: Sent WhatsApp alert to editor {$editor->name} ({$phone})");
                } else {
                    $log->update([
                        'status'        => 'failed',
                        'error_message' => $result['error'] ?? 'UltraMsg send failed',
                    ]);
                }
            } catch (\Throwable $e) {
                $log->update([
                    'status'        => 'failed',
                    'error_message' => $e->getMessage(),
                ]);
                Log::channel('single')->error("{$logContext} failed for editor {$editor->id} ({$phone}): " . $e->getMessage());
            }
        }

        return $sentCount;
    }

    /**
     * Send direct WhatsApp message to a specific phone number.
     */
    public function sendDirectWhatsApp(string $phone, string $message, string $logContext = 'AdminAlertDispatcher'): bool
    {
        if (!NotificationSettings::isChannelEnabled('whatsapp')) {
            Log::channel('single')->info("{$logContext}: WhatsApp channel is disabled in settings. Skipping direct WhatsApp.");
            return false;
        }

        $phone = trim($phone);
        if ($phone === '') {
            return false;
        }

        $log = UltramsgSendLog::create([
            'notification_id' => null,
            'person_id'      => null,
            'recipient_name' => 'Direct Message',
            'to_number'      => $phone,
            'message_sent'   => $message,
            'media_type'     => null,
            'media_path'     => null,
            'status'         => 'pending',
        ]);

        try {
            $result = $this->ultramsg->sendText($phone, $message);
            if (!empty($result['success'])) {
                $log->update([
                    'status'      => 'sent',
                    'ultramsg_id' => $result['id'] ?? null,
                    'sent_at'     => now(),
                ]);
                Log::channel('single')->info("{$logContext}: Sent direct WhatsApp message to {$phone}");
                return true;
            }

            $log->update([
                'status'        => 'failed',
                'error_message' => $result['error'] ?? 'UltraMsg send failed',
            ]);
            return false;
        } catch (\Throwable $e) {
            $log->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
            ]);
            Log::channel('single')->error("{$logContext}: Failed sending direct WhatsApp message to {$phone}: " . $e->getMessage());
            return false;
        }
    }
}
