@extends('layouts.app')

@section('title', 'إدارة المنتجات')

@push('styles')
<style>
    /* =========================================================
       Products Dashboard Modern Styling
       ========================================================= */
    .product-kpi-card {
        border: none;
        border-radius: 16px;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        overflow: hidden;
        cursor: pointer;
        position: relative;
    }
    .product-kpi-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 12px 28px rgba(0, 0, 0, 0.12) !important;
    }
    .kpi-icon-wrapper {
        width: 52px;
        height: 52px;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.4rem;
        flex-shrink: 0;
    }
    .filter-card {
        border: none;
        border-radius: 16px;
    }
    .product-table th {
        font-size: 0.85rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        font-weight: 700;
        vertical-align: middle;
        border-top: none;
    }
    .product-table td {
        vertical-align: middle;
    }
    .product-thumbnail-box {
        width: 64px;
        height: 64px;
        border-radius: 12px;
        overflow: hidden;
        position: relative;
        flex-shrink: 0;
        background: rgba(0, 0, 0, 0.04);
        border: 1px solid rgba(0, 0, 0, 0.08);
    }
    .dark .product-thumbnail-box {
        background: rgba(255, 255, 255, 0.04);
        border-color: rgba(255, 255, 255, 0.08);
    }
    .product-thumbnail-box img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.3s ease;
    }
    .product-thumbnail-box:hover img {
        transform: scale(1.1);
    }
    .owner-info-cell {
        display: flex;
        align-items: center;
        gap: 10px;
        min-width: 220px;
    }
    .owner-avatar-sm {
        width: 38px;
        height: 38px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid #3b82f6;
        flex-shrink: 0;
    }
    .owner-avatar-sm.female {
        border-color: #ec4899;
    }
    .badge-soft-primary {
        background-color: rgba(59, 130, 246, 0.15);
        color: #2563eb;
    }
    .badge-soft-success {
        background-color: rgba(16, 185, 129, 0.15);
        color: #059669;
    }
    .badge-soft-warning {
        background-color: rgba(245, 158, 11, 0.15);
        color: #d97706;
    }
    .badge-soft-info {
        background-color: rgba(6, 182, 212, 0.15);
        color: #0891b2;
    }
    .badge-soft-secondary {
        background-color: rgba(107, 114, 128, 0.15);
        color: #4b5563;
    }
    .dark .badge-soft-primary { color: #60a5fa; }
    .dark .badge-soft-success { color: #34d399; }
    .dark .badge-soft-warning { color: #fbbf24; }
    .dark .badge-soft-info { color: #38bdf8; }
    .dark .badge-soft-secondary { color: #9ca3af; }

    /* Status Switch in Table */
    .table .custom-switch {
        padding-right: 2.25rem;
        padding-left: 0;
    }
</style>
@endpush

@section('content')
<div class="container-fluid">
    <!-- Header Section -->
    <div class="row align-items-center justify-content-between mb-4">
        <div class="col-12 col-md-6 mb-3 mb-md-0">
            <h2 class="h3 font-weight-bold text-gray-800 mb-1 d-flex align-items-center">
                <i class="fas fa-boxes text-primary ml-2"></i>
                إدارة منتجات الأسر المنتجة
            </h2>
            <p class="text-muted mb-0 font-weight-500">
                إدارة شاملة لمنتجات ومشاريع العائلة، المتاجر العادية، وعروض التأجير والاستعارة
            </p>
        </div>
        <div class="col-12 col-md-6 text-md-left">
            <div class="d-flex flex-wrap align-items-center justify-content-md-end" style="gap: 8px;">
                <a href="{{ route('products.categories.index') }}" class="btn btn-outline-secondary shadow-sm">
                    <i class="fas fa-folder mr-1"></i> التصنيفات
                </a>
                <a href="{{ route('products.subcategories.index') }}" class="btn btn-outline-secondary shadow-sm">
                    <i class="fas fa-tags mr-1"></i> الفئات الفرعية
                </a>
                <a href="{{ route('products.create') }}" class="btn btn-primary shadow-sm px-3">
                    <i class="fas fa-plus-circle mr-1"></i> إضافة منتج جديد
                </a>
            </div>
        </div>
    </div>

    <!-- Alert Notifications -->
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm border-0 mb-4" role="alert">
            <div class="d-flex align-items-center">
                <i class="fas fa-check-circle fa-lg ml-2"></i>
                <div>{{ session('success') }}</div>
            </div>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif

    <!-- Statistics KPI Cards -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card product-kpi-card shadow-sm h-100 {{ $status === 'all' && $type === 'all' ? 'border-primary' : '' }}"
                 onclick="window.location.href='{{ route('products.index', ['type' => 'all', 'status' => 'all']) }}'">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <span class="text-muted text-xs font-weight-bold text-uppercase d-block mb-1">إجمالي المنتجات</span>
                            <span class="h3 font-weight-bold text-gray-800 mb-0">{{ number_format($stats['total'] ?? 0) }}</span>
                        </div>
                        <div class="kpi-icon-wrapper badge-soft-primary">
                            <i class="fas fa-cube"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card product-kpi-card shadow-sm h-100 {{ $status === 'active' ? 'border-success' : '' }}"
                 onclick="window.location.href='{{ route('products.index', array_merge(request()->query(), ['status' => 'active'])) }}'">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <span class="text-muted text-xs font-weight-bold text-uppercase d-block mb-1">منتجات نشطة</span>
                            <span class="h3 font-weight-bold text-success mb-0">{{ number_format($stats['active'] ?? 0) }}</span>
                        </div>
                        <div class="kpi-icon-wrapper badge-soft-success">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card product-kpi-card shadow-sm h-100 {{ $status === 'inactive' ? 'border-warning' : '' }}"
                 onclick="window.location.href='{{ route('products.index', array_merge(request()->query(), ['status' => 'inactive'])) }}'">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <span class="text-muted text-xs font-weight-bold text-uppercase d-block mb-1">منتجات معطلة</span>
                            <span class="h3 font-weight-bold text-warning mb-0">{{ number_format($stats['inactive'] ?? 0) }}</span>
                        </div>
                        <div class="kpi-icon-wrapper badge-soft-warning">
                            <i class="fas fa-pause-circle"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card product-kpi-card shadow-sm h-100 {{ $type === 'rental' ? 'border-info' : '' }}"
                 onclick="window.location.href='{{ route('products.index', array_merge(request()->query(), ['type' => 'rental'])) }}'">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <span class="text-muted text-xs font-weight-bold text-uppercase d-block mb-1">منتجات مؤجرة (استعارة)</span>
                            <span class="h3 font-weight-bold text-info mb-0">{{ number_format($stats['rental'] ?? 0) }}</span>
                        </div>
                        <div class="kpi-icon-wrapper badge-soft-info">
                            <i class="fas fa-hand-holding"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter & Search Controls -->
    <div class="card filter-card shadow-sm mb-4">
        <div class="card-body p-4">
            <!-- Type Tabs -->
            <div class="d-flex flex-wrap align-items-center justify-content-between pb-3 mb-3 border-bottom" style="gap: 12px;">
                <div class="btn-group btn-group-toggle" data-toggle="buttons">
                    <a href="{{ route('products.index', array_merge(request()->query(), ['type' => 'all'])) }}"
                       class="btn btn-outline-primary btn-sm px-3 {{ $type === 'all' ? 'active' : '' }}">
                        <i class="fas fa-border-all ml-1"></i> الكل ({{ $stats['total'] }})
                    </a>
                    <a href="{{ route('products.index', array_merge(request()->query(), ['type' => 'normal'])) }}"
                       class="btn btn-outline-primary btn-sm px-3 {{ $type === 'normal' ? 'active' : '' }}">
                        <i class="fas fa-shopping-bag ml-1"></i> عادي للبيع ({{ $stats['normal'] }})
                    </a>
                    <a href="{{ route('products.index', array_merge(request()->query(), ['type' => 'rental'])) }}"
                       class="btn btn-outline-primary btn-sm px-3 {{ $type === 'rental' ? 'active' : '' }}">
                        <i class="fas fa-hand-holding ml-1"></i> متاح للتأجير ({{ $stats['rental'] }})
                    </a>
                </div>

                @if($categoryId || $subcategoryId || $search || $status !== 'all' || $type !== 'all')
                    <a href="{{ route('products.index') }}" class="btn btn-link btn-sm text-danger p-0 font-weight-bold">
                        <i class="fas fa-times-circle ml-1"></i> إلغاء جميع الفلاتر
                    </a>
                @endif
            </div>

            <!-- Filter Inputs Form -->
            <form method="GET" action="{{ route('products.index') }}" id="filterForm">
                <input type="hidden" name="type" value="{{ $type }}">
                <div class="form-row align-items-end">
                    <div class="col-12 col-md-3 mb-2 mb-md-0">
                        <label class="font-weight-bold text-xs text-muted mb-1">الفئة الرئيسية</label>
                        <select name="category_id" id="categoryFilter" class="form-control form-control-sm" onchange="onCategoryFilterChange()">
                            <option value="">جميع الفئات</option>
                            @foreach($categories as $cat)
                                <option value="{{ $cat->id }}" {{ $categoryId == $cat->id ? 'selected' : '' }}>
                                    {{ $cat->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-12 col-md-3 mb-2 mb-md-0">
                        <label class="font-weight-bold text-xs text-muted mb-1">الفئة الفرعية</label>
                        <select name="subcategory_id" id="subcategoryFilter" class="form-control form-control-sm" onchange="document.getElementById('filterForm').submit()">
                            <option value="">جميع الفئات الفرعية</option>
                            @foreach($subcategories as $sub)
                                <option value="{{ $sub->id }}" {{ $subcategoryId == $sub->id ? 'selected' : '' }}>
                                    {{ $sub->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-12 col-md-2 mb-2 mb-md-0">
                        <label class="font-weight-bold text-xs text-muted mb-1">الحالة</label>
                        <select name="status" class="form-control form-control-sm" onchange="document.getElementById('filterForm').submit()">
                            <option value="all" {{ $status === 'all' ? 'selected' : '' }}>الكل</option>
                            <option value="active" {{ $status === 'active' ? 'selected' : '' }}>نشط فقط</option>
                            <option value="inactive" {{ $status === 'inactive' ? 'selected' : '' }}>معطل فقط</option>
                        </select>
                    </div>

                    <div class="col-12 col-md-3 mb-2 mb-md-0">
                        <label class="font-weight-bold text-xs text-muted mb-1">بحث متقدم</label>
                        <div class="input-group input-group-sm">
                            <input type="text" name="search" class="form-control"
                                   placeholder="ابحث باسم المنتج، الوصف، أو اسم المالك..."
                                   value="{{ $search }}">
                            <div class="input-group-append">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-md-1">
                        <button type="submit" class="btn btn-secondary btn-sm btn-block">
                            تصفية
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Products Table Card -->
    <div class="card shadow-sm border-0 rounded-lg">
        <div class="card-body p-0">
            @if($products->count() > 0)
                <div class="table-responsive">
                    <table class="table table-hover product-table mb-0 align-middle">
                        <thead class="bg-light">
                            <tr>
                                <th class="text-center" width="80">الصورة</th>
                                <th>تفاصيل المنتج</th>
                                <th>صاحب المنتج (المالك)</th>
                                <th>التصنيف</th>
                                <th>السعر والنوع</th>
                                <th class="text-center" width="110">الحالة</th>
                                <th class="text-center" width="160">الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($products as $product)
                                @php
                                    $owner = $product->owner;
                                    $isFemale = $owner && $owner->gender === 'female';
                                    $defaultAvatar = $isFemale
                                        ? asset('assets/img/avatar-female-hejap-1.png')
                                        : asset('assets/img/avatar-male.png');
                                    $ownerAvatar = $owner ? ($owner->avatar ?: $defaultAvatar) : '';
                                    $ownerFullName = $owner ? $owner->full_name : 'غير محدد';
                                    $categoryName = $product->category ? $product->category->name : 'بدون فئة';
                                    $subcategoryName = $product->subcategory ? $product->subcategory->name : '';
                                    $mainImgUrl = $product->main_image ? asset('storage/' . $product->main_image) : '';
                                @endphp
                                <tr id="product-row-{{ $product->id }}">
                                    <!-- Image Thumbnail -->
                                    <td class="text-center">
                                        <div class="product-thumbnail-box mx-auto">
                                            @if($product->main_image)
                                                <img src="{{ $mainImgUrl }}"
                                                     alt="{{ $product->name }}"
                                                     loading="lazy">
                                            @else
                                                <div class="w-100 h-100 d-flex align-items-center justify-content-center text-muted">
                                                    <i class="fas fa-image fa-lg opacity-50"></i>
                                                </div>
                                            @endif
                                        </div>
                                    </td>

                                    <!-- Product Info -->
                                    <td>
                                        <div class="font-weight-bold text-dark text-base mb-1">
                                            {{ $product->name }}
                                        </div>
                                        @if($product->description)
                                            <div class="text-muted text-xs mb-1" style="max-width: 320px; white-space: normal; line-height: 1.4;">
                                                {{ Str::limit($product->description, 75) }}
                                            </div>
                                        @endif
                                        <div class="d-flex align-items-center text-xs text-muted" style="gap: 12px;">
                                            @if($product->location)
                                                <span><i class="fas fa-map-marker-alt text-danger ml-1"></i>{{ $product->location->name }}</span>
                                            @endif
                                            @if($product->media->count() > 0)
                                                <span><i class="fas fa-photo-video text-info ml-1"></i>{{ $product->media->count() }} وسائط</span>
                                            @endif
                                        </div>
                                    </td>

                                    <!-- Owner Info with Full Lineage Name & Avatar -->
                                    <td>
                                        @if($owner)
                                            <div class="owner-info-cell">
                                                <img src="{{ $ownerAvatar }}"
                                                     alt="{{ $ownerFullName }}"
                                                     class="owner-avatar-sm {{ $isFemale ? 'female' : '' }}">
                                                <div class="min-w-0">
                                                    <div class="font-weight-600 text-sm text-dark line-clamp-1" title="{{ $ownerFullName }}">
                                                        {{ $ownerFullName }}
                                                    </div>
                                                    <span class="badge {{ $isFemale ? 'badge-soft-danger' : 'badge-soft-primary' }} text-xs py-0 px-2 mt-1">
                                                        <i class="fas {{ $isFemale ? 'fa-venus' : 'fa-mars' }} ml-1"></i>
                                                        {{ $isFemale ? 'أنثى' : 'ذكر' }}
                                                    </span>
                                                </div>
                                            </div>
                                        @else
                                            <span class="text-muted text-xs font-italic">
                                                <i class="fas fa-user-slash ml-1"></i>غير محدد
                                            </span>
                                        @endif
                                    </td>

                                    <!-- Category & Subcategory -->
                                    <td>
                                        @if($product->category)
                                            <span class="badge badge-soft-primary font-weight-normal py-1 px-2 d-inline-block mb-1">
                                                {{ $categoryName }}
                                            </span>
                                            @if($product->subcategory)
                                                <br>
                                                <span class="badge badge-soft-secondary font-weight-normal py-1 px-2 d-inline-block">
                                                    {{ $subcategoryName }}
                                                </span>
                                            @endif
                                        @else
                                            <span class="badge badge-soft-warning">بدون فئة</span>
                                        @endif
                                    </td>

                                    <!-- Price & Type -->
                                    <td>
                                        <div class="font-weight-bold text-primary text-base mb-1">
                                            {{ number_format($product->price, 2) }} ر.س
                                        </div>
                                        @if($product->is_rental)
                                            <span class="badge badge-soft-info py-1 px-2">
                                                <i class="fas fa-hand-holding ml-1"></i> للتأجير
                                            </span>
                                        @else
                                            <span class="badge badge-soft-success py-1 px-2">
                                                <i class="fas fa-shopping-cart ml-1"></i> عادي للبيع
                                            </span>
                                        @endif
                                    </td>

                                    <!-- Status Toggle (AJAX) -->
                                    <td class="text-center align-middle">
                                        <div class="d-flex flex-column align-items-center justify-content-center" style="gap: 5px;">
                                            <div class="custom-control custom-switch m-0" style="min-height: 22px;">
                                                <input type="checkbox"
                                                       class="custom-control-input js-toggle-status"
                                                       id="switchStatus{{ $product->id }}"
                                                       data-id="{{ $product->id }}"
                                                       data-url="{{ route('products.toggle', $product) }}"
                                                       {{ $product->is_active ? 'checked' : '' }}>
                                                <label class="custom-control-label cursor-pointer" for="switchStatus{{ $product->id }}"></label>
                                            </div>
                                            <span class="badge {{ $product->is_active ? 'badge-soft-success' : 'badge-soft-secondary' }} status-badge font-weight-bold" id="status-badge-{{ $product->id }}" style="font-size: 0.725rem; min-width: 46px;">
                                                {{ $product->is_active ? 'نشط' : 'معطل' }}
                                            </span>
                                        </div>
                                    </td>

                                    <!-- Action Buttons -->
                                    <td class="text-center">
                                        <div class="btn-group" role="group" style="gap: 4px;">
                                            <button type="button"
                                                    class="btn btn-sm btn-outline-info rounded js-preview-btn"
                                                    data-id="{{ $product->id }}"
                                                    data-name="{{ $product->name }}"
                                                    data-price="{{ number_format($product->price, 2) }}"
                                                    data-description="{{ $product->description }}"
                                                    data-category="{{ $categoryName }}"
                                                    data-subcategory="{{ $subcategoryName }}"
                                                    data-owner="{{ $ownerFullName }}"
                                                    data-owner-avatar="{{ $ownerAvatar }}"
                                                    data-image="{{ $mainImgUrl }}"
                                                    data-is-rental="{{ $product->is_rental ? '1' : '0' }}"
                                                    data-phone="{{ $product->contact_phone }}"
                                                    data-whatsapp="{{ $product->contact_whatsapp }}"
                                                    data-email="{{ $product->contact_email }}"
                                                    data-location-url="{{ $product->location_url }}"
                                                    data-features='@json($product->features ?: [])'
                                                    title="معاينة سريعة">
                                                <i class="fas fa-eye"></i>
                                            </button>

                                            <a href="{{ route('products.edit', $product) }}"
                                               class="btn btn-sm btn-outline-primary rounded"
                                               title="تعديل">
                                                <i class="fas fa-edit"></i>
                                            </a>

                                            <button type="button"
                                                    class="btn btn-sm btn-outline-danger rounded js-delete-btn"
                                                    data-id="{{ $product->id }}"
                                                    data-url="{{ route('products.destroy', $product) }}"
                                                    data-name="{{ $product->name }}"
                                                    title="حذف المنتج">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Pagination Section -->
                <div class="d-flex align-items-center justify-content-between p-3 border-top flex-wrap">
                    <div class="text-muted text-sm mb-2 mb-md-0">
                        عرض السجلات من {{ $products->firstItem() ?? 0 }} إلى {{ $products->lastItem() ?? 0 }} من إجمالي {{ $products->total() }} منتج
                    </div>
                    <div>
                        {{ $products->appends(request()->query())->links() }}
                    </div>
                </div>
            @else
                <!-- Modern Empty State -->
                <div class="text-center py-5 px-3">
                    <div class="mb-4">
                        <div class="d-inline-flex align-items-center justify-content-center bg-light rounded-circle shadow-sm" style="width: 100px; height: 100px;">
                            <i class="fas fa-shopping-bag text-muted fa-3x"></i>
                        </div>
                    </div>
                    <h4 class="font-weight-bold text-gray-800 mb-2">لا توجد منتجات مطابقة حالياً</h4>
                    <p class="text-muted mb-4" style="max-width: 480px; margin: 0 auto;">
                        لم يتم العثور على أي منتج يطابق معايير البحث الحالية، أو لم يتم إضافة منتجات بعد.
                    </p>
                    <div class="d-flex justify-content-center" style="gap: 10px;">
                        @if($categoryId || $subcategoryId || $search || $status !== 'all' || $type !== 'all')
                            <a href="{{ route('products.index') }}" class="btn btn-outline-secondary">
                                <i class="fas fa-redo ml-1"></i> إعادة ضبط الفلاتر
                            </a>
                        @endif
                        <a href="{{ route('products.create') }}" class="btn btn-primary px-4 shadow-sm">
                            <i class="fas fa-plus-circle ml-1"></i> إضافة منتج جديد
                        </a>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>

<!-- Quick View Modal -->
<div class="modal fade" id="quickViewModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header border-bottom py-3">
                <h5 class="modal-title font-weight-bold" id="modalProductName">تفاصيل المنتج</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-4">
                <div class="row">
                    <div class="col-md-5 mb-3 mb-md-0 text-center">
                        <div class="rounded overflow-hidden bg-light shadow-sm" style="height: 240px;">
                            <img id="modalProductImage" src="" alt="" class="w-100 h-100" style="object-fit: cover;">
                        </div>
                        <div class="mt-3">
                            <span class="h4 font-weight-bold text-primary" id="modalProductPrice"></span>
                            <span class="text-muted"> ر.س</span>
                            <div class="mt-1" id="modalProductTypeBadge"></div>
                        </div>
                    </div>
                    <div class="col-md-7">
                        <div class="mb-3">
                            <label class="text-xs font-weight-bold text-muted text-uppercase d-block mb-1">صاحب المنتج</label>
                            <div class="d-flex align-items-center" id="modalOwnerBox">
                                <img id="modalOwnerAvatar" src="" class="owner-avatar-sm ml-2">
                                <span class="font-weight-600 text-dark" id="modalOwnerName"></span>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="text-xs font-weight-bold text-muted text-uppercase d-block mb-1">التصنيف</label>
                            <span class="badge badge-soft-primary" id="modalCategory"></span>
                            <span class="badge badge-soft-secondary" id="modalSubcategory"></span>
                        </div>

                        <div class="mb-3">
                            <label class="text-xs font-weight-bold text-muted text-uppercase d-block mb-1">الوصف</label>
                            <p class="text-sm text-muted mb-0" id="modalDescription" style="white-space: pre-line;"></p>
                        </div>

                        <div class="mb-3" id="modalFeaturesContainer">
                            <label class="text-xs font-weight-bold text-muted text-uppercase d-block mb-1">المميزات</label>
                            <ul class="text-sm text-dark pr-3 mb-0" id="modalFeaturesList"></ul>
                        </div>

                        <div class="border-top pt-3 mt-3">
                            <label class="text-xs font-weight-bold text-muted text-uppercase d-block mb-2">معلومات التواصل</label>
                            <div class="d-flex flex-wrap" style="gap: 8px;" id="modalContacts">
                                <a href="#" id="modalPhoneLink" class="btn btn-sm btn-outline-secondary d-none" target="_blank">
                                    <i class="fas fa-phone-alt ml-1"></i> <span id="modalPhoneText"></span>
                                </a>
                                <a href="#" id="modalWhatsappLink" class="btn btn-sm btn-outline-success d-none" target="_blank">
                                    <i class="fab fa-whatsapp ml-1"></i> واتساب
                                </a>
                                <a href="#" id="modalLocationLink" class="btn btn-sm btn-outline-danger d-none" target="_blank">
                                    <i class="fas fa-map-marker-alt ml-1"></i> خريطة الموقع
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-top py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">إغلاق</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    // Handle Category Filter Change (fetch subcategories dynamically)
    function onCategoryFilterChange() {
        const categoryId = document.getElementById('categoryFilter').value;
        const subSelect = document.getElementById('subcategoryFilter');
        subSelect.innerHTML = '<option value="">جاري التحميل...</option>';

        if (!categoryId) {
            subSelect.innerHTML = '<option value="">جميع الفئات الفرعية</option>';
            document.getElementById('filterForm').submit();
            return;
        }

        const url = "{{ route('products.subcategories.by-category', ':id') }}".replace(':id', categoryId);
        fetch(url)
            .then(res => res.json())
            .then(data => {
                subSelect.innerHTML = '<option value="">جميع الفئات الفرعية</option>';
                if (data.success && data.subcategories) {
                    data.subcategories.forEach(sub => {
                        const opt = document.createElement('option');
                        opt.value = sub.id;
                        opt.textContent = sub.name;
                        subSelect.appendChild(opt);
                    });
                }
                document.getElementById('filterForm').submit();
            })
            .catch(() => {
                subSelect.innerHTML = '<option value="">جميع الفئات الفرعية</option>';
                document.getElementById('filterForm').submit();
            });
    }

    $(document).ready(function() {
        // AJAX Toggle Active/Inactive Status
        $(document).on('change', '.js-toggle-status', function() {
            const checkbox = $(this);
            const productId = checkbox.data('id');
            const url = checkbox.data('url');
            const statusBadge = $(`#status-badge-${productId}`);
            const originalChecked = !checkbox.is(':checked');

            $.ajax({
                url: url,
                type: 'POST',
                data: {
                    _token: $('meta[name="csrf-token"]').attr('content')
                },
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                    'Accept': 'application/json'
                },
                success: function(response) {
                    if (response.success) {
                        if (response.is_active) {
                            statusBadge.removeClass('badge-soft-secondary').addClass('badge-soft-success').text('نشط');
                        } else {
                            statusBadge.removeClass('badge-soft-success').addClass('badge-soft-secondary').text('معطل');
                        }
                    } else {
                        checkbox.prop('checked', originalChecked);
                    }
                },
                error: function() {
                    checkbox.prop('checked', originalChecked);
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'error',
                            title: 'خطأ',
                            text: 'حدث خطأ أثناء تحديث حالة المنتج'
                        });
                    } else {
                        alert('حدث خطأ أثناء تحديث حالة المنتج');
                    }
                }
            });
        });

        // Delete Product with SweetAlert Confirmation & Native Fallback
        $(document).on('click', '.js-delete-btn', function(e) {
            e.preventDefault();
            const btn = $(this);
            const productId = btn.data('id');
            const productName = btn.data('name');
            const url = btn.data('url');

            const executeDelete = function() {
                btn.prop('disabled', true).addClass('disabled');
                $.ajax({
                    url: url,
                    type: 'POST',
                    data: {
                        _token: $('meta[name="csrf-token"]').attr('content'),
                        _method: 'DELETE'
                    },
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                        'Accept': 'application/json'
                    },
                    success: function(response) {
                        $(`#product-row-${productId}`).fadeOut(400, function() {
                            $(this).remove();
                        });
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                icon: 'success',
                                title: 'تم الحذف بنجاح',
                                text: response.message || 'تم حذف المنتج بنجاح',
                                timer: 1500,
                                showConfirmButton: false
                            });
                        }
                    },
                    error: function(xhr) {
                        btn.prop('disabled', false).removeClass('disabled');
                        const errMsg = (xhr.responseJSON && xhr.responseJSON.message) 
                            ? xhr.responseJSON.message 
                            : 'حدث خطأ أثناء محاولة حذف المنتج';
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                icon: 'error',
                                title: 'فشل الحذف',
                                text: errMsg
                            });
                        } else {
                            alert(errMsg);
                        }
                    }
                });
            };

            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'هل أنت متأكد من الحذف؟',
                    html: `سيتم حذف المنتج <b>"${productName}"</b> وكافة الصور والوسائط والمواعيد التابعة له نهائياً.`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: '<i class="fas fa-trash-alt ml-1"></i> نعم، احذف',
                    cancelButtonText: 'إلغاء'
                }).then((result) => {
                    if (result.isConfirmed) {
                        executeDelete();
                    }
                });
            } else {
                if (confirm(`هل أنت متأكد من حذف المنتج "${productName}" وكافة بياناته نهائياً؟`)) {
                    executeDelete();
                }
            }
        });

        // Quick View Modal
        $('.js-preview-btn').on('click', function() {
            const btn = $(this);
            const name = btn.data('name');
            const price = btn.data('price');
            const description = btn.data('description');
            const category = btn.data('category');
            const subcategory = btn.data('subcategory');
            const owner = btn.data('owner');
            const ownerAvatar = btn.data('owner-avatar');
            const image = btn.data('image');
            const isRental = btn.data('is-rental') == 1;
            const phone = btn.data('phone');
            const whatsapp = btn.data('whatsapp');
            const locationUrl = btn.data('location-url');
            let features = [];
            try {
                features = btn.data('features') || [];
                if (typeof features === 'string') {
                    features = JSON.parse(features);
                }
            } catch(e) {
                features = [];
            }

            $('#modalProductName').text(name);
            $('#modalProductPrice').text(price);
            $('#modalDescription').text(description || 'لا يوجد وصف متوفر للمنتج.');
            $('#modalCategory').text(category);

            if (subcategory) {
                $('#modalSubcategory').text(subcategory).removeClass('d-none');
            } else {
                $('#modalSubcategory').addClass('d-none');
            }

            if (image) {
                $('#modalProductImage').attr('src', image).removeClass('d-none');
            } else {
                $('#modalProductImage').addClass('d-none');
            }

            if (isRental) {
                $('#modalProductTypeBadge').html('<span class="badge badge-soft-info"><i class="fas fa-hand-holding ml-1"></i> متاح للتأجير والاستعارة</span>');
            } else {
                $('#modalProductTypeBadge').html('<span class="badge badge-soft-success"><i class="fas fa-shopping-cart ml-1"></i> عادي للبيع</span>');
            }

            $('#modalOwnerName').text(owner);
            if (ownerAvatar) {
                $('#modalOwnerAvatar').attr('src', ownerAvatar).removeClass('d-none');
            } else {
                $('#modalOwnerAvatar').addClass('d-none');
            }

            // Features
            const featuresList = $('#modalFeaturesList');
            featuresList.empty();
            if (Array.isArray(features) && features.length > 0) {
                features.forEach(f => {
                    featuresList.append(`<li>${f}</li>`);
                });
                $('#modalFeaturesContainer').removeClass('d-none');
            } else {
                $('#modalFeaturesContainer').addClass('d-none');
            }

            // Contacts
            if (phone) {
                $('#modalPhoneLink').attr('href', 'tel:' + phone).removeClass('d-none');
                $('#modalPhoneText').text(phone);
            } else {
                $('#modalPhoneLink').addClass('d-none');
            }

            if (whatsapp) {
                const waNumber = String(whatsapp).replace(/[^0-9]/g, '');
                $('#modalWhatsappLink').attr('href', 'https://wa.me/' + waNumber).removeClass('d-none');
            } else {
                $('#modalWhatsappLink').addClass('d-none');
            }

            if (locationUrl) {
                $('#modalLocationLink').attr('href', locationUrl).removeClass('d-none');
            } else {
                $('#modalLocationLink').addClass('d-none');
            }

            $('#quickViewModal').modal('show');
        });
    });
</script>
@endpush
