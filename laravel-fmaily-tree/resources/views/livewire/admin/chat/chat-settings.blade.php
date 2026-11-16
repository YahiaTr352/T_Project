<div>
    <form wire:submit="save">
        <div class="row">
            {{-- عام --}}
            <div class="col-xl-6 mb-4">
                <div class="card shadow h-100">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-cog mr-1"></i> إعدادات عامة</h6>
                    </div>
                    <div class="card-body">
                        <div class="custom-control custom-switch mb-3">
                            <input type="checkbox" class="custom-control-input" id="chatEnabled" wire:model="form.chat.enabled">
                            <label class="custom-control-label" for="chatEnabled">تفعيل المحادثات على مستوى النظام</label>
                            <small class="text-muted d-block">عند الإيقاف لن يستطيع أي مستخدم إرسال رسائل.</small>
                        </div>

                        <div class="custom-control custom-switch mb-3">
                            <input type="checkbox" class="custom-control-input" id="allowAttachments" wire:model="form.chat.allow_attachments">
                            <label class="custom-control-label" for="allowAttachments">السماح بالمرفقات (صور وفيديو)</label>
                        </div>

                        <div class="form-group">
                            <label class="small text-muted">أقصى طول للرسالة (حرف)</label>
                            <input type="number" min="100" max="10000" wire:model="form.chat.max_message_length"
                                class="form-control form-control-sm">
                        </div>

                        <div class="form-group">
                            <label class="small text-muted">الوضع البطيء الافتراضي (ثانية، 0 = معطّل)</label>
                            <input type="number" min="0" max="86400" wire:model="form.chat.slow_mode_seconds"
                                class="form-control form-control-sm">
                        </div>

                        <div class="form-group">
                            <label class="small text-muted">حد الإرسال لكل مشارك في الدقيقة</label>
                            <input type="number" min="0" max="600" wire:model="form.chat.participant_rate_limit_per_minute"
                                class="form-control form-control-sm">
                            <small class="text-muted">0 = بدون حد إضافي (الحد العام على مستوى الطلبات يبقى سارياً).</small>
                        </div>

                        <div class="form-group mb-0">
                            <label class="small text-muted">مدة الاحتفاظ بالرسائل (يوم، 0 = للأبد)</label>
                            <input type="number" min="0" max="3650" wire:model="form.chat.retention_days"
                                class="form-control form-control-sm">
                        </div>
                    </div>
                </div>
            </div>

            {{-- فلتر المحتوى --}}
            <div class="col-xl-6 mb-4">
                <div class="card shadow h-100 mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-filter mr-1"></i> فلتر المحتوى</h6>
                    </div>
                    <div class="card-body">
                        <div class="custom-control custom-switch mb-3">
                            <input type="checkbox" class="custom-control-input" id="filterEnabled" wire:model="form.chat.filter.enabled">
                            <label class="custom-control-label" for="filterEnabled">تفعيل فلتر المحتوى</label>
                        </div>

                        <div class="custom-control custom-switch mb-3">
                            <input type="checkbox" class="custom-control-input" id="filterBlocked" wire:model="form.chat.filter.blocked_words">
                            <label class="custom-control-label" for="filterBlocked">فحص الكلمات الممنوعة</label>
                        </div>

                        <div class="custom-control custom-switch mb-3">
                            <input type="checkbox" class="custom-control-input" id="filterMask" wire:model="form.chat.filter.mask_words">
                            <label class="custom-control-label" for="filterMask">إخفاء الكلمات بدل منعها (***)</label>
                        </div>

                        <div class="custom-control custom-switch mb-3">
                            <input type="checkbox" class="custom-control-input" id="filterLinks" wire:model="form.chat.filter.links">
                            <label class="custom-control-label" for="filterLinks">فحص الروابط الخارجية</label>
                        </div>

                        <div class="form-group">
                            <label class="small text-muted">إجراء الروابط غير المسموح بها</label>
                            <select wire:model="filterLinkAction" class="form-control form-control-sm">
                                <option value="warn">تحذير فقط</option>
                                <option value="block">منع الإرسال</option>
                            </select>
                        </div>

                        <div class="custom-control custom-switch mb-3">
                            <input type="checkbox" class="custom-control-input" id="filterSpam" wire:model="form.chat.filter.spam_patterns">
                            <label class="custom-control-label" for="filterSpam">كشف نمط السبام (تكرار حروف / منشن / روابط)</label>
                        </div>

                        <div class="form-group mb-0">
                            <label class="small text-muted">أقصى عدد منشن في الرسالة</label>
                            <input type="number" min="0" max="100" wire:model="form.chat.filter.max_mentions_per_message"
                                class="form-control form-control-sm">
                        </div>
                    </div>
                </div>
            </div>

            {{-- التصعيد --}}
            <div class="col-xl-6 mb-4">
                <div class="card shadow h-100 mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-level-up mr-1"></i> التصعيد التلقائي</h6>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label class="small text-muted">عدد التحذيرات قبل تسجيل المخالفة</label>
                            <input type="number" min="0" max="50" wire:model="form.chat.escalation.warnings_before_flag"
                                class="form-control form-control-sm">
                        </div>

                        <div class="form-group">
                            <label class="small text-muted">حظر تلقائي بعد (بلاغ، 0 = معطّل)</label>
                            <input type="number" min="0" max="100" wire:model="form.chat.escalation.auto_ban_after_reports"
                                class="form-control form-control-sm">
                        </div>

                        <div class="form-group">
                            <label class="small text-muted">نطاق الحظر التلقائي</label>
                            <select wire:model="escalationAutoBanScope" class="form-control form-control-sm">
                                <option value="conversation">المحادثة فقط</option>
                                <option value="global">كل المحادثات</option>
                            </select>
                        </div>

                        <div class="form-group mb-0">
                            <label class="small text-muted">مدة الحظر التلقائي (يوم)</label>
                            <input type="number" min="0" max="365" wire:model="form.chat.escalation.auto_ban_duration_days"
                                class="form-control form-control-sm">
                        </div>
                    </div>
                </div>
            </div>

            {{-- الإشراف الذكي --}}
            <div class="col-xl-6 mb-4">
                <div class="card shadow h-100">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-robot mr-1"></i> الإشراف الذكي (ذكاء اصطناعي)</h6>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info py-2 px-3 small">
                            <i class="fas fa-info-circle mr-1"></i>
                            التكلفة ترتفع مع فحص كل الرسائل — الأفضل تفعيله على الرسائل المُبلَّغ عنها فقط.
                        </div>

                        <div class="custom-control custom-switch mb-3">
                            <input type="checkbox" class="custom-control-input" id="aiEnabled" wire:model="form.chat.moderation.ai_enabled">
                            <label class="custom-control-label" for="aiEnabled">تفعيل الفحص الذكي</label>
                        </div>

                        <div class="custom-control custom-switch mb-3">
                            <input type="checkbox" class="custom-control-input" id="aiReportedOnly" wire:model="form.chat.moderation.ai_reported_only">
                            <label class="custom-control-label" for="aiReportedOnly">فحص الرسائل المُبلَّغ عنها فقط (موصى به)</label>
                        </div>

                        <div class="form-group">
                            <label class="small text-muted">نسبة العيّنة المفحوصة من كل الرسائل (0-100)</label>
                            <input type="number" min="0" max="100" wire:model="form.chat.moderation.ai_sample_rate"
                                class="form-control form-control-sm">
                        </div>

                        <div class="form-group mb-0">
                            <label class="small text-muted">حد الحساسية (0 - 1)</label>
                            <input type="number" min="0" max="1" step="0.05" wire:model="form.chat.moderation.ai_threshold"
                                class="form-control form-control-sm">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow mb-4">
            <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-2">
                <button type="button" wire:click="resetToDefaults" class="btn btn-outline-secondary btn-sm">
                    <i class="fas fa-redo mr-1"></i> استعادة الإعدادات الافتراضية
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save mr-1"></i> حفظ الإعدادات
                </button>
            </div>
        </div>
    </form>
</div>
