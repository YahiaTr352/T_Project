@props([
    'id',
    'imageUrl',
    'name' => null,
    'description' => null,
    'uploaderName' => null,
    'createdAt' => null,
    'status' => 'pending',
    'statusLabel' => null,
    'rejectionReason' => null,
    'extraBadge' => null,
    'previewTitle' => null,
    'approveUrl' => null,
    'rejectUrl' => null,
    'deleteUrl' => null,
    'canManage' => false,
])

@php
    $statusMap = [
        'approved' => 'badge-soft-success',
        'rejected' => 'badge-soft-danger',
        'pending' => 'badge-soft-warning',
    ];
    $badgeClass = $statusMap[$status] ?? 'badge-soft-secondary';
    $displayName = $name ?: 'بدون اسم';
@endphp

@once('visual-library-card-styles')
    <style>
        .visual-lib-card {
            display: flex;
            flex-direction: column;
            height: 100%;
            overflow: hidden;
            padding: 0 !important;
        }
        .visual-lib-media {
            position: relative;
            aspect-ratio: 4 / 3;
            overflow: hidden;
            background-color: #0f172a;
            cursor: zoom-in;
        }
        .visual-lib-media img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .visual-lib-card:hover .visual-lib-media img {
            transform: scale(1.05);
        }
        .visual-lib-overlay {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            background: linear-gradient(to top, rgba(0, 0, 0, 0.55) 0%, rgba(0, 0, 0, 0.1) 60%, transparent 100%);
            opacity: 0;
            transition: opacity 0.25s ease;
        }
        .visual-lib-card:hover .visual-lib-overlay,
        .visual-lib-media:focus-within .visual-lib-overlay {
            opacity: 1;
        }
        .visual-lib-zoom {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 14px;
            border-radius: 9999px;
            background: rgba(255, 255, 255, 0.95);
            color: #0f172a;
            font-size: 11px;
            font-weight: 800;
            border: 0;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.25);
        }
        .visual-lib-zoom:hover {
            background: #ffffff;
        }
        .visual-lib-status {
            position: absolute;
            top: 10px;
            right: 10px;
            display: flex;
            gap: 6px;
        }
        .visual-lib-status .badge {
            font-size: 10px;
            font-weight: 800;
            padding: 4px 10px;
            border-radius: 9999px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
        }
        .visual-lib-body {
            display: flex;
            flex-direction: column;
            gap: 8px;
            padding: 14px 14px 12px;
            flex: 1;
        }
        .visual-lib-title {
            font-size: 13px;
            font-weight: 800;
            margin: 0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .visual-lib-meta {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 11px;
            font-weight: 600;
            flex-wrap: wrap;
        }
        .visual-lib-reason {
            font-size: 11px;
            font-weight: 600;
            border-radius: 10px;
            padding: 8px 10px;
            line-height: 1.6;
        }
        body:not(.dark) .visual-lib-reason {
            background-color: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
        }
        body.dark .visual-lib-reason {
            background-color: rgba(127, 29, 29, 0.25);
            border: 1px solid rgba(248, 113, 113, 0.3);
            color: #fca5a5;
        }
        .visual-lib-actions {
            display: flex;
            gap: 6px;
            margin-top: auto;
            padding-top: 4px;
        }
        .visual-lib-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            border-radius: 10px;
            font-size: 11px;
            font-weight: 800;
            padding: 8px 10px;
            border: 1.5px solid transparent;
            cursor: pointer;
            transition: all 0.18s ease;
            text-decoration: none !important;
        }
        .visual-lib-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        .visual-lib-btn-approve { flex: 1; }
        .visual-lib-btn-reject { flex: 1; }
        body:not(.dark) .visual-lib-btn-approve {
            background-color: #059669;
            border-color: #059669;
            color: #ffffff;
        }
        body:not(.dark) .visual-lib-btn-approve:hover:not(:disabled) {
            background-color: #047857;
        }
        body:not(.dark) .visual-lib-btn-reject {
            background-color: #ffffff;
            border-color: #fecaca;
            color: #b91c1c;
        }
        body:not(.dark) .visual-lib-btn-reject:hover:not(:disabled) {
            background-color: #fef2f2;
        }
        body:not(.dark) .visual-lib-btn-ghost {
            background-color: #ffffff;
            border-color: #e2e8f0;
            color: #64748b;
        }
        body:not(.dark) .visual-lib-btn-ghost:hover:not(:disabled) {
            border-color: #fca5a5;
            color: #dc2626;
            background-color: #fef2f2;
        }
        body.dark .visual-lib-btn-approve {
            background-color: #059669;
            border-color: #10b981;
            color: #ffffff;
        }
        body.dark .visual-lib-btn-approve:hover:not(:disabled) {
            background-color: #047857;
        }
        body.dark .visual-lib-btn-reject {
            background-color: transparent;
            border-color: rgba(248, 113, 113, 0.4);
            color: #fca5a5;
        }
        body.dark .visual-lib-btn-reject:hover:not(:disabled) {
            background-color: rgba(127, 29, 29, 0.3);
        }
        body.dark .visual-lib-btn-ghost {
            background-color: transparent;
            border-color: #334155;
            color: #94a3b8;
        }
        body.dark .visual-lib-btn-ghost:hover:not(:disabled) {
            border-color: rgba(248, 113, 113, 0.5);
            color: #f87171;
        }
    </style>
