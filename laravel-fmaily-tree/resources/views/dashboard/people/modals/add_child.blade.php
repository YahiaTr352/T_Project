{{--
    مودال إضافة ابن / ابنة للشخص الحالي
--}}
<div class="modal fade ppl-modal" id="addChildModal" tabindex="-1" role="dialog" aria-labelledby="addChildModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content text-right">
            <div class="modal-header border-bottom">
                <h5 class="modal-title font-weight-bold d-flex align-items-center" id="addChildModalLabel">
                    <i class="fas fa-child text-success ml-2"></i>
                    <span>إضافة ابن / ابنة لـ: {{ $person->full_name }}</span>
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="إغلاق">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form action="{{ route('people.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="from_outside_the_family" value="0">
                <div class="modal-body p-4">
                    {{-- الاسم الأول والأخير --}}
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="form-group mb-0">
                                <label for="child_first_name" class="ppl-form-label">
                                    <span>الاسم الأول للابن/الابنة</span>
                                    <span class="text-danger">*</span>
                                </label>
                                <input type="text" class="form-control ppl-form-control" id="child_first_name" name="first_name"
                                       value="{{ old('first_name') }}" placeholder="الاسم الأول مجرداً..." required>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="form-group mb-0">
                                <label for="child_last_name" class="ppl-form-label">
                                    <span>اسم العائلة أو اللقب</span>
                                </label>
                                <input type="text" class="form-control ppl-form-control" id="child_last_name" name="last_name"
                                       value="{{ old('last_name', $person->last_name) }}">
                            </div>
                        </div>
                    </div>

                    {{-- الجنس --}}
                    <div class="form-group mb-3">
                        <label for="child_gender" class="ppl-form-label">
                            <span>الجنس</span>
                            <span class="text-danger">*</span>
                        </label>
                        <select class="form-control ppl-form-control" id="child_gender" name="gender" required>
                            <option value="male" @selected(old('gender') == 'male')>👨 ذكر</option>
                            <option value="female" @selected(old('gender') == 'female')>👩 أنثى</option>
                        </select>
                    </div>

                    @include('dashboard.people.partials.parent-fields', ['idPrefix' => 'child_', 'knownParent' => $person, 'parentsRequired' => true])

                    {{-- تاريخ الميلاد --}}
                    <div class="form-group mb-3">
                        <label for="child_birth_date" class="ppl-form-label">
                            <i class="fas fa-calendar-alt text-primary"></i>
                            <span>تاريخ الميلاد (ميلادي)</span>
                        </label>
                        <input type="date" class="form-control ppl-form-control" id="child_birth_date" name="birth_date"
                               value="{{ old('birth_date') }}">
                    </div>

                    {{-- صورة الشخص --}}
                    <div class="form-group mb-0">
                        <label for="child_photo" class="ppl-form-label">
                            <i class="fas fa-camera text-secondary"></i>
                            <span>الصورة الشخصية (اختياري)</span>
                        </label>
                        <div class="custom-file">
                            <input type="file" class="custom-file-input" id="child_photo" name="photo" accept="image/*">
                            <label class="custom-file-label ppl-form-control" for="child_photo" style="line-height: 2;">اختر ملف صورة...</label>
                        </div>
                    </div>
                </div>

                <div class="modal-footer d-flex justify-content-between p-3">
                    <button type="button" class="btn btn-secondary px-4 py-2" data-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-success px-4 py-2 font-weight-bold shadow-sm">
                        <i class="fas fa-plus ml-1"></i> حفظ وإضافة الابن
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
