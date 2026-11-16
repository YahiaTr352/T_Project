<div class="modal fade" id="createMarriageModal" tabindex="-1" role="dialog" aria-labelledby="createMarriageModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-gradient-primary text-white py-3">
                <h5 class="modal-title d-flex align-items-center" id="createMarriageModalLabel">
                    <i class="fas fa-ring mr-2 text-warning"></i>
                    <span>إضافة سجل زواج جديد</span>
                </h5>
                <button type="button" class="close text-white opacity-75 hover-opacity-100" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="createMarriageForm" action="{{ route('marriages.store') }}" method="POST">
                @csrf
                <div class="modal-body p-4">
                    {{-- بطاقات اختيار الزوجين --}}
                    <div class="row align-items-center mb-3">
                        {{-- اختيار الزوج --}}
                        <div class="col-md-5">
                            <div class="form-group mb-0">
                                <label for="create_marriage_husband_id" class="font-weight-bold d-flex align-items-center justify-content-between">
                                    <span>
                                        <i class="fas fa-mars text-primary mr-1"></i> الزوج <span class="text-danger">*</span>
                                    </span>
                                </label>
                                <select name="husband_id" id="create_marriage_husband_id" class="form-control" required data-placeholder="ابحث عن الزوج بالاسم الكامل...">
                                    <option value=""></option>
                                </select>
                                {{-- بطاقة معاينة الزوج المختار --}}
                                <div id="create_husband_preview" class="person-preview-card male"></div>
                            </div>
                        </div>

                        {{-- الرابط البصري التفاعلي بين الزوجين --}}
                        <div class="col-md-2 text-center my-3 my-md-0">
                            <div class="marriage-couple-connector">
                                <div class="marriage-connector-line"></div>
                                <div class="marriage-connector-badge" id="createMarriageConnectorBadge" title="عقد قران">
                                    <i class="fas fa-heart text-danger"></i>
                                </div>
                            </div>
                        </div>

                        {{-- اختيار الزوجة --}}
                        <div class="col-md-5">
                            <div class="form-group mb-0">
                                <label for="create_marriage_wife_id" class="font-weight-bold d-flex align-items-center justify-content-between">
                                    <span>
                                        <i class="fas fa-venus text-danger mr-1"></i> الزوجة <span class="text-danger">*</span>
                                    </span>
                                    <button type="button" class="btn btn-link btn-sm p-0 text-muted" data-toggle="modal" data-target="#addPersonsOutsideTheFamilyTreeModal" style="font-size: 0.78rem; text-decoration: none;">
                                        <i class="fas fa-user-plus text-success"></i> من خارج العائلة؟
                                    </button>
                                </label>
                                <select name="wife_id" id="create_marriage_wife_id" class="form-control" required data-placeholder="ابحث عن الزوجة بالاسم الكامل...">
                                    <option value=""></option>
                                </select>
                                {{-- بطاقة معاينة الزوجة المختارة --}}
                                <div id="create_wife_preview" class="person-preview-card female"></div>
                            </div>
                        </div>
                    </div>

                    <hr class="my-4" style="border-top: 1px dashed rgba(255,255,255,0.15);">

                    {{-- تواريخ الزواج والانفصال --}}
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="create_married_at" class="font-weight-bold">
                                    <i class="fas fa-calendar-check text-success mr-1"></i> تاريخ الزواج
                                </label>
                                <input type="date" name="married_at" id="create_married_at" class="form-control" value="{{ old('married_at') }}">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="create_divorced_at" class="font-weight-bold">
                                    <i class="fas fa-calendar-times text-warning mr-1"></i> تاريخ الانفصال (إن وجد)
                                </label>
                                <input type="date" name="divorced_at" id="create_divorced_at" class="form-control" value="{{ old('divorced_at') }}">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="create_sort_order" class="font-weight-bold">
                                    <i class="fas fa-sort-numeric-down text-info mr-1"></i> ترتيب الزوجة عند الزوج
                                    <small class="text-muted">(1 = الأولى — اتركه فاضي للترتيب التلقائي بالتاريخ)</small>
                                </label>
                                <input type="number" name="sort_order" id="create_sort_order" class="form-control" min="1" max="100" value="{{ old('sort_order') }}" placeholder="مثال: 1">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="custom-control custom-checkbox mt-4">
                                <input type="checkbox" name="is_divorced" id="create_is_divorced" class="custom-control-input" value="1" {{ old('is_divorced') ? 'checked' : '' }}>
                                <label class="custom-control-label" for="create_is_divorced">
                                    حالة انفصال / طلاق (بدون تحديد تاريخ دقيق)
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light px-4 py-3">
                    <button type="button" class="btn btn-secondary px-4" data-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-primary px-4 shadow-sm">
                        <i class="fas fa-check mr-1"></i> حفظ سجل الزواج
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