@endonce

<article class="dashboard-notif-card visual-lib-card" data-visual-card="{{ $id }}">
    <div
        class="visual-lib-media"
        role="button"
        tabindex="0"
        aria-label="معاينة: {{ $displayName }}"
        data-visual-preview-trigger
        data-image="{{ $imageUrl }}"
        data-title="{{ $previewTitle ?? $displayName }}"
    >
        <img src="{{ $imageUrl }}" alt="{{ $displayName }}" loading="lazy">

        <div class="visual-lib-status">
            <span class="badge {{ $badgeClass }}">{{ $statusLabel }}</span>
            @if ($extraBadge)
                <span class="badge badge-soft-info">{{ $extraBadge }}</span>
            @endif
        </div>

        <div class="visual-lib-overlay" aria-hidden="true">
            <span class="visual-lib-zoom">
                <i class="fas fa-search-plus"></i>
                معاينة سريعة
            </span>
        </div>
    </div>

    <div class="visual-lib-body">
        <h3 class="visual-lib-title notif-title" title="{{ $displayName }}">{{ $displayName }}</h3>

        <div class="visual-lib-meta notif-muted">
            <span class="d-inline-flex align-items-center" style="gap:4px;">
                <i class="fas fa-user"></i>
                {{ $uploaderName ?? 'الإدارة' }}
            </span>
            @if ($createdAt)
                <span aria-hidden="true">•</span>
                <span class="d-inline-flex align-items-center" style="gap:4px;">
                    <i class="far fa-calendar-alt"></i>
                    {{ $createdAt }}
                </span>
            @endif
        </div>

        @if ($description)
            <p class="notif-subtitle mb-0" style="font-size:11px;line-height:1.7;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;">
                {{ $description }}
            </p>
        @endif

        @if ($status === 'rejected' && $rejectionReason)
            <div class="visual-lib-reason">
                <i class="fas fa-info-circle ml-1"></i>
                سبب الرفض: {{ $rejectionReason }}
            </div>
        @endif

        @if ($canManage)
            <div class="visual-lib-actions">
                @if ($status === 'pending')
                    @if ($approveUrl)
                        <button
                            type="button"
                            class="visual-lib-btn visual-lib-btn-approve"
                            data-visual-approve="{{ $approveUrl }}"
                            data-visual-id="{{ $id }}"
                        >
                            <i class="fas fa-check"></i>
                            اعتماد
                        </button>
                    @endif
                    @if ($rejectUrl)
                        <button
                            type="button"
                            class="visual-lib-btn visual-lib-btn-reject"
                            data-visual-reject-open="{{ $rejectUrl }}"
                            data-visual-id="{{ $id }}"
                        >
                            <i class="fas fa-times"></i>
                            رفض
                        </button>
                    @endif
                @endif
                @if ($deleteUrl)
                    <button
                        type="button"
                        class="visual-lib-btn visual-lib-btn-ghost"
                        title="حذف نهائي"
                        aria-label="حذف {{ $displayName }}"
                        data-visual-delete="{{ $deleteUrl }}"
                        data-visual-id="{{ $id }}"
                        data-visual-name="{{ $displayName }}"
                    >
                        <i class="fas fa-trash-alt"></i>
                    </button>
                @endif
            </div>
        @endif
    </div>
</article>
