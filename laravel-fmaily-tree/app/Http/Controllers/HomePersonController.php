<?php

namespace App\Http\Controllers;

use App\Models\Marriage;
use App\Models\Person;
use App\Services\ChildOrderService;
use Illuminate\Http\Request;

class HomePersonController extends Controller
{
    public function personsWhereHasBadges(Request $request)
    {
        // تم استخدام whereHas لفلترة الأشخاص الذين لديهم أوسمة فقط
        // ثم استخدمنا with('padges') لتحميل هذه الأوسمة مسبقًا في استعلام واحد فقط
        $persons = Person::whereHas('padges')
            ->with(['padges' => fn ($q) => $q->orderBy('sort_order')])
            ->get();

        return view('web-site.persons.badges', ['persons' => $persons]);
    }

    public function show(Person $person)
    {
        // استخدام Route Model Binding يجلب الشخص تلقائيًا
        // نقوم بتحميل العلاقات الأساسية لتحسين الأداء
        $person->load([
            'padges', // الأوسمة
            'parent:id,first_name,last_name,gender,birth_date,death_date,photo_url,privacy_settings,parent_id', // الأب
            'mother:id,first_name,last_name,gender,birth_date,death_date,photo_url,privacy_settings,parent_id', // الأم
            'location:id,name', // الموقع
            'locations:id,name', // المواقع المتعددة
            'contactAccounts:id,person_id,type,value,visibility,label,sort_order', // حسابات التواصل
            'articles:id,title,person_id,category_id', // المقالات
            'articles.category:id,name', // فئات المقالات
        ]);

        $privacyService = app(\App\Services\ProfilePrivacyService::class);

        // فلترة حسابات التواصل للمشاهد
        if ($person->relationLoaded('contactAccounts')) {
            $filteredAccounts = $person->contactAccounts->filter(function ($acc) use ($privacyService, $person) {
                return $privacyService->canViewContactAccount($acc, $person, request());
            })->values();
            $person->setRelation('contactAccounts', $filteredAccounts);
        }

        // جلب الزوجات/الزوج مرتبة (الأولى ثم الثانية...)
        $spouses = collect();
        if ($person->gender === 'male') {
            // جلب الزوجات باستخدام Marriage model مرتبة لتجنب مشكلة ambiguous column
            $wifeIdsOrdered = Marriage::where('husband_id', $person->id)
                ->ordered()
                ->pluck('wife_id');

            if ($wifeIdsOrdered->isNotEmpty()) {
                $byId = Person::whereIn('id', $wifeIdsOrdered)
                    ->select('id', 'first_name', 'last_name', 'gender', 'birth_date', 'death_date', 'photo_url', 'privacy_settings', 'parent_id')
                    ->get()->keyBy('id');
                foreach ($wifeIdsOrdered as $wid) {
                    if (isset($byId[$wid])) {
                        $spouses->push($byId[$wid]);
                    }
                }
            }
        } else {
            // جلب الزوج (الأول حسب الترتيب)
            $marriage = Marriage::where('wife_id', $person->id)->ordered()->first();
            if ($marriage && $marriage->husband_id) {
                $husband = Person::where('id', $marriage->husband_id)
                    ->select('id', 'first_name', 'last_name', 'gender', 'birth_date', 'death_date', 'photo_url', 'privacy_settings', 'parent_id')
                    ->first();
                if ($husband) {
                    $spouses = collect([$husband]);
                }
            }
        }

        // جلب الأخوة (الأبناء المشتركون مع نفس الأب أو الأم)
        $siblings = collect();
        if ($person->parent_id) {
            $siblings = Person::where('parent_id', $person->parent_id)
                ->where('id', '!=', $person->id)
                ->select('id', 'first_name', 'last_name', 'gender', 'birth_date', 'death_date', 'photo_url', 'privacy_settings', 'parent_id')
                ->get();
        }
        if ($person->mother_id) {
            $motherSiblings = Person::where('mother_id', $person->mother_id)
                ->where('id', '!=', $person->id)
                ->select('id', 'first_name', 'last_name', 'gender', 'birth_date', 'death_date', 'photo_url', 'privacy_settings', 'parent_id')
                ->get();
            $siblings = $siblings->merge($motherSiblings)->unique('id');
        }

        if ($person->gender === 'male') {
            $children = Person::where('parent_id', $person->id)
                ->select('id', 'first_name', 'last_name', 'gender', 'birth_date', 'death_date', 'photo_url', 'privacy_settings', 'parent_id', 'mother_id', 'display_order')
                ->get();
        } else {
            $children = Person::where('mother_id', $person->id)
                ->select('id', 'first_name', 'last_name', 'gender', 'birth_date', 'death_date', 'photo_url', 'privacy_settings', 'parent_id')
                ->get();
        }
        $children = app(ChildOrderService::class)->orderForParent($person, $children, useDisplayOrderByDefault: false);

        // إرسال بيانات الشخص إلى الـ view
        return view('web-site.persons.show', [
            'person' => $person,
            'siblings' => $siblings,
            'children' => $children,
            'spouses' => $spouses,
            'privacyService' => $privacyService,
        ]);
    }
}
