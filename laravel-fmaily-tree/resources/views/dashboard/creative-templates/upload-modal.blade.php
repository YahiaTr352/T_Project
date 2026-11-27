<div class="modal fade" id="visualUploadModal" tabindex="-1" role="dialog" aria-hidden="true" aria-labelledby="creativeUploadTitle" dir="rtl">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-xl" role="document">
        <div class="modal-content border-0 shadow-lg" style="border-radius:18px;">
            <div class="modal-header align-items-center">
                <h5 id="creativeUploadTitle" class="modal-title notif-title font-bold mb-0">
                    <i class="fas fa-cloud-upload-alt ml-1" aria-hidden="true"></i> رفع قالب
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="إغلاق"><span aria-hidden="true">&times;</span></button>
            </div>

            <form id="creativeSubmission" action="{{ route('dashboard.creatives.store', absolute: false) }}" method="POST" enctype="multipart/form-data"
                class="d-flex flex-column" style="min-height:0;" data-admin-upload
                data-preview-url="{{ route('dashboard.creatives.fields.preview', absolute: false) }}">
                @csrf
                <div class="modal-body p-3 p-md-4">
                    <div class="creative-upload-details mb-3">
                        <div>
                            <label class="creative-editor-upload mb-0" for="submissionImage" data-creative-dropzone>
                                <i class="fas fa-cloud-upload-alt" aria-hidden="true"></i>
                                <span id="submissionFileName">اختر صورة التصميم</span>
                                <small>اسحب الصورة أو اضغط للاختيار · JPG أو PNG أو WebP أو GIF أو SVG · حتى ١٦ ميغابايت</small>
                            </label>
                            <input id="submissionImage" name="image" type="file" accept=".jpg,.jpeg,.png,.webp,.gif,.svg,image/svg+xml" required class="sr-only">
                        </div>
                        <div>
                            <label for="submissionType" class="notif-title font-bold d-block mb-1">نوع الدعوة</label>
                            <select id="submissionType" class="dashboard-input w-100" name="invitation_type" required>
                                <option value="">اختر نوع الدعوة</option>
                                @foreach (config('invitation_templates.templates', []) as $template)
                                    <option value="{{ $template['key'] }}">{{ $template['name'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="submissionName" class="notif-title font-bold d-block mb-1">اسم التصميم (اختياري)</label>
                            <input id="submissionName" class="dashboard-input w-100" name="name" maxlength="255">
                        </div>
                    </div>

                    @include('web-site.invitations.partials.creative-field-editor')
                </div>

                <div class="modal-footer creative-editor-footer">
                    <p id="submissionStatus" class="creative-editor-status flex-grow-1" role="status"></p>
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">إلغاء</button>
                    <button type="submit" id="submissionSend" class="creative-editor-button creative-editor-primary">حفظ القالب</button>
                </div>

                @include('web-site.invitations.partials.creative-submission-review', [
                    'reviewTitle' => 'راجع التصميم قبل الحفظ', 'confirmLabel' => 'تأكيد حفظ القالب',
                ])
            </form>
        </div>
    </div>
</div>
