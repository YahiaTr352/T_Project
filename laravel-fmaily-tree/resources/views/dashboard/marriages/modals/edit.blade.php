<div class="modal fade" id="editMarriageModal{{ $marriage->id }}" tabindex="-1" role="dialog"
    aria-labelledby="editMarriageModalLabel{{ $marriage->id }}" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-gradient-primary text-white py-3">
                <h5 class="modal-title d-flex align-items-center" id="editMarriageModalLabel{{ $marriage->id }}">
                    <i class="fas fa-edit mr-2 text-warning"></i>
                    <span>تعديل سجل الزواج #{{ $marriage->id }}</span>
                </h5>
                <button type="button" class="close text-white opacity-75 hover-opacity-100" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form class="edit-marriage-form" action="{{ route('marriages.update', $marriage->id) }}" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-body p-4">
                    {{-- بطاقات اختيار الزوجين --}}
                    <div class="row align-items-center mb-3">
                        {{-- الزوج --}}
                        <div class="col-md-5">
                            <div class="form-group mb-0">
                                <label for="husband_id_edit{{ $marriage->id }}" class="font-weight-bold">
                                    <i class="fas fa-mars text-primary mr-1"></i> الزوج <span class="text-danger">*</span>
                                </label>
                                <select name="husband_id" id="husband_id_edit{{ $marriage->id }}"
                                    class="form-control js-marriage-edit-husband" required data-placeholder="ابحث عن الزوج بالاسم الكامل..."
                                    data-modal="#editMarriageModal{{ $marriage->id }}"
                                    data-preview="#edit_husband_preview{{ $marriage->id }}">
                                    @if($marriage->husband)
                                        <option value="{{ $marriage->husband_id }}" selected>
                                            {{ $marriage->husband->full_name }}
                                        </option>
                                    @endif
                                </select>
                                {{-- بطاقة معاينة الزوج --}}
                                <div id="edit_husband_preview{{ $marriage->id }}" class="person-preview-card male" style="display: block;">
                                    @if($marriage->husband)
                                        <div class="preview-body">
                                            <img src="{{ $marriage->husband->avatar }}" alt="{{ $marriage->husband->full_name }}" class="preview-avatar">
                                            <div class="preview-details">
                                                <div class="preview-name">
                                                    <span>{{ $marriage->husband->full_name }}</span>
                                                    <span class="badge badge-primary font-weight-normal py-1 px-2">
                                                        <i class="fas fa-mars"></i> ذكر
                                                    </span>
                                                </div>
                                                <div class="preview-meta">
                                                    @if($marriage->husband->age)
                                                        <div class="preview-meta-item"><i class="fas fa-birthday-cake text-info"></i> <span>{{ $marriage->husband->age }} سنة</span></div>
                                                    @endif
                                                    @if($marriage->husband->birth_date)
                                                        <div class="preview-meta-item"><i class="fas fa-calendar-alt text-muted"></i> <span>الميلاد: {{ $marriage->husband->birth_date->format('Y-m-d') }}</span></div>
                                                    @endif
                                                    @if($marriage->husband->location)
                                                        <div class="preview-meta-item"><i class="fas fa-map-marker-alt text-danger"></i> <span>{{ $marriage->husband->location->name ?? $marriage->husband->location->display_name }}</span></div>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>

                        {{-- الرابط البصري بين الزوجين --}}
                        <div class="col-md-2 text-center my-3 my-md-0">
                            <div class="marriage-couple-connector">
                                <div class="marriage-connector-line"></div>
                                <div class="marriage-connector-badge active" title="عقد قران">
                                    <i class="fas fa-heart text-danger"></i>
                                </div>
                            </div>
                        </div>

                        {{-- الزوجة --}}
                        <div class="col-md-5">
                            <div class="form-group mb-0">
                                <label for="wife_id_edit{{ $marriage->id }}" class="font-weight-bold">
                                    <i class="fas fa-venus text-danger mr-1"></i> الزوجة <span class="text-danger">*</span>
                                </label>
                                <select name="wife_id" id="wife_id_edit{{ $marriage->id }}"
                                    class="form-control js-marriage-edit-wife" required data-placeholder="ابحث عن الزوجة بالاسم الكامل..."
                                    data-modal="#editMarriageModal{{ $marriage->id }}"
                                    data-preview="#edit_wife_preview{{ $marriage->id }}">
                                    @if($marriage->wife)
                                        <option value="{{ $marriage->wife_id }}" selected>
                                            {{ $marriage->wife->full_name }}
                                        </option>
                                    @endif
                                </select>
                                {{-- بطاقة معاينة الزوجة --}}
                                <div id="edit_wife_preview{{ $marriage->id }}" class="person-preview-card female" style="display: block;">
                                    @if($marriage->wife)
                                        <div class="preview-body">
                                            <img src="{{ $marriage->wife->avatar }}" alt="{{ $marriage->wife->full_name }}" class="preview-avatar">
                                            <div class="preview-details">
                                                <div class="preview-name">
                                                    <span>{{ $marriage->wife->full_name }}</span>
                                                    <span class="badge badge-danger font-weight-normal py-1 px-2">
                                                        <i class="fas fa-venus"></i> أنثى
                                                    </span>
                                                </div>
                                                <div class="preview-meta">
                                                    @if($marriage->wife->age)
                                                        <div class="preview-meta-item"><i class="fas fa-birthday-cake text-info"></i> <span>{{ $marriage->wife->age }} سنة</span></div>
                                                    @endif
                                                    @if($marriage->wife->birth_date)
                                                        <div class="preview-meta-item"><i class="fas fa-calendar-alt text-muted"></i> <span>الميلاد: {{ $marriage->wife->birth_date->format('Y-m-d') }}</span></div>
                                                    @endif
                                                    @if($marriage->wife->location)
                                                        <div class="preview-meta-item"><i class="fas fa-map-marker-alt text-danger"></i> <span>{{ $marriage->wife->location->name ?? $marriage->wife->location->display_name }}</span></div>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    <hr class="my-4" style="border-top: 1px dashed rgba(255,255,255,0.15);">

                    {{-- التواريخ --}}
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="married_at_edit{{ $marriage->id }}" class="font-weight-bold">
                                    <i class="fas fa-calendar-check text-success mr-1"></i> تاريخ الزواج
                                </label>
                                <input type="date" name="married_at" id="married_at_edit{{ $marriage->id }}"
                                    class="form-control"
                                    value="{{ $marriage->married_at ? $marriage->married_at->format('Y-m-d') : '' }}">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="divorced_at_edit{{ $marriage->id }}" class="font-weight-bold">
                                    <i class="fas fa-calendar-times text-warning mr-1"></i> تاريخ الانفصال
                                </label>
                                <input type="date" name="divorced_at" id="divorced_at_edit{{ $marriage->id }}"
                                    class="form-control"
                                    value="{{ $marriage->divorced_at ? $marriage->divorced_at->format('Y-m-d') : '' }}">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="sort_order_edit{{ $marriage->id }}" class="font-weight-bold">
                                    <i class="fas fa-sort-numeric-down text-info mr-1"></i> ترتيب الزوجة عند الزوج
                                    <small class="text-muted">(1 = الأولى)</small>
                                </label>
                                <input type="number" name="sort_order" id="sort_order_edit{{ $marriage->id }}"
                                    class="form-control" min="1" max="100"
                                    value="{{ old('sort_order', $marriage->sort_order) }}" placeholder="مثال: 1">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="custom-control custom-checkbox mt-4">
                                <input type="checkbox" name="is_divorced" id="is_divorced_edit{{ $marriage->id }}"
                                    class="custom-control-input" value="1" {{ $marriage->is_divorced ? 'checked' : '' }}>
                                <label class="custom-control-label" for="is_divorced_edit{{ $marriage->id }}">
                                    حالة انفصال / طلاق (بدون تحديد تاريخ دقيق)
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light px-4 py-3">
                    <button type="button" class="btn btn-secondary px-4" data-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-primary px-4 shadow-sm">
                        <i class="fas fa-save mr-1"></i> حفظ التغييرات
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

