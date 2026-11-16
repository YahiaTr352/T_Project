<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Spatie\Permission\Models\Permission;

class PermissionCatalog
{
    private const GROUP_LABELS = [
        'people' => 'الشخصيات',
        'marriages' => 'الزواج',
        'breastfeeding' => 'الرضاعة',
        'friendships' => 'الأصدقاء',
        'locations' => 'الأماكن',
        'articles' => 'المقالات',
        'categories' => 'التصنيفات',
        'stories' => 'القصص',
        'images' => 'الصور',
        'padges' => 'الشارات',
        'site-content' => 'محتوى الموقع',
        'slideshow' => 'العرض المتحرك',
        'home-gallery' => 'معرض الرئيسية',
        'courses' => 'الدورات',
        'programs' => 'البرامج',
        'products' => 'المنتجات',
        'councils' => 'المجالس',
        'walking-program' => 'برنامج المشي',
        'notifications' => 'الإشعارات',
        'logs' => 'سجلات النظام',
        'system' => 'النظام',
        'audit' => 'التدقيق',
        'visit-logs' => 'سجل الزيارات',
        'dashboard' => 'لوحة التحكم',
        'roles' => 'الأدوار',
        'users' => 'المستخدمون',
        'reports' => 'التقارير',
        'chat' => 'الشات والدردشة',
        'ai' => 'الذكاء الاصطناعي',
        'other' => 'أخرى',
    ];

    /**
     * @return Collection<string, Collection<int, Permission>>
     */
    public function grouped(): Collection
    {
        return Permission::query()
            ->orderBy('name')
            ->get()
            ->groupBy(function (Permission $permission) {
                $parts = explode('.', $permission->name);

                return $parts[0] ?: 'other';
            })
            ->sortKeys();
    }

    public function labelForGroup(string $group): string
    {
        return self::GROUP_LABELS[$group] ?? $group;
    }

    /**
     * @param  Collection<string, Collection<int, Permission>>  $grouped
     * @return array<string, string>
     */
    public function labelsFor(Collection $grouped): array
    {
        return $grouped->keys()->mapWithKeys(fn (string $key) => [
            $key => $this->labelForGroup($key),
        ])->all();
    }
}
