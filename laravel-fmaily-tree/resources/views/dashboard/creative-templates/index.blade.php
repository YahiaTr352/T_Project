@extends('layouts.app')

@section('title', 'مساحة الإبداع')

@push('styles')
    @include('dashboard.notifications.partials.styles')
@endpush

@section('content')
<div class="container-fluid py-4" style="font-family: 'Tajawal', sans-serif;">
    <div
        data-visual-scope
        data-preview-modal="#visualPreviewModal"
        data-reject-modal="#visualRejectModal"
        data-confirm-modal="#visualConfirmModal"
    >
        {{-- Hero — مكون جاهز يدعم الوضع الليلي والنهاري --}}
        <x-dashboard.glass-hero
            title="مساحة الإبداع"
            subtitle="راجع تصاميم الأعضاء واعتمد المناسب منها ليظهر ضمن قوالب نوع الدعوة. اترك مساحة للنص المتغيّر على الخلفية."
        >
            <x-slot name="actions">
                @can('notifications.send')
                    <button
                        type="button"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-500 hover:bg-emerald-400 text-white text-xs font-bold rounded-xl transition-all shadow-md border-0"
                        data-toggle="modal"
                        data-target="#visualUploadModal"
                    >
                        <i class="fas fa-cloud-upload-alt"></i>
                        رفع تصميم
                    </button>
                @endcan
                <a
                    href="{{ route('dashboard.notifications.index') }}"
                    class="inline-flex items-center gap-2 px-4 py-2 text-xs font-bold rounded-xl transition-all border-0"
                    style="background:rgba(255,255,255,0.15);color:#fff;border:1px solid rgba(255,255,255,0.25) !important;"
                >
                    <i class="fas fa-arrow-right"></i>
                    عودة
                </a>
            </x-slot>
        </x-dashboard.glass-hero>

        @include('components.alerts')

        {{-- إحصائيات سريعة --}}
        <div class="row mb-4">
            <div class="col-xl-4 col-md-6 mb-3 mb-xl-0">
                <a
                    href="{{ route('dashboard.creatives.index', ['status' => 'pending']) }}"
                    class="dashboard-notif-card h-100 p-4 d-flex align-items-center text-decoration-none"
                    style="gap:12px;"
                >
                    <span class="action-icon-circle bg-soft-warning shrink-0">
                        <i class="fas fa-hourglass-half"></i>
                    </span>
                    <span>
                        <span class="d-block notif-muted" style="font-size:11px;font-weight:700;">بانتظار المراجعة</span>
                        <span class="d-block notif-title" style="font-size:1.6rem;font-weight:900;line-height:1.2;">{{ number_format($counts['pending'] ?? 0) }}</span>
                    </span>
                </a>
            </div>
            <div class="col-xl-4 col-md-6 mb-3 mb-xl-0">
                <a
                    href="{{ route('dashboard.creatives.index', ['status' => 'approved']) }}"
                    class="dashboard-notif-card h-100 p-4 d-flex align-items-center text-decoration-none"
                    style="gap:12px;"
                >
                    <span class="action-icon-circle bg-soft-success shrink-0">
                        <i class="fas fa-check-circle"></i>
                    </span>
                    <span>
                        <span class="d-block notif-muted" style="font-size:11px;font-weight:700;">تصاميم معتمدة</span>
                        <span class="d-block notif-title" style="font-size:1.6rem;font-weight:900;line-height:1.2;">{{ number_format($counts['approved'] ?? 0) }}</span>
                    </span>
                </a>
            </div>
            <div class="col-xl-4 col-md-6">
                <a
                    href="{{ route('dashboard.creatives.index', ['status' => 'rejected']) }}"
                    class="dashboard-notif-card h-100 p-4 d-flex align-items-center text-decoration-none"
                    style="gap:12px;"
                >
                    <span class="action-icon-circle bg-soft-danger shrink-0">
                        <i class="fas fa-times-circle"></i>
                    </span>
                    <span>
                        <span class="d-block notif-muted" style="font-size:11px;font-weight:700;">تصاميم مرفوضة</span>
                        <span class="d-block notif-title" style="font-size:1.6rem;font-weight:900;line-height:1.2;">{{ number_format($counts['rejected'] ?? 0) }}</span>
                    </span>
                </a>
            </div>
        </div>

        {{-- شريط التصفية --}}
        <div class="dashboard-notif-card p-4 mb-4">
            <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between" style="gap:12px;">
                <x-dashboard.visual.status-tabs
                    baseRoute="dashboard.creatives.index"
                    :status="$status"
                    :counts="$counts"
                />
                <span class="notif-muted" style="font-size:11px;font-weight:700;">
                    {{ $creatives->total() }} تصميم — صفحة {{ $creatives->currentPage() }} من {{ $creatives->lastPage() }}
                </span>
            </div>
        </div>

        {{-- الشبكة / الحالة الفارغة --}}
        @if ($creatives->isEmpty())
            <x-dashboard.visual.empty-state
                icon="fas fa-palette"
                title="لا توجد تصاميم في هذا القسم بعد"
                subtitle="عند رفع الأعضاء لتصاميم جديدة ستظهر هنا للمراجعة والاعتماد، أو ارفع تصميمًا جديدًا بنفسك."
            >
                @can('notifications.send')
                    <x-slot name="action">
                        <button
                            type="button"
                            class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl transition-all shadow-md border-0"
                            data-toggle="modal"
                            data-target="#visualUploadModal"
                        >
                            <i class="fas fa-cloud-upload-alt"></i>
                            رفع أول تصميم
                        </button>
                    </x-slot>
                @endcan
            </x-dashboard.visual.empty-state>
        @else
            <div class="row">
                @foreach ($creatives as $ct)
                    <div class="col-sm-6 col-md-4 col-lg-3 mb-4">
                        <x-dashboard.visual.library-card
                            :id="$ct->id"
                            :imageUrl="$ct->image_url"
                            :name="$ct->name"
                            :description="$ct->description"
                            :uploaderName="$ct->uploader->full_name ?? null"
                            :createdAt="$ct->created_at?->format('Y-m-d')"
                            :status="$ct->status"
                            :statusLabel="$ct->status_label"
                            :rejectionReason="$ct->rejection_reason"
                            :approveUrl="route('dashboard.creatives.approve', $ct)"
                            :rejectUrl="route('dashboard.creatives.reject', $ct)"
                            :deleteUrl="route('dashboard.creatives.destroy', $ct)"
                            :canManage="auth()->user()?->can('notifications.send') ?? false"
                        />
                        @can('notifications.send')
                            <form method="POST" action="{{ route('dashboard.creatives.update', $ct) }}" class="mt-2" data-category-form>
                                @csrf
                                @method('PATCH')
                                <label for="creativeType{{ $ct->id }}">نوع الدعوة</label>
                                <select name="invitation_type" id="creativeType{{ $ct->id }}" class="dashboard-input">
                                    <option value="">تصميم عام</option>
                                    @foreach (config('invitation_templates.templates', []) as $type)
                                        <option value="{{ $type['key'] }}" @selected($ct->invitation_type === $type['key'])>{{ $type['name'] }}</option>
                                    @endforeach
                                </select>
                                <button type="submit" class="btn btn-sm btn-success mt-2">حفظ التصنيف</button>
                                <span role="status"></span>
                            </form>
                        @endcan
                    </div>
                @endforeach
            </div>

            @if ($creatives->hasPages())
                <div class="d-flex justify-content-center mt-2">
                    {{ $creatives->links() }}
                </div>
            @endif
        @endif

        {{-- المودالات المشتركة --}}
        <x-dashboard.visual.preview-modal id="visualPreviewModal" title="معاينة التصميم" />
        <x-dashboard.visual.reject-modal
            id="visualRejectModal"
            title="رفض التصميم"
            inputId="visualRejectReason"
        />
        <x-dashboard.visual.confirm-modal
            id="visualConfirmModal"
            title="تأكيد الإجراء"
            message="هل أنت متأكد؟"
            confirmLabel="تأكيد"
        />

        @can('notifications.send')
            <x-dashboard.visual.upload-modal
                id="visualUploadModal"
                title="رفع تصميم جديد"
                formId="visualUploadForm"
                fileId="visualUploadFile"
                nameId="visualUploadName"
                extraId="visualUploadDesc"
                :showDescription="true"
                :invitationTypes="config('invitation_templates.templates', [])"
                submitLabel="رفع واعتماد"
                :storeUrl="route('dashboard.creatives.store')"
            />
        @endcan

        <x-dashboard.visual.toasts />
    </div>
</div>
@endsection

@push('scripts')
    <script>
        document.querySelectorAll('[data-category-form]').forEach(function (form) {
            form.addEventListener('submit', async function (event) {
                event.preventDefault();
                const button = form.querySelector('button');
                const status = form.querySelector('[role="status"]');
                button.disabled = true;
                try {
                    const response = await fetch(form.action, {
                        method: 'POST',
                        headers: { Accept: 'application/json' },
                        body: new FormData(form),
                    });
                    const result = await response.json();
                    status.textContent = response.ok ? 'تم الحفظ' : (result.message || 'تعذر الحفظ');
                } catch (error) {
                    status.textContent = 'تعذر الاتصال، حاول مجددًا';
                } finally {
                    button.disabled = false;
                }
            });
        });
    </script>
    <script src="{{ asset('js/dashboard/visual-library.js') }}?v={{ file_exists(public_path('js/dashboard/visual-library.js')) ? filemtime(public_path('js/dashboard/visual-library.js')) : time() }}"></script>
@endpush
