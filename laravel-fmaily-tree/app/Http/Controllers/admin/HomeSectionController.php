<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\HomeSection;
use App\Models\HomeSectionItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class HomeSectionController extends Controller
{
    /**
     * عرض صفحة إدارة الأقسام الديناميكية
     */
    public function index()
    {
        $sections = HomeSection::orderBy('display_order')->with('items')->get();
        
        $stats = [
            'total' => HomeSection::count(),
            'active' => HomeSection::where('is_active', true)->count(),
            'inactive' => HomeSection::where('is_active', false)->count(),
        ];
        
        return view('dashboard.home-sections.index', compact('sections', 'stats'));
    }

    /**
     * عرض نموذج إنشاء قسم جديد
     */
    public function create()
    {
        return view('dashboard.home-sections.create');
    }

    /**
     * حفظ قسم جديد (مع إنشاء المحتوى المبدئي المباشر إن وُجد)
     */
    public function store(Request $request)
    {
        $request->validate(array_merge([
            'title'                => 'required|string|max:255',
            'section_type'         => 'required|string|max:255',
            'is_active'            => 'nullable|boolean',
            'css_classes'          => 'nullable|string',
            'content_source_type'  => 'nullable|string|max:255',
            'html_content'         => 'nullable|string',
            'rich_text_content'    => 'nullable|string',
            'youtube_url'          => 'nullable|url|max:500',
            'media'                => 'nullable|file|mimes:jpeg,png,jpg,gif,webp,svg,mp4,avi,mov|max:20480',
            'countdown_target_date'     => 'nullable|string|max:100',
            'countdown_title'           => 'nullable|string|max:255',
            'countdown_description'     => 'nullable|string|max:2000',
            'countdown_badge_text'      => 'nullable|string|max:100',
            'countdown_button_text'     => 'nullable|string|max:100',
            'countdown_button_url'      => 'nullable|url|max:500',
            'countdown_expired_message' => 'nullable|string|max:500',
            'countdown_layout_variant'  => 'nullable|string|in:overlay,side_by_side,stacked,minimal',
            'countdown_theme_style'     => 'nullable|string|in:glass,solid,gradient',
            'countdown_show_days'       => 'nullable',
            'countdown_show_hours'      => 'nullable',
            'countdown_show_minutes'    => 'nullable',
            'countdown_show_seconds'    => 'nullable',
            'countdown_auto_hide'       => 'nullable',
        ], $this->settingsValidationRules()));

        $lastOrder = HomeSection::max('display_order') ?? 0;

        $settings = $request->settings ?? [];
        $settings['show_title'] = isset($settings['show_title']) ? true : ($request->has('settings.show_title') ? true : true);
        $settings['carousel_autoplay'] = isset($settings['carousel_autoplay']) ? true : false;
        if (isset($settings['source_ids'])) {
            $settings['source_ids'] = array_map('intval', array_filter($settings['source_ids']));
        }

        $contentSource = $request->content_source_type;
        if ($contentSource && !array_key_exists($contentSource, HomeSection::ALLOWED_SOURCES)) {
            $contentSource = null;
        }

        $section = DB::transaction(function () use ($request, $lastOrder, $settings, $contentSource) {
            $section = HomeSection::create([
                'title' => $request->title,
                'section_type' => $request->section_type,
                'display_order' => $lastOrder + 1,
                'is_active' => $request->has('is_active') ? true : false,
                'settings' => $settings,
                'css_classes' => $request->css_classes,
                'content_source_type' => $contentSource,
            ]);

            // إنشاء المحتوى المبدئي فوراً إذا تم تمريره (لمنع الخطوات المزدوجة والمحررات الفارغة)
            if ($request->filled('html_content') || in_array($request->section_type, ['custom', 'html'])) {
                $htmlCode = $request->input('html_content', '');
                if (!empty(trim($htmlCode))) {
                    HomeSectionItem::create([
                        'home_section_id' => $section->id,
                        'item_type' => 'html',
                        'content' => ['html' => strip_contenteditable_from_html($htmlCode)],
                        'display_order' => 1,
                        'settings' => [],
                    ]);
                }
            } elseif ($request->filled('rich_text_content') || $request->section_type === 'rich_text') {
                $richText = $request->input('rich_text_content', '');
                if (!empty(trim($richText))) {
                    HomeSectionItem::create([
                        'home_section_id' => $section->id,
                        'item_type' => 'rich_text',
                        'content' => ['html' => clean_html($richText)],
                        'display_order' => 1,
                        'settings' => [],
                    ]);
                }
            } elseif ($request->section_type === 'video_section' && ($request->filled('youtube_url') || $request->hasFile('media'))) {
                $mediaPath = null;
                if ($request->hasFile('media')) {
                    $mediaPath = $request->file('media')->store('home-sections/videos', 'public');
                }
                HomeSectionItem::create([
                    'home_section_id' => $section->id,
                    'item_type' => 'video',
                    'youtube_url' => $request->youtube_url,
                    'media_path' => $mediaPath,
                    'content' => ['title' => $request->title],
                    'display_order' => 1,
                    'settings' => [],
                ]);
            } elseif ($request->section_type === 'countdown') {
                $mediaPath = null;
                if ($request->hasFile('media')) {
                    $mediaPath = $request->file('media')->store('home-sections/images', 'public');
                }
                HomeSectionItem::create([
                    'home_section_id' => $section->id,
                    'item_type'       => 'countdown',
                    'media_path'      => $mediaPath,
                    'content'         => [
                        'title'           => $request->input('countdown_title', $request->title),
                        'description'     => $request->input('countdown_description'),
                        'target_date'     => $request->input('countdown_target_date'),
                        'badge_text'      => $request->input('countdown_badge_text'),
                        'button_text'     => $request->input('countdown_button_text'),
                        'button_url'      => $request->input('countdown_button_url'),
                        'expired_message' => $request->input('countdown_expired_message'),
                    ],
                    'settings'        => [
                        'layout_variant'      => $request->input('countdown_layout_variant', 'overlay'),
                        'theme_style'         => $request->input('countdown_theme_style', 'glass'),
                        'show_days'           => $request->has('countdown_show_days') ? (bool)$request->countdown_show_days : true,
                        'show_hours'          => $request->has('countdown_show_hours') ? (bool)$request->countdown_show_hours : true,
                        'show_minutes'        => $request->has('countdown_show_minutes') ? (bool)$request->countdown_show_minutes : true,
                        'show_seconds'        => $request->has('countdown_show_seconds') ? (bool)$request->countdown_show_seconds : true,
                        'auto_hide_on_expire' => $request->has('countdown_auto_hide') ? (bool)$request->countdown_auto_hide : false,
                    ],
                    'display_order'   => 1,
                ]);
            }

            return $section;
        });

        return redirect()->route('dashboard.home-sections.edit', $section)
            ->with('success', 'تم إنشاء القسم ومحتواه بنجاح!');
    }

    /**
     * عرض نموذج تعديل قسم (واجهة بناء وتعديل القسم)
     */
    public function edit(HomeSection $homeSection)
    {
        $homeSection->load('items');
        return view('dashboard.home-sections.edit', compact('homeSection'));
    }

    /**
     * تحديث قسم (مع تحديث المحتوى المباشر في نقرة واحدة)
     */
    public function update(Request $request, HomeSection $homeSection)
    {
        $request->validate(array_merge([
            'title'                => 'required|string|max:255',
            'section_type'         => 'required|string|max:255',
            'is_active'            => 'nullable|boolean',
            'css_classes'          => 'nullable|string',
            'content_source_type'  => 'nullable|string|max:255',
            'primary_html_content' => 'nullable|string',
            'primary_rich_content' => 'nullable|string',
            'media'                => 'nullable|file|mimes:jpeg,png,jpg,gif,webp,svg,mp4,avi,mov|max:20480',
            'remove_media'         => 'nullable|boolean',
            'countdown_target_date'     => 'nullable|string|max:100',
            'countdown_title'           => 'nullable|string|max:255',
            'countdown_description'     => 'nullable|string|max:2000',
            'countdown_badge_text'      => 'nullable|string|max:100',
            'countdown_button_text'     => 'nullable|string|max:100',
            'countdown_button_url'      => 'nullable|url|max:500',
            'countdown_expired_message' => 'nullable|string|max:500',
            'countdown_layout_variant'  => 'nullable|string|in:overlay,side_by_side,stacked,minimal',
            'countdown_theme_style'     => 'nullable|string|in:glass,solid,gradient',
            'countdown_show_days'       => 'nullable',
            'countdown_show_hours'      => 'nullable',
            'countdown_show_minutes'    => 'nullable',
            'countdown_show_seconds'    => 'nullable',
            'countdown_auto_hide'       => 'nullable',
        ], $this->settingsValidationRules()));

        $settings = $request->settings ?? [];
        $settings['show_title'] = isset($settings['show_title']) ? true : false;
        $settings['carousel_autoplay'] = isset($settings['carousel_autoplay']) ? true : false;
        if (isset($settings['source_ids'])) {
            $settings['source_ids'] = array_map('intval', array_filter($settings['source_ids']));
        }

        $contentSource = $request->content_source_type;
        if ($contentSource && !array_key_exists($contentSource, HomeSection::ALLOWED_SOURCES)) {
            $contentSource = null;
        }

        DB::transaction(function () use ($request, $homeSection, $settings, $contentSource) {
            $homeSection->update([
                'title' => $request->title,
                'section_type' => $request->section_type,
                'is_active' => $request->has('is_active') ? true : false,
                'settings' => $settings,
                'css_classes' => $request->css_classes,
                'content_source_type' => $contentSource ?: null,
            ]);

            // تحديث كود الـ HTML المباشر إذا كان مرسلاً من نموذج التعديل (لحفظ القسم ومحتواه بضغطة زر واحدة)
            if ($request->has('primary_html_content')) {
                $rawHtml = $request->input('primary_html_content', '');
                $htmlItem = $homeSection->items()->where('item_type', 'html')->first();
                if ($htmlItem) {
                    $htmlItem->update([
                        'content' => ['html' => strip_contenteditable_from_html($rawHtml)],
                    ]);
                } elseif (!empty(trim($rawHtml))) {
                    HomeSectionItem::create([
                        'home_section_id' => $homeSection->id,
                        'item_type' => 'html',
                        'content' => ['html' => strip_contenteditable_from_html($rawHtml)],
                        'display_order' => 1,
                        'settings' => [],
                    ]);
                }
            }

            // تحديث النص الغني المباشر إذا كان مرسلاً
            if ($request->has('primary_rich_content')) {
                $rawRich = $request->input('primary_rich_content', '');
                $richItem = $homeSection->items()->where('item_type', 'rich_text')->first();
                if ($richItem) {
                    $richItem->update([
                        'content' => ['html' => clean_html($rawRich)],
                    ]);
                } elseif (!empty(trim($rawRich))) {
                    HomeSectionItem::create([
                        'home_section_id' => $homeSection->id,
                        'item_type' => 'rich_text',
                        'content' => ['html' => clean_html($rawRich)],
                        'display_order' => 1,
                        'settings' => [],
                    ]);
                }
            }

            // تحديث محتوى العداد التنازلي المباشر إذا كان القسم عداداً أو مرسلاً حقوله
            if ($request->has('countdown_target_date') || $homeSection->section_type === 'countdown' || $request->has('countdown_layout_variant')) {
                $countdownItem = $homeSection->items()->where('item_type', 'countdown')->first();
                $cMedia = $countdownItem?->media_path;
                if ($request->hasFile('media')) {
                    if ($cMedia && !filter_var($cMedia, FILTER_VALIDATE_URL)) {
                        Storage::disk('public')->delete($cMedia);
                    }
                    $cMedia = $request->file('media')->store('home-sections/images', 'public');
                } elseif ($request->boolean('remove_media')) {
                    if ($cMedia && !filter_var($cMedia, FILTER_VALIDATE_URL)) {
                        Storage::disk('public')->delete($cMedia);
                    }
                    $cMedia = null;
                }

                $cContent = [
                    'title'           => $request->input('countdown_title'),
                    'description'     => $request->input('countdown_description'),
                    'target_date'     => $request->input('countdown_target_date'),
                    'badge_text'      => $request->input('countdown_badge_text'),
                    'button_text'     => $request->input('countdown_button_text'),
                    'button_url'      => $request->input('countdown_button_url'),
                    'expired_message' => $request->input('countdown_expired_message'),
                ];
                $cSettings = [
                    'layout_variant'      => $request->input('countdown_layout_variant', 'overlay'),
                    'theme_style'         => $request->input('countdown_theme_style', 'glass'),
                    'show_days'           => $request->has('countdown_show_days') ? (bool)$request->countdown_show_days : false,
                    'show_hours'          => $request->has('countdown_show_hours') ? (bool)$request->countdown_show_hours : false,
                    'show_minutes'        => $request->has('countdown_show_minutes') ? (bool)$request->countdown_show_minutes : false,
                    'show_seconds'        => $request->has('countdown_show_seconds') ? (bool)$request->countdown_show_seconds : false,
                    'auto_hide_on_expire' => $request->has('countdown_auto_hide') ? (bool)$request->countdown_auto_hide : false,
                ];

                if ($countdownItem) {
                    $countdownItem->update([
                        'content'    => $cContent,
                        'media_path' => $cMedia,
                        'settings'   => $cSettings,
                    ]);
                } elseif ($request->filled('countdown_target_date') || $homeSection->section_type === 'countdown') {
                    HomeSectionItem::create([
                        'home_section_id' => $homeSection->id,
                        'item_type'       => 'countdown',
                        'content'         => $cContent,
                        'media_path'      => $cMedia,
                        'settings'        => $cSettings,
                        'display_order'   => 1,
                    ]);
                }
            }
        });

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'تم حفظ جميع التغييرات بنجاح',
            ]);
        }

        return redirect()->route('dashboard.home-sections.edit', $homeSection)
            ->with('success', 'تم حفظ جميع التغييرات بنجاح');
    }

    /**
     * معاينة كاملة ومباشرة للقسم في بيئة معزولة تماثل الصفحة الرئيسية
     */
    public function preview(HomeSection $homeSection)
    {
        $homeSection->load('items');

        if ($homeSection->content_source_type) {
            $homeSection->content_source_items = $homeSection->getContentSourceCollection();
        }

        return view('dashboard.home-sections.preview', compact('homeSection'));
    }

    /**
     * حذف قسم
     */
    public function destroy(HomeSection $homeSection)
    {
        // حذف جميع العناصر المرتبطة
        foreach ($homeSection->items as $item) {
            if ($item->media_path && !filter_var($item->media_path, FILTER_VALIDATE_URL)) {
                Storage::disk('public')->delete($item->media_path);
            }
        }
        
        $homeSection->delete();

        return redirect()->route('dashboard.home-sections.index')
            ->with('success', 'تم حذف القسم بنجاح');
    }

    /**
     * إعادة ترتيب الأقسام
     */
    public function reorder(Request $request)
    {
        $request->validate([
            'orders' => 'required|array',
            'orders.*' => 'required|integer|exists:home_sections,id',
        ]);

        DB::transaction(function () use ($request) {
            foreach ($request->orders as $order => $sectionId) {
                HomeSection::where('id', $sectionId)
                    ->update(['display_order' => $order + 1]);
            }
        });

        return response()->json(['success' => true, 'message' => 'تم إعادة الترتيب بنجاح']);
    }

    /**
     * تفعيل/تعطيل قسم
     */
    public function toggle(HomeSection $homeSection)
    {
        $homeSection->toggle();

        return redirect()->route('dashboard.home-sections.index')
            ->with('success', 'تم تحديث حالة القسم بنجاح');
    }

    /**
     * قواعد التحقق المشتركة لحقل settings بين store() و update()
     */
    private function settingsValidationRules(): array
    {
        $colorRule  = 'nullable|string|max:50';
        $sizeValues = implode(',', ['', '13px', '15px', '17px', '20px', '24px', '30px', '36px', '48px']);
        $weightVals = implode(',', ['', '400', '600', '700', '800', '900']);
        $alignVals  = 'right,center,left';

        return [
            'settings'                        => 'nullable|array',
            // Content
            'settings.subtitle'               => 'nullable|string|max:500',
            'settings.description'            => 'nullable|string|max:2000',
            'settings.icon'                   => 'nullable|string|max:100',
            'settings.show_title'             => 'nullable|boolean',
            // Section-wide design
            'settings.background_color'       => $colorRule,
            'settings.text_color'             => $colorRule,
            'settings.padding_top'            => 'nullable|integer|min:0|max:200',
            'settings.padding_bottom'         => 'nullable|integer|min:0|max:200',
            // Layout
            'settings.layout_style'           => 'nullable|string|in:grid,horizontal,vertical',
            'settings.columns'                => 'nullable|integer|min:1|max:6',
            // Carousel
            'settings.carousel_autoplay'      => 'nullable|boolean',
            'settings.carousel_interval'      => 'nullable|integer|min:1000|max:30000',
            // Source
            'settings.source_limit'           => 'nullable|integer|min:1|max:100',
            'settings.source_order'           => 'nullable|string|in:latest,oldest,name_asc,name_desc',
            'settings.source_mode'            => 'nullable|string|in:all,selected',
            'settings.source_ids'             => 'nullable|array',
            'settings.source_ids.*'           => 'nullable|integer',
            // Typography – Title
            'settings.title_color'            => $colorRule,
            'settings.title_size'             => "nullable|string|in:{$sizeValues}",
            'settings.title_weight'           => "nullable|string|in:{$weightVals}",
            'settings.title_align'            => "nullable|string|in:,{$alignVals}",
            // Typography – Subtitle
            'settings.subtitle_color'         => $colorRule,
            'settings.subtitle_size'          => "nullable|string|in:{$sizeValues}",
            'settings.subtitle_weight'        => "nullable|string|in:{$weightVals}",
            'settings.subtitle_align'         => "nullable|string|in:,{$alignVals}",
            // Typography – Description
            'settings.description_color'      => $colorRule,
            'settings.description_size'       => "nullable|string|in:{$sizeValues}",
            'settings.description_align'      => "nullable|string|in:,{$alignVals}",
        ];
    }

    /**
     * بحث AJAX في مصادر المحتوى (مستخدمين / أشخاص)
     */
    public function searchSource(Request $request)
    {
        $request->validate([
            'type' => 'required|string',
            'q' => 'nullable|string|max:255',
        ]);

        $type = $request->type;
        if (!array_key_exists($type, HomeSection::ALLOWED_SOURCES)) {
            return response()->json([]);
        }

        $q = $request->q ?? '';
        $query = $type::query();

        if ($type === 'App\\Models\\Person') {
            if ($q) {
                $query->where(function ($qb) use ($q) {
                    $qb->where('first_name', 'like', "%{$q}%")
                       ->orWhere('last_name', 'like', "%{$q}%");
                });
            }
            $results = $query->limit(30)->get()->map(function ($p) {
                return [
                    'id' => $p->id,
                    'text' => $p->full_name,
                    'avatar' => $p->avatar,
                ];
            });
        } else {
            if ($q) {
                $query->where(function ($qb) use ($q) {
                    $qb->where('name', 'like', "%{$q}%")
                       ->orWhere('email', 'like', "%{$q}%");
                });
            }
            $results = $query->limit(30)->get()->map(function ($u) {
                return [
                    'id' => $u->id,
                    'text' => $u->name . ($u->email ? " ({$u->email})" : ''),
                    'avatar' => $u->avatar ? asset('storage/' . $u->avatar) : null,
                ];
            });
        }

        return response()->json($results->values());
    }

    /**
     * نسخ قسم
     */
    public function duplicate(HomeSection $homeSection)
    {
        DB::transaction(function () use ($homeSection) {
            $lastOrder = HomeSection::max('display_order') ?? 0;
            
            $newSection = $homeSection->replicate();
            $newSection->title = $homeSection->title . ' (نسخة)';
            $newSection->display_order = $lastOrder + 1;
            $newSection->is_active = false;
            $newSection->save();

            foreach ($homeSection->items as $item) {
                $newItem = $item->replicate();
                $newItem->home_section_id = $newSection->id;
                $newItem->save();
            }
        });

        return redirect()->route('dashboard.home-sections.index')
            ->with('success', 'تم نسخ القسم بنجاح');
    }
}
