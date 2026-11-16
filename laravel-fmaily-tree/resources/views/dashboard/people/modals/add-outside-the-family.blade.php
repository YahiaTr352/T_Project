<div class="modal fade ppl-modal" id="addPersonsOutsideTheFamilyTreeModal" tabindex="-1" role="dialog"
    aria-labelledby="addPersonsOutsideModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content text-right">
            <div class="modal-header border-bottom">
                <h5 class="modal-title font-weight-bold d-flex align-items-center" id="addPersonsOutsideModalLabel">
                    <i class="fas fa-handshake text-warning ml-2"></i>
                    <span>إضافة زوج / زوجة من خارج العائلة (مصاهرة)</span>
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="إغلاق">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form action="{{ route('persons.store.outside') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-body p-4">
                    {{-- حقول الاسم --}}
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="form-group mb-0">
                                <label for="outside_first_name" class="ppl-form-label">
                                    <span>الاسم الأول</span>
                                    <span class="text-danger">*</span>
                                </label>
                                <input type="text" class="form-control ppl-form-control" id="outside_first_name" name="first_name"
                                    placeholder="الاسم الأول..." required>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="form-group mb-0">
                                <label for="outside_last_name" class="ppl-form-label">
                                    <span>الاسم الأخير أو العائلة</span>
                                </label>
                                <input type="text" class="form-control ppl-form-control" id="outside_last_name" name="last_name"
                                    placeholder="اسم عائلته الأصلية...">
                            </div>
                        </div>
                    </div>

                    {{-- تواريخ الميلاد والوفاة --}}
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="form-group mb-0">
                                <label for="outside_birth_date" class="ppl-form-label">
                                    <i class="fas fa-calendar-alt text-primary"></i>
                                    <span>تاريخ الميلاد</span>
                                </label>
                                <input type="date" class="form-control ppl-form-control" id="outside_birth_date" name="birth_date">
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="form-group mb-0">
                                <label for="outside_death_date" class="ppl-form-label">
                                    <i class="fas fa-calendar-times text-danger"></i>
                                    <span>تاريخ الوفاة (إن وجد)</span>
                                </label>
                                <input type="date" class="form-control ppl-form-control" id="outside_death_date" name="death_date">
                            </div>
                        </div>
                    </div>

                    {{-- الجنس وربط الشريك --}}
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="form-group mb-0">
                                <label for="outside_gender" class="ppl-form-label">
                                    <span>الجنس</span>
                                    <span class="text-danger">*</span>
                                </label>
                                <select class="form-control ppl-form-control no-search" id="outside_gender" name="gender" required>
                                    <option value="">-- اختر الجنس --</option>
                                    <option value="male">👨 ذكر</option>
                                    <option value="female">👩 أنثى</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="form-group mb-0">
                                <label for="marrying_person_id" class="ppl-form-label">
                                    <i class="fas fa-link text-info"></i>
                                    <span>ربط كـ زوج/زوجة لـ</span>
                                </label>
                                <select class="form-control ppl-form-control" id="marrying_person_id" name="marrying_person_id" data-placeholder="ابحث بالاسم الكامل للشريك من العائلة...">
                                    <option value="">-- اختياري: ابحث بالاسم الكامل --</option>
                                </select>
                                <div id="outside_partner_preview" class="person-preview-card mt-2"></div>
                            </div>
                        </div>
                    </div>

                    {{-- المهنة ومكان الإقامة --}}
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="form-group mb-0">
                                <label for="outside_occupation" class="ppl-form-label">
                                    <i class="fas fa-briefcase text-secondary"></i>
                                    <span>المهنة</span>
                                </label>
                                <input type="text" class="form-control ppl-form-control" id="outside_occupation" name="occupation" placeholder="مثال: طبيب، مهندس...">
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="form-group mb-0 position-relative">
                                <label for="outside_location" class="ppl-form-label">
                                    <i class="fas fa-map-marker-alt text-warning"></i>
                                    <span>مكان الإقامة</span>
                                </label>
                                <input type="text" class="form-control ppl-form-control location-autocomplete" id="outside_location" name="location" autocomplete="off" placeholder="ابدأ الكتابة للبحث...">
                                <input type="hidden" id="outside_location_id" name="location_id">
                                <div id="outside_location_suggestions" class="list-group shadow-lg mt-1" style="display: none; position: absolute; z-index: 1050; max-height: 200px; overflow-y: auto; width: 100%; border-radius: 10px;"></div>
                            </div>
                        </div>
                    </div>

                    {{-- السيرة الذاتية --}}
                    <div class="form-group mb-3">
                        <label for="outside_biography" class="ppl-form-label">
                            <i class="fas fa-pen-nib text-primary"></i>
                            <span>سيرة ذاتية أو نبذة</span>
                        </label>
                        <textarea class="form-control ppl-form-control" id="outside_biography" name="biography" rows="3" placeholder="نبذة مختصرة عن الشخص..."></textarea>
                    </div>

                    {{-- الصورة الشخصية --}}
                    <div class="form-group mb-0">
                        <label for="outside_photo" class="ppl-form-label">
                            <i class="fas fa-camera text-secondary"></i>
                            <span>صورة الشخص</span>
                        </label>
                        <div class="custom-file">
                            <input type="file" class="custom-file-input" id="outside_photo" name="photo" accept="image/*">
                            <label class="custom-file-label ppl-form-control" for="outside_photo" style="line-height: 2;">اختر ملف صورة...</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer d-flex justify-content-between p-3 border-top">
                    <button type="button" class="btn btn-secondary px-4 py-2" data-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-primary px-4 py-2 font-weight-bold shadow-sm">
                        <i class="fas fa-save ml-1"></i> حفظ وإضافة الشريك
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        $('#addPersonsOutsideTheFamilyTreeModal').on('shown.bs.modal', function() {
            if (typeof initPersonSelect2 === 'function') {
                initPersonSelect2('#marrying_person_id', {
                    parentModal: '#addPersonsOutsideTheFamilyTreeModal',
                    placeholder: 'ابحث بالاسم الكامل للشريك من العائلة...',
                    previewContainer: '#outside_partner_preview',
                    gender: function() {
                        const newGender = $('#outside_gender').val();
                        if (newGender === 'male') return 'female';
                        if (newGender === 'female') return 'male';
                        return null;
                    },
                    includeOutside: false
                });
            }
        });

        $('#outside_gender').on('change', function() {
            $('#marrying_person_id').val(null).trigger('change');
            $('#outside_partner_preview').fadeOut(200).empty();
        });
    });

    // Location Autocomplete
    const outsideLocationInput = document.getElementById('outside_location');
    const outsideLocationIdInput = document.getElementById('outside_location_id');
    const outsideSuggestionsDiv = document.getElementById('outside_location_suggestions');
    let searchTimeout;

    if (outsideLocationInput && outsideLocationIdInput && outsideSuggestionsDiv) {
        outsideLocationInput.addEventListener('input', function() {
            const query = this.value.trim();
            clearTimeout(searchTimeout);

            if (query.length < 2) {
                outsideSuggestionsDiv.style.display = 'none';
                outsideLocationIdInput.value = '';
                return;
            }

            searchTimeout = setTimeout(function() {
                fetch('{{ route("locations.autocomplete") }}?q=' + encodeURIComponent(query))
                    .then(response => response.json())
                    .then(data => {
                        outsideSuggestionsDiv.innerHTML = '';

                        if (!Array.isArray(data) || data.length === 0) {
                            outsideSuggestionsDiv.innerHTML = '<div class="list-group-item text-muted p-2">لا توجد نتائج مطابقة</div>';
                            outsideSuggestionsDiv.style.display = 'block';
                            return;
                        }

                        data.forEach(function(location) {
                            const item = document.createElement('div');
                            item.className = 'list-group-item list-group-item-action p-2';
                            item.style.cursor = 'pointer';
                            item.innerHTML = `
                                <strong>${location.name}</strong>
                                ${location.persons_count > 0 ? `<small class="text-muted mr-1">(${location.persons_count} شخص)</small>` : ''}
                            `;

                            item.addEventListener('click', function() {
                                outsideLocationInput.value = location.name;
                                outsideLocationIdInput.value = location.id;
                                outsideSuggestionsDiv.style.display = 'none';
                            });

                            outsideSuggestionsDiv.appendChild(item);
                        });

                        outsideSuggestionsDiv.style.display = 'block';
                    })
                    .catch(error => {
                        console.error('Error fetching outside locations:', error);
                    });
            }, 300);
        });

        document.addEventListener('click', function(e) {
            if (!outsideLocationInput.contains(e.target) && !outsideSuggestionsDiv.contains(e.target)) {
                outsideSuggestionsDiv.style.display = 'none';
            }
        });
    }
</script>
