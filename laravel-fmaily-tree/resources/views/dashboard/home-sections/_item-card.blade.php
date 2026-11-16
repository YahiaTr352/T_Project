@php
    $typeConfig = [
        'rich_text' => ['name' => 'نص غني', 'icon' => 'fa-align-right', 'badge' => 'primary'],
        'text' => ['name' => 'نص', 'icon' => 'fa-font', 'badge' => 'secondary'],
        'image' => ['name' => 'صورة', 'icon' => 'fa-image', 'badge' => 'success'],
        'video' => ['name' => 'فيديو', 'icon' => 'fa-play-circle', 'badge' => 'danger'],
        'table' => ['name' => 'جدول', 'icon' => 'fa-table', 'badge' => 'warning'],
        'card' => ['name' => 'بطاقة', 'icon' => 'fa-id-card', 'badge' => 'info'],
        'button' => ['name' => 'زر', 'icon' => 'fa-mouse-pointer', 'badge' => 'dark'],
        'html' => ['name' => 'HTML & JS', 'icon' => 'fa-code', 'badge' => 'indigo'],
        'countdown' => ['name' => 'عداد تنازلي', 'icon' => 'fa-stopwatch', 'badge' => 'warning'],
        'spacer' => ['name' => 'فاصل', 'icon' => 'fa-arrows-alt-v', 'badge' => 'secondary'],
    ];
    $config = $typeConfig[$item->item_type] ?? ['name' => $item->item_type, 'icon' => 'fa-cube', 'badge' => 'secondary'];
    $content = $item->content ?? [];
    $settings = $item->settings ?? [];
@endphp

