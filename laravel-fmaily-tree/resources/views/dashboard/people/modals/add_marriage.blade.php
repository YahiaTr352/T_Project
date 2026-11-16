{{--
    مودال إنشاء عقد زواج جديد للشخص الحالي
--}}
<div class="modal fade ppl-modal" id="addMarriageModal" tabindex="-1" role="dialog" aria-labelledby="addMarriageModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content text-right">
            <div class="modal-header border-bottom">
                <h5 class="modal-title font-weight-bold d-flex align-items-center" id="addMarriageModalLabel">
                    <i class="fas fa-ring text-warning ml-2"></i>
                    <span>إضافة عقد زواج لـ: {{ $person->full_name }}</span>
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="إغلاق">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form action="{{ route('marriages.store') }}" method="POST" id="personAddMarriageForm">
                @csrf
                <div class="modal-body p-4">
                    {{-- بطاقات اختيار الزوجين --}}
                    <div class="row align-items-center mb-4">
                        {{-- الزوج --}}
                        <div class="col-md-5">
                            <div class="form-group mb-0">
                                <label class="ppl-form-label text-primary">
                                    <i class="fas fa-mars"></i> الزوج <span class="text-danger">*</span>
                                </label>
                                @if($person->gender == 'male')
                                    <input type="hidden" name="husband_id" value="{{ $person->id }}">
                                    <div class="d-flex align-items-center p-3 rounded-lg border" style="background: var(--ppl-profile-item-bg);">
                                        <img src="{{ $person->avatar }}" alt="{{ $person->full_name }}" class="rounded-circle ml-3 border" width="50" height="50" style="object-fit: cover;">
                                        <div>
                                            <div class="font-weight-bold ppl-text-title" style="font-size: 1.05rem;">{{ $person->full_name }}</div>
                                            <span class="ppl-badge ppl-badge-male mt-1">الزوج الحالي</span>
                                        </div>
                                    </div>
                                @else
                                    <select name="husband_id" id="person_modal_husband_id" class="form-control ppl-form-control" required data-placeholder="ابحث عن الزوج بالاسم الكامل...">
                                        <option value=""></option>
                                    </select>
                                    <div id="person_modal_husband_preview" class="person-preview-card male mt-2"></div>
                                @endif
                            </div>
                        </div>

                        {{-- أيقونة الربط --}}
                        <div class="col-md-2 text-center my-3 my-md-0">
                            <div class="d-inline-flex align-items-center justify-content-center rounded-circle shadow-sm" 
                                 style="width: 48px; height: 48px; background: #fee2e2; color: #ef4444; font-size: 1.25rem;">
                                <i class="fas fa-heart"></i>
                            </div>
                        </div>

                        {{-- الزوجة --}}
                        <div class="col-md-5">
                            <div class="form-group mb-0">
                                <div class="d-flex align-items-center justify-content-between mb-1">
                                    <label class="ppl-form-label text-pink mb-0">
                                        <i class="fas fa-venus"></i> الزوجة <span class="text-danger">*</span>
                                    </label>
                                    @if($person->gender == 'male')
                                        <button type="button" class="btn btn-link btn-sm p-0 text-primary font-weight-bold" 
                                                data-toggle="modal" data-target="#addPersonsOutsideTheFamilyTreeModal" style="font-size: 0.85rem; text-decoration: none;">
                                            <i class="fas fa-user-plus ml-1"></i> من خارج العائلة؟
                                        </button>
                                    @endif
                                </div>
                                @if($person->gender == 'female')
                                    <input type="hidden" name="wife_id" value="{{ $person->id }}">
                                    <div class="d-flex align-items-center p-3 rounded-lg border" style="background: var(--ppl-profile-item-bg);">
                                        <img src="{{ $person->avatar }}" alt="{{ $person->full_name }}" class="rounded-circle ml-3 border" width="50" height="50" style="object-fit: cover;">
                                        <div>
                                            <div class="font-weight-bold ppl-text-title" style="font-size: 1.05rem;">{{ $person->full_name }}</div>
                                            <span class="ppl-badge ppl-badge-female mt-1">الزوجة الحالية</span>
                                        </div>
                                    </div>
                                @else
                                    <select name="wife_id" id="person_modal_wife_id" class="form-control ppl-form-control" required data-placeholder="ابحث عن الزوجة بالاسم الكامل...">
                                        <option value=""></option>
                                    </select>
                                    <div id="person_modal_wife_preview" class="person-preview-card female mt-2"></div>
                                @endif
                            </div>
                        </div>
                    </div>

                    <hr class="my-4" style="border-top: 1px solid var(--ppl-card-border);">

                    {{-- تواريخ الزواج والانفصال --}}
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="form-group mb-0">
                                <label for="person_married_at" class="ppl-form-label">
                                    <i class="fas fa-calendar-check text-success"></i>
                                    <span>تاريخ الزواج (ميلادي)</span>
                                </label>
                                <input type="date" name="married_at" id="person_married_at" class="form-control ppl-form-control" value="{{ old('married_at') }}">
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="form-group mb-0">
                                <label for="person_divorced_at" class="ppl-form-label">
                                    <i class="fas fa-calendar-times text-warning"></i>
                                    <span>تاريخ الانفصال (إن وجد)</span>
                                </label>
                                <input type="date" name="divorced_at" id="person_divorced_at" class="form-control ppl-form-control" value="{{ old('divorced_at') }}">
                            </div>
                        </div>
                    </div>

                    <div class="custom-control custom-checkbox mt-2">
                        <input type="checkbox" name="is_divorced" id="person_is_divorced" class="custom-control-input" value="1" {{ old('is_divorced') ? 'checked' : '' }}>
                        <label class="custom-control-label font-weight-bold" for="person_is_divorced" style="cursor: pointer;">
                            حالة انفصال / طلاق (دون تحديد تاريخ دقيق)
                        </label>
                    </div>
                </div>
                <div class="modal-footer d-flex justify-content-between p-3 border-top">
                    <button type="button" class="btn btn-secondary px-4 py-2" data-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-primary px-4 py-2 font-weight-bold shadow-sm">
                        <i class="fas fa-check ml-1"></i> حفظ وتوثيق عقد الزواج
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
