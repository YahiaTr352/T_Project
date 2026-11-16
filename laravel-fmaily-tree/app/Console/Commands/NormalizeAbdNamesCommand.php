<?php

namespace App\Console\Commands;

use App\Models\Person;
use App\Services\NameNormalizationService;
use App\Services\TreeCacheService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class NormalizeAbdNamesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'people:normalize-abd-names
                            {--dry-run : عرض الأسماء المقترحة فقط دون إنشاء طلبات تعديل أو تغيير البيانات}
                            {--apply-direct : تطبيق التعديل مباشرة على قاعدة البيانات دون المرور بطلبات الاعتماد}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'فحص الأسماء المركبة (عبد ...) وإزالة المسافة الزائدة بين "عبد" والاسم الإلهي وإنشاء طلبات تعديل للاعتماد في لوحة التحكم.';

    /**
     * Execute the console command.
     */
    public function handle(NameNormalizationService $normalizationService, TreeCacheService $treeCache): int
    {
        $isDryRun = (bool) $this->option('dry-run');
        $applyDirect = (bool) $this->option('apply-direct');

        $this->info('🔍 جاري فحص الأسماء المركبة (عبد ...) في قاعدة البيانات...');

        $candidates = [];

        // Efficiently scan persons whose first_name or last_name contains "عبد "
        $query = Person::query()
            ->where('first_name', 'LIKE', '%عبد %')
            ->orWhere('last_name', 'LIKE', '%عبد %');

        foreach ($query->cursor() as $person) {
            $changes = $normalizationService->getProposedChangesForPerson($person);
            if (!empty($changes)) {
                $candidates[] = [
                    'person'  => $person,
                    'changes' => $changes,
                ];
            }
        }

        $totalCount = count($candidates);

        if ($totalCount === 0) {
            $this->info('✨ لم يتم العثور على أي أسماء تحتوي على مسافة غير صحيحة بعد "عبد". قاعدة البيانات سليمة!');
            return Command::SUCCESS;
        }

        $this->info("تم العثور على {$totalCount} شخص يحتاج تصحيح الاسم المركب:");

        // Display results table
        $tableRows = [];
        foreach ($candidates as $item) {
            /** @var Person $person */
            $person = $item['person'];
            $changes = $item['changes'];

            $firstChange = isset($changes['first_name'])
                ? "{$person->first_name} ➔ {$changes['first_name']}"
                : '—';

            $lastChange = isset($changes['last_name'])
                ? "{$person->last_name} ➔ {$changes['last_name']}"
                : '—';

            $tableRows[] = [
                $person->id,
                $firstChange,
                $lastChange,
            ];
        }

        $this->table(['ID', 'الاسم الأول (First Name)', 'الاسم الأخير (Last Name)'], $tableRows);

        if ($isDryRun) {
            $this->warn("⚠️  [وضع المعاينة --dry-run] تم رصد {$totalCount} شخص. لم يتم إجراء أي تغيير أو إنشاء طلبات تعديل.");
            return Command::SUCCESS;
        }

        if ($applyDirect) {
            if (!$this->input->isInteractive() || $this->confirm("هل أنت متأكد من تطبيق التعديلات مباشرة على {$totalCount} شخص في قاعدة البيانات؟", true)) {
                $this->info('جاري تطبيق التعديلات وتحديث كاش الشجرة...');

                DB::transaction(function () use ($candidates, $treeCache) {
                    foreach ($candidates as $item) {
                        /** @var Person $person */
                        $person = $item['person'];
                        $person->update($item['changes']);
                        $treeCache->forgetPersonAndTree($person);
                    }
                });

                $this->info("✅ تم تحديث {$totalCount} شخص مباشرة في قاعدة البيانات بنجاح، وتحديث كاش شجرة العائلة.");
                return Command::SUCCESS;
            }

            $this->warn('تم إلغاء العملية.');
            return Command::SUCCESS;
        }

        // Standard mode: create ProfileUpdateRequest for each person for admin moderation
        $this->info('جاري إنشاء طلبات تعديل البيانات وإرسال التنبيهات لإدارة العائلة...');

        $createdCount = 0;
        $existingCount = 0;

        foreach ($candidates as $item) {
            /** @var Person $person */
            $person = $item['person'];
            $request = $normalizationService->createNormalizationRequest($person, $item['changes']);

            if ($request->wasRecentlyCreated) {
                $createdCount++;
            } else {
                $existingCount++;
            }
        }

        $this->newLine();
        $this->info("🎉 تم بنجاح إنشاء {$createdCount} طلب تعديل بانتظار المراجعة والاعتماد في لوحة التحكم.");
        if ($existingCount > 0) {
            $this->line("ℹ️  ({$existingCount} طلب كان موجوداً مسبقاً قيد المراجعة).");
        }

        $this->comment("👉 يمكنك الآن الدخول إلى لوحة التحكم (طلبات تعديل البيانات) للاطلاع على الفروقات واعتمادها فرادى أو دفعة واحدة.");

        return Command::SUCCESS;
    }
}