<div class="item-card mb-3" data-id="{{ $item->id }}" data-type="{{ $item->item_type }}">
    <!-- Item Header -->
    <div class="item-header d-flex align-items-center justify-content-between p-3" onclick="toggleItem(this)">
        <div class="item-header-left d-flex align-items-center" style="gap: 12px; flex: 1;">
            <i class="fas fa-grip-vertical item-drag-handle text-muted" title="اسحب لتغيير الترتيب" onclick="event.stopPropagation()"></i>
            <span class="badge badge-{{ $config['badge'] }} px-2 py-1" style="font-size: 0.75rem;">
                <i class="fas {{ $config['icon'] }} mr-1"></i>{{ $config['name'] }}
            </span>
            <span class="text-truncate font-weight-bold" style="max-width: 380px; font-size: 0.88rem;">
                @if($item->item_type === 'html')
                    كود مخصص ({{ strlen($content['html'] ?? '') }} حرف - {{ substr_count($content['html'] ?? '', "\n") + 1 }} سطر)
                    @if(strpos($content['html'] ?? '', '<script') !== false)
                        <span class="badge badge-dark ml-1" style="font-size: 0.68rem;"><i class="fab fa-js mr-1 text-warning"></i>JavaScript</span>
                    @endif
                @elseif($item->item_type === 'rich_text' && isset($content['html']))
                    {{ Str::limit(strip_tags($content['html']), 50) }}
                @elseif($item->item_type === 'text' && isset($content['text']))
                    {{ Str::limit($content['text'], 50) }}
                @elseif($item->item_type === 'image')
                    {{ $content['alt'] ?? ($content['caption'] ?? 'صورة مخصصة') }}
                @elseif($item->item_type === 'video')
                    {{ $content['title'] ?? ($item->youtube_url ? 'فيديو يوتيوب' : 'فيديو مرفوع') }}
                @elseif($item->item_type === 'table')
                    {{ $content['title'] ?? 'جدول بيانات' }}
                @elseif($item->item_type === 'card')
                    {{ $content['title'] ?? 'بطاقة مميزة' }}
                @elseif($item->item_type === 'button')
                    {{ $content['text'] ?? 'زر تحويل' }}
                @elseif($item->item_type === 'countdown')
                    عداد تنازلي ({{ $content['title'] ?? ($content['target_date'] ? 'إلى: '.$content['target_date'] : 'بدون تاريخ') }})
                @elseif($item->item_type === 'spacer')
                    {{ ($settings['height'] ?? 40) }}px فاصل
                @else
                    عنصر #{{ $item->display_order }}
                @endif
            </span>
        </div>
        <div class="item-header-actions d-flex align-items-center" style="gap: 6px;" onclick="event.stopPropagation()">
            <button type="button" class="btn btn-sm btn-outline-primary" title="تعديل العنصر"
                    onclick="event.stopPropagation(); editItem({{ $item->id }})">
                <i class="fas fa-edit mr-1"></i>تعديل
            </button>
            <button type="button" class="btn btn-sm btn-outline-danger" title="حذف العنصر"
                    onclick="deleteItem({{ $item->id }})">
                <i class="fas fa-trash"></i>
            </button>
        </div>
    </div>

    <!-- Item Body (Preview) -->
    <div class="item-body p-3 border-top">
        <div class="item-preview-content">
            @switch($item->item_type)
                @case('html')
                    @php
                        $htmlStr = $content['html'] ?? '';
                        $hasScript = strpos($htmlStr, '<script') !== false;
                        $hasStyle = strpos($htmlStr, '<style') !== false;
                    @endphp
                    <div class="p-3 rounded border" style="background: rgba(99, 102, 241, 0.05); border-color: rgba(99, 102, 241, 0.2) !important;">
                        <div class="d-flex align-items-center justify-content-between flex-wrap mb-2" style="gap: 8px;">
                            <div class="d-flex align-items-center" style="gap: 6px;">
                                <span class="badge badge-primary"><i class="fas fa-code mr-1"></i>كود HTML مخصص</span>
                                @if($hasScript)
                                    <span class="badge badge-warning text-dark"><i class="fab fa-js mr-1"></i>يتضمن سكربت تفاعلي</span>
                                @endif
                                @if($hasStyle)
                                    <span class="badge badge-info"><i class="fab fa-css3-alt mr-1"></i>يتضمن تنسيقات CSS</span>
                                @endif
                            </div>
                            <span class="text-muted small">حجم الكود: {{ number_format(strlen($htmlStr)) }} بايت</span>
                        </div>
                        <div style="max-height: 90px; overflow: hidden; position: relative;" class="rounded bg-dark p-2 text-left" dir="ltr">
                            <code class="text-light" style="font-size: 0.78rem; font-family: monospace; white-space: pre-wrap;">{{ Str::limit($htmlStr, 220) }}</code>
                            <div style="position: absolute; bottom: 0; left: 0; right: 0; height: 30px; background: linear-gradient(transparent, #0f172a);"></div>
                        </div>
                    </div>
                    @break

                @case('rich_text')
                    <div style="direction: rtl; max-height: 180px; overflow: hidden; position: relative;">
                        {!! $content['html'] ?? '<span class="text-muted">لا يوجد محتوى</span>' !!}
                        <div style="position: absolute; bottom: 0; left: 0; right: 0; height: 35px; background: linear-gradient(transparent, rgba(255,255,255,0.8));"></div>
                    </div>
                    @break
                    
                @case('text')
                    <p class="text-muted mb-0">{{ Str::limit($content['text'] ?? '', 200) }}</p>
                    @break
                    
                @case('image')
                    @if($item->image_url)
                        <div class="text-center">
                            <img src="{{ $item->image_url }}" alt="{{ $content['alt'] ?? '' }}" 
                                 style="max-height: 160px; max-width: 100%; border-radius: 10px; object-fit: cover;">
                        </div>
                        @if(isset($content['caption']) && $content['caption'])
                            <p class="text-center text-muted small mt-2 mb-0">{{ $content['caption'] }}</p>
                        @endif
                    @else
                        <div class="text-center py-3 text-muted">
                            <i class="fas fa-image fa-2x mb-1" style="opacity: 0.4;"></i>
                            <p class="small mb-0">لم يتم رفع صورة</p>
                        </div>
                    @endif
                    @break
                    
                @case('video')
                    @if($item->youtube_url)
                        @php
                            $videoId = '';
                            if (preg_match('/(?:youtube\.com\/(?:watch\?v=|embed\/)|youtu\.be\/)([\w-]+)/', $item->youtube_url, $m)) {
                                $videoId = $m[1];
                            }
                        @endphp
                        @if($videoId)
                            <div class="text-center">
                                <img src="https://img.youtube.com/vi/{{ $videoId }}/mqdefault.jpg" 
                                     alt="{{ $content['title'] ?? 'فيديو' }}"
                                     style="max-height: 150px; border-radius: 10px;">
                                <div class="mt-2">
                                    <span class="badge badge-danger"><i class="fab fa-youtube mr-1"></i>{{ $content['title'] ?? 'فيديو يوتيوب' }}</span>
                                </div>
                            </div>
                        @endif
                    @elseif($item->image_url)
                        <div class="text-center">
                            <span class="badge badge-dark"><i class="fas fa-video mr-1"></i>فيديو مرفوع: {{ $content['title'] ?? 'بدون عنوان' }}</span>
                        </div>
                    @else
                        <div class="text-center text-muted py-3">
                            <i class="fas fa-play-circle fa-2x" style="opacity: 0.4;"></i>
                        </div>
                    @endif
                    @break
                    
                @case('table')
                    @if(isset($content['table_data']) && is_array($content['table_data']))
                        <div style="overflow-x: auto;">
                            <table class="table table-sm table-bordered mb-0" style="font-size: 0.8rem;">
                                @foreach($content['table_data'] as $ri => $row)
                                    <tr>
                                        @foreach($row as $cell)
                                            @if($ri === 0)
                                                <th style="background: rgba(0,0,0,0.04); padding: 5px 8px;">{{ $cell }}</th>
                                            @else
                                                <td style="padding: 5px 8px;">{{ $cell }}</td>
                                            @endif
                                        @endforeach
                                    </tr>
                                @endforeach
                            </table>
                        </div>
                    @else
                        <span class="text-muted small">جدول فارغ</span>
                    @endif
                    @break
                    
                @case('card')
                    <div class="d-flex align-items-start" style="gap: 14px;">
                        @if($item->image_url)
                            <img src="{{ $item->image_url }}" alt="{{ $content['title'] ?? '' }}" 
                                 style="width: 70px; height: 55px; object-fit: cover; border-radius: 8px;">
                        @endif
                        <div>
                            <strong class="d-block mb-1" style="font-size: 0.92rem;">{{ $content['title'] ?? 'بطاقة بدون عنوان' }}</strong>
                            @if(isset($content['description']))
                                <p class="text-muted small mb-0">{{ Str::limit($content['description'], 100) }}</p>
                            @endif
                        </div>
                    </div>
                    @break
                    
                @case('button')
                    <div>
                        <a href="javascript:void(0)" class="btn btn-{{ $settings['btn_color'] ?? 'primary' }} btn-sm {{ ($settings['btn_block'] ?? false) ? 'btn-block' : '' }}">
                            @if(isset($content['icon']) && $content['icon'])
                                <i class="{{ $content['icon'] }} mr-1"></i>
                            @endif
                            {{ $content['text'] ?? 'زر' }}
                        </a>
                        @if(isset($content['url']) && $content['url'])
                            <small class="text-muted d-block mt-1"><i class="fas fa-link mr-1"></i>{{ Str::limit($content['url'], 40) }}</small>
                        @endif
                    </div>
                    @break

                @case('countdown')
                    <div class="d-flex align-items-center justify-content-between flex-wrap p-2 rounded" style="background: rgba(234, 88, 12, 0.05); border: 1px solid rgba(234, 88, 12, 0.2); gap: 12px;">
                        <div class="d-flex align-items-center" style="gap: 12px;">
                            @if($item->image_url)
                                <img src="{{ $item->image_url }}" alt="صورة العداد" style="width: 60px; height: 50px; object-fit: cover; border-radius: 8px;">
                            @else
                                <div class="rounded d-flex align-items-center justify-content-center bg-white text-warning shadow-sm" style="width: 50px; height: 50px; font-size: 1.3rem;">
                                    <i class="fas fa-stopwatch"></i>
                                </div>
                            @endif
                            <div>
                                <strong class="d-block text-gray-800" style="font-size: 0.92rem;">
                                    {{ $content['title'] ?? 'عداد تنازلي' }}
                                    @if(!empty($content['badge_text']))
                                        <span class="badge badge-warning text-dark ml-1">{{ $content['badge_text'] }}</span>
                                    @endif
                                </strong>
                                <small class="text-muted d-block">
                                    <i class="far fa-calendar-alt text-primary mr-1"></i>
                                    {{ $content['target_date'] ? 'تاريخ الهدف: ' . $content['target_date'] : 'لم يتم تحديد تاريخ' }}
                                </small>
                            </div>
                        </div>
                        <div class="d-flex align-items-center flex-wrap" style="gap: 6px;">
                            <span class="badge badge-light border">نمط: {{ $settings['layout_variant'] ?? 'overlay' }}</span>
                            @if(!empty($settings['show_days'])) <span class="badge badge-secondary">أيام</span> @endif
                            @if(!empty($settings['show_hours'])) <span class="badge badge-secondary">ساعات</span> @endif
                            @if(!empty($settings['show_minutes'])) <span class="badge badge-secondary">دقائق</span> @endif
                            @if(!empty($settings['show_seconds'])) <span class="badge badge-secondary">ثواني</span> @endif
                        </div>
                    </div>
                    @break
                    
                @case('spacer')
                    <div class="text-center py-1">
                        <div style="height: {{ min(($settings['height'] ?? 40), 50) }}px; display: flex; align-items: center; justify-content: center;">
                            @if($settings['show_line'] ?? false)
                                <hr style="width: 100%; margin: 0;">
                            @else
                                <span class="text-muted small">{{ ($settings['height'] ?? 40) }}px فاصل مسافة</span>
                            @endif
                        </div>
                    </div>
                    @break
                    
                @default
                    <span class="text-muted small">عنصر {{ $item->item_type }}</span>
            @endswitch
        </div>
    </div>
</div>
