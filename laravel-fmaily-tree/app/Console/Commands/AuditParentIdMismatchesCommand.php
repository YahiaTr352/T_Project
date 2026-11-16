<?php

namespace App\Console\Commands;

use App\Models\Marriage;
use App\Models\Person;
use App\Models\ProfileUpdateRequest;
use Illuminate\Console\Command;

class AuditParentIdMismatchesCommand extends Command
{
    protected $signature = 'audit:parent-mismatches {--developer-id= : Specify person ID of developer to search specifically} {--reject-invalid-pending : Reject pending child/marriage requests that have no valid marriage}';

    protected $description = 'Audit and clean up profile update requests where children or marriages were wrongly linked to unrelated persons';

    public function handle(): int
    {
        $this->info('بدء فحص طلبات إضافة الأبناء والزواج للتأكد من صحة الربط...');

        $developerPersonId = $this->option('developer-id') ? (int) $this->option('developer-id') : null;
        $rejectPending = (bool) $this->option('reject-invalid-pending');

        $requests = ProfileUpdateRequest::whereIn('change_type', ['child_add', 'marriage_add'])
            ->with(['person'])
            ->orderBy('id', 'desc')
            ->get();

        $rows = [];
        $invalidPendingCount = 0;

        foreach ($requests as $req) {
            $owner = $req->person;
            if (!$owner) {
                continue;
            }

            $proposed = $req->proposed_data ?? [];

            if ($req->change_type === 'child_add' && $owner->gender === 'female') {
                $motherId = $owner->id;
                $fatherId = (int) ($proposed['parent_id'] ?? 0);

                if ($fatherId <= 0) {
                    continue;
                }

                $isMarried = Marriage::where('husband_id', $fatherId)
                    ->where('wife_id', $motherId)
                    ->exists();

                $isTarget = (!$isMarried) || ($developerPersonId && $fatherId === $developerPersonId);

                if ($isTarget) {
                    $father = Person::find($fatherId);
                    $rows[] = [
                        'ID' => $req->id,
                        'Type' => 'child_add',
                        'Owner (Mother)' => "{$owner->full_name} (#{$owner->id})",
                        'Assigned Father' => $father ? "{$father->full_name} (#{$father->id})" : "#{$fatherId}",
                        'Child Name' => $proposed['first_name'] ?? '—',
                        'Valid Marriage?' => $isMarried ? 'نعم' : '❌ لا يوجد زواج',
                        'Status' => $req->status,
                        'Created At' => optional($req->created_at)?->format('Y-m-d H:i'),
                    ];

                    if ($req->status === 'pending') {
                        $invalidPendingCount++;
                        if ($rejectPending) {
                            $req->update([
                                'status' => 'rejected',
                                'rejection_reason' => 'تم رفض الطلب تلقائياً لعدم وجود عقد زواج موثق يربط الأم بالأب المختار.',
                                'reviewed_at' => now(),
                            ]);
                        }
                    }
                }
            } elseif ($req->change_type === 'marriage_add' && $owner->gender === 'female') {
                $husbandId = (int) ($proposed['husband_id'] ?? 0);
                if ($developerPersonId && $husbandId === $developerPersonId) {
                    $husband = Person::find($husbandId);
                    $rows[] = [
                        'ID' => $req->id,
                        'Type' => 'marriage_add',
                        'Owner (Mother)' => "{$owner->full_name} (#{$owner->id})",
                        'Assigned Father' => $husband ? "{$husband->full_name} (#{$husband->id})" : "#{$husbandId}",
                        'Child Name' => '— (طلب زواج)',
                        'Valid Marriage?' => '❌ طلب زواج بالحساب',
                        'Status' => $req->status,
                        'Created At' => optional($req->created_at)?->format('Y-m-d H:i'),
                    ];

                    if ($req->status === 'pending' && $rejectPending) {
                        $req->update([
                            'status' => 'rejected',
                            'rejection_reason' => 'تم رفض الطلب تلقائياً بسبب خطأ في تحديد الزواج.',
                            'reviewed_at' => now(),
                        ]);
                    }
                }
            }
        }

        if (empty($rows)) {
            $this->info('لم يتم العثور على أي طلبات غير متطابقة.');
            return self::SUCCESS;
        }

        $this->table(['ID', 'Type', 'Owner (Mother)', 'Assigned Father/Spouse', 'Detail', 'Valid Marriage?', 'Status', 'Created At'], $rows);

        $this->warn("إجمالي الطلبات المشبوهة أو غير المتطابقة: " . count($rows));
        $this->warn("الطلبات المعلقة (Pending) منها: {$invalidPendingCount}");

        if ($rejectPending) {
            $this->info("تم بنجاح رفض كافة الطلبات المعلقة غير الصالحة.");
        } else if ($invalidPendingCount > 0) {
            $this->comment("لرفض الطلبات المعلقة غير المتطابقة تلقائياً، أعد تشغيل الأمر مع الخيار: --reject-invalid-pending");
        }

        return self::SUCCESS;
    }
}
