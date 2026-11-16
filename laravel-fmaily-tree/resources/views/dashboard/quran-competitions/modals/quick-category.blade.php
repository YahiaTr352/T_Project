<div class="modal fade" id="quickCategoryModal" tabindex="-1" role="dialog" aria-labelledby="quickCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <form id="quickCategoryForm" enctype="multipart/form-data" class="modal-content shadow-lg border-0" style="border-radius: 20px;">
            <div class="modal-header border-bottom p-4 d-flex align-items-center justify-content-between">
                <h5 class="modal-title font-weight-bold d-flex align-items-center gap-2 mb-0" id="quickCategoryModalLabel" style="font-size: 1.15rem;">
                    <div class="p-2 rounded-lg bg-light d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                        <i class="fe fe-folder-plus text-primary" style="font-size: 1.1rem;"></i>
                    </div>
                    <span>إنشاء تصنيف / فئة جديدة</span>
                </h5>
                <button type="button" class="close text-muted" data-dismiss="modal" aria-label="Close" style="opacity: 0.7;">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            
            <div class="modal-body p-4">
                {{-- Category Name --}}
                <div class="form-group mb-3">
                    <label class="font-weight-bold">اسم الفئة <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" style="border-radius: 10px;" required maxlength="255" placeholder="مثال: مسابقات رمضان، فئات البراعم...">
                </div>

                {{-- Category Description --}}
                <div class="form-group mb-3">
                    <label class="font-weight-bold">الوصف (اختياري)</label>
                    <textarea name="description" class="form-control" style="border-radius: 10px;" rows="2" placeholder="نبذة مختصرة عن الفئة..."></textarea>
                </div>

                <div class="row">
                    {{-- Parent Category --}}
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label class="font-weight-bold">الفئة الرئيسية (أب)</label>
                            <select name="parent_id" class="form-control" style="border-radius: 10px;">
                                <option value="">— فئة رئيسية (بدون أب) —</option>
                                @foreach (\App\Models\Category::ordered()->get() as $pc)
                                    <option value="{{ $pc->id }}">{{ $pc->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    {{-- Sort Order --}}
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label class="font-weight-bold">الترتيب</label>
                            <input type="number" name="sort_order" class="form-control" style="border-radius: 10px;" min="0" value="0">
                        </div>
                    </div>
                </div>

                {{-- Category Image --}}
                <div class="form-group mb-3">
                    <label class="font-weight-bold">صورة الفئة (اختياري)</label>
                    <div class="custom-file">
                        <input type="file" name="image" class="custom-file-input" id="catImgInput" accept="image/*">
                        <label class="custom-file-label" for="catImgInput" style="border-radius: 10px;">اختر صورة...</label>
                    </div>
                </div>

                {{-- Program Managers Section --}}
                <div class="border-top pt-3 mt-3">
                    <label class="font-weight-bold d-flex align-items-center gap-2 mb-2">
                        <i class="fe fe-users text-primary"></i>
                        <span>القائمون على البرنامج</span>
                        <small class="text-muted">(اختياري)</small>
                    </label>

                    {{-- Dynamic Managers List --}}
                    <div id="quick-managers-list" class="mb-3" style="max-height: 180px; overflow-y: auto;">
                        <div class="list-group" id="quick-sortable-managers"></div>
                    </div>

                    {{-- Add Manager Row --}}
                    <div class="input-group">
                        @php
                            $allPersons = \App\Models\Person::orderBy('first_name')->orderBy('last_name')->get();
                        @endphp
                        <select class="form-control" id="quick-person-select" style="border-radius: 10px 0 0 10px;">
                            <option value="">— اختر شخص للإضافة —</option>
                            @foreach($allPersons as $person)
                                <option value="{{ $person->id }}" 
                                        data-name="{{ $person->full_name }}" 
                                        data-avatar="{{ $person->avatar }}">
                                    {{ $person->full_name }}
                                </option>
                            @endforeach
                        </select>
                        <div class="input-group-append">
                            <button type="button" class="btn btn-outline-success d-inline-flex align-items-center gap-1" id="quick-add-manager-btn" style="border-radius: 0 10px 10px 0;">
                                <i class="fe fe-plus"></i>
                                <span>إضافة</span>
                            </button>
                        </div>
                    </div>
                    <small class="form-text text-muted mt-1">اختر الشخص من القائمة ثم اضغط على زر إضافة.</small>
                </div>
            </div>

            <div class="modal-footer border-top p-3 d-flex align-items-center justify-content-between">
                @csrf
                <button type="button" class="btn btn-outline-secondary px-3 py-2" data-dismiss="modal" style="border-radius: 10px;">
                    إلغاء
                </button>
                <button id="quickCategorySubmit" type="submit" class="btn btn-primary d-inline-flex align-items-center gap-2 px-4 py-2" style="border-radius: 10px; font-weight: 700;">
                    <i class="fe fe-save"></i>
                    <span>حفظ الفئة</span>
                </button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
$(document).ready(function() {
    let quickManagers = [];

    $('#quick-add-manager-btn').on('click', function() {
        const select = $('#quick-person-select');
        const personId = select.val();
        const selectedOption = select.find('option:selected');
        const personName = selectedOption.data('name') || selectedOption.text();
        const personAvatar = selectedOption.data('avatar') || "{{ asset('assets/img/avatar-male.png') }}";
        
        if (!personId) {
            alert('يرجى اختيار شخص من القائمة أولاً');
            return;
        }

        if (quickManagers.includes(personId)) {
            alert('هذا الشخص موجود بالفعل في القائمة');
            return;
        }

        quickManagers.push(personId);

        const managerHtml = `
            <div class="list-group-item d-flex justify-content-between align-items-center py-2 px-3 mb-1 border rounded" data-person-id="${personId}">
                <div class="d-flex align-items-center">
                    <img src="${personAvatar}" alt="${personName}" class="rounded-circle mr-2" width="32" height="32" style="object-fit: cover;" onerror="this.src='{{ asset('assets/img/avatar-male.png') }}'">
                    <span class="font-weight-bold small">${personName}</span>
                </div>
                <button type="button" class="btn btn-sm btn-outline-danger p-1 quick-remove-manager-btn" data-person-id="${personId}" style="border-radius: 6px;">
                    <i class="fe fe-trash-2"></i>
                </button>
            </div>
        `;

        $('#quick-sortable-managers').append(managerHtml);
        select.val('').trigger('change');
    });

    $(document).on('click', '.quick-remove-manager-btn', function() {
        const personId = $(this).data('person-id').toString();
        quickManagers = quickManagers.filter(id => id.toString() !== personId);
        $(this).closest('.list-group-item').remove();
    });

    $('#quickCategoryForm').on('submit', function(e) {
        e.preventDefault();
        const form = this;
        const fd = new FormData(form);
        
        quickManagers.forEach((personId) => {
            fd.append('managers[]', personId);
        });
        
        const btn = $('#quickCategorySubmit');
        const originalHtml = btn.html();
        btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm mr-1"></span> جارٍ الحفظ...');

        $.ajax({
            url: "{{ route('categories.quick-store') }}",
            method: "POST",
            data: fd,
            contentType: false,
            processData: false,
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            success: function(resp) {
                if (resp.ok && resp.category) {
                    const $select = $('select[name="category_id"]');
                    $select.append(new Option(resp.category.name, resp.category.id, true, true));
                    
                    $('#quickCategoryModal').modal('hide');
                    form.reset();
                    $('.custom-file-label').text('اختر صورة...');
                    quickManagers = [];
                    $('#quick-sortable-managers').empty();
                    
                    if (typeof toastr !== 'undefined') {
                        toastr.success('تم إنشاء الفئة بنجاح');
                    } else {
                        alert('تم إنشاء الفئة بنجاح');
                    }
                } else {
                    alert('حدث خطأ أثناء إنشاء الفئة');
                }
            },
            error: function(xhr) {
                let message = 'تعذر إنشاء الفئة. تحقق من المدخلات.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    message = xhr.responseJSON.message;
                } else if (xhr.responseJSON && xhr.responseJSON.errors) {
                    message = Object.values(xhr.responseJSON.errors).flat().join('\n');
                }
                alert(message);
            },
            complete: function() {
                btn.prop('disabled', false).html(originalHtml);
            }
        });
    });

    $('#quickCategoryModal').on('hidden.bs.modal', function() {
        quickManagers = [];
        $('#quick-sortable-managers').empty();
        $('#quickCategoryForm')[0].reset();
        $('.custom-file-label').text('اختر صورة...');
        $('#quick-person-select').val('').trigger('change');
    });

    $(document).on('change', '.custom-file-input', function() {
        const fileName = $(this).val().split('\\').pop();
        $(this).next('.custom-file-label').html(fileName || 'اختر صورة...');
    });
});
</script>
@endpush
