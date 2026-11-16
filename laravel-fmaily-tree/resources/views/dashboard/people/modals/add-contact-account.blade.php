{{-- مودال إضافة حساب تواصل --}}
<div class="modal fade ppl-modal" id="addContactAccountModal{{ $person->id }}" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content text-right">
            <div class="modal-header border-bottom">
                <h5 class="modal-title font-weight-bold d-flex align-items-center">
                    <i class="fas fa-plus-circle text-success ml-2"></i>
                    <span>إضافة وسيلة تواصل لـ: {{ $person->full_name }}</span>
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="إغلاق">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form action="{{ route('people.contact-accounts.store', $person->id) }}" method="POST">
                @csrf
                <div class="modal-body p-4">
                    <div class="form-group mb-3">
                        <label class="ppl-form-label">
                            <i class="fas fa-tag text-primary"></i>
                            <span>نوع وسيلة التواصل</span>
                            <span class="text-danger">*</span>
                        </label>
                        <select name="type" class="form-control ppl-form-control" required>
                            <option value="phone">📞 هاتف محمول / اتصال</option>
                            <option value="whatsapp">💬 واتساب (WhatsApp)</option>
                            <option value="email">✉️ بريد إلكتروني</option>
                            <option value="twitter">🐦 إكس / تويتر (X)</option>
                            <option value="instagram">📸 إنستجرام</option>
                            <option value="facebook">📘 فيسبوك</option>
                            <option value="linkedin">💼 لينكد إن</option>
                            <option value="telegram">✈️ تيليجرام</option>
                            <option value="other">🌐 أخرى</option>
                        </select>
                    </div>
                    <div class="form-group mb-3">
                        <label class="ppl-form-label">
                            <i class="fas fa-pen text-info"></i>
                            <span>القيمة (الرقم أو الرابط أو الحساب)</span>
                            <span class="text-danger">*</span>
                        </label>
                        <input type="text" name="value" class="form-control ppl-form-control" placeholder="مثال: 0501234567 أو user@mail.com" required>
                    </div>
                    <div class="form-group mb-0">
                        <label class="ppl-form-label">
                            <i class="fas fa-info-circle text-secondary"></i>
                            <span>التسمية أو الوصف (اختياري)</span>
                        </label>
                        <input type="text" name="label" class="form-control ppl-form-control" placeholder="مثال: هاتف العمل، هاتف شخصي، واتساب العائلة">
                    </div>
                </div>
                <div class="modal-footer d-flex justify-content-between p-3 border-top">
                    <button type="button" class="btn btn-secondary px-4 py-2" data-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-primary px-4 py-2 font-weight-bold shadow-sm">
                        <i class="fas fa-save ml-1"></i> حفظ الوسيلة
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
