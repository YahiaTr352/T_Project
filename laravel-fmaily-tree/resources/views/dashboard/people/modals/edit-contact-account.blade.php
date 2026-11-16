{{-- مودال تعديل حساب تواصل --}}
<div class="modal fade ppl-modal" id="editContactAccountModal{{ $account->id }}" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content text-right">
            <div class="modal-header border-bottom">
                <h5 class="modal-title font-weight-bold d-flex align-items-center">
                    <i class="fas fa-edit text-primary ml-2"></i>
                    <span>تعديل وسيلة التواصل</span>
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="إغلاق">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form action="{{ route('people.contact-accounts.update', ['person' => $person->id, 'contactAccountId' => $account->id]) }}" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-body p-4">
                    <div class="form-group mb-3">
                        <label class="ppl-form-label">
                            <i class="fas fa-tag text-primary"></i>
                            <span>نوع وسيلة التواصل</span>
                            <span class="text-danger">*</span>
                        </label>
                        <select name="type" class="form-control ppl-form-control" required>
                            <option value="phone" @selected($account->type == 'phone')>📞 هاتف محمول / اتصال</option>
                            <option value="whatsapp" @selected($account->type == 'whatsapp')>💬 واتساب (WhatsApp)</option>
                            <option value="email" @selected($account->type == 'email')>✉️ بريد إلكتروني</option>
                            <option value="twitter" @selected($account->type == 'twitter')>🐦 إكس / تويتر (X)</option>
                            <option value="instagram" @selected($account->type == 'instagram')>📸 إنستجرام</option>
                            <option value="facebook" @selected($account->type == 'facebook')>📘 فيسبوك</option>
                            <option value="linkedin" @selected($account->type == 'linkedin')>💼 لينكد إن</option>
                            <option value="telegram" @selected($account->type == 'telegram')>✈️ تيليجرام</option>
                            <option value="other" @selected($account->type == 'other')>🌐 أخرى</option>
                        </select>
                    </div>
                    <div class="form-group mb-3">
                        <label class="ppl-form-label">
                            <i class="fas fa-pen text-info"></i>
                            <span>القيمة (الرقم أو الرابط أو الحساب)</span>
                            <span class="text-danger">*</span>
                        </label>
                        <input type="text" name="value" class="form-control ppl-form-control" value="{{ $account->value }}" required>
                    </div>
                    <div class="form-group mb-0">
                        <label class="ppl-form-label">
                            <i class="fas fa-info-circle text-secondary"></i>
                            <span>التسمية أو الوصف</span>
                        </label>
                        <input type="text" name="label" class="form-control ppl-form-control" value="{{ $account->label }}" placeholder="مثل: هاتف العمل، هاتف شخصي">
                    </div>
                </div>
                <div class="modal-footer d-flex justify-content-between p-3 border-top">
                    <button type="button" class="btn btn-secondary px-4 py-2" data-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-primary px-4 py-2 font-weight-bold shadow-sm">
                        <i class="fas fa-save ml-1"></i> حفظ التعديلات
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
