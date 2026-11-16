/**
 * Visual Library — shared interactions for invitation backgrounds & creative templates.
 *
 * - No inline onclick, no alert()/confirm().
 * - Bootstrap 4 modals + toast stack.
 * - Dark/light safe (relies on CSS classes, no hardcoded theme colors here).
 *
 * Expected markup (see components/dashboard/visual/*):
 * - [data-visual-preview-trigger] with data-image + data-title
 * - [data-visual-approve] with URL value
 * - [data-visual-reject-open] with URL value
 * - [data-visual-delete] with URL value + data-visual-name
 * - Reject modal: [data-visual-reject-submit] + textarea input
 * - Confirm modal: [data-visual-confirm-submit] + [data-visual-confirm-message]
 * - Upload modal: form[data-visual-upload-form] with data-url
 */
(function () {
    'use strict';

    function csrfToken() {
        var el = document.querySelector('meta[name="csrf-token"]');
        return el ? el.getAttribute('content') : '';
    }

    function toast(message, type) {
        var stack = document.getElementById('visualToastStack');
        if (!stack) return;
        var el = document.createElement('div');
        el.className = 'visual-toast visual-toast-' + (type || 'success');
        var icon = type === 'danger'
            ? 'fa-exclamation-circle'
            : (type === 'info' ? 'fa-info-circle' : 'fa-check-circle');
        el.innerHTML = '<i class="fas ' + icon + '"></i><span></span>';
        el.querySelector('span').textContent = message || '';
        stack.appendChild(el);
        window.setTimeout(function () {
            el.classList.add('is-leaving');
            window.setTimeout(function () { el.remove(); }, 320);
        }, 3400);
    }

    function parseJsonSafe(text) {
        try { return JSON.parse(text); } catch (e) { return null; }
    }

    function postForm(url, formData) {
        return fetch(url, {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': csrfToken(),
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        }).then(function (res) {
            return res.text().then(function (text) {
                return { status: res.status, body: parseJsonSafe(text) || {} };
            });
        });
    }

    function deleteJson(url) {
        return fetch(url, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': csrfToken(),
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        }).then(function (res) {
            return res.text().then(function (text) {
                return { status: res.status, body: parseJsonSafe(text) || {} };
            });
        });
    }

    function setLoading(btn, isLoading, loadingHtml) {
        if (!btn) return;
        if (isLoading) {
            btn.disabled = true;
            if (!btn.dataset.originalHtml) btn.dataset.originalHtml = btn.innerHTML;
            btn.innerHTML = loadingHtml || '<i class="fas fa-spinner fa-spin ml-1"></i> جارٍ التنفيذ…';
        } else {
            btn.disabled = false;
            if (btn.dataset.originalHtml) btn.innerHTML = btn.dataset.originalHtml;
        }
    }

    function showModal(selector) {
        if (window.jQuery && window.jQuery(selector).modal) {
            window.jQuery(selector).modal('show');
        } else {
            var el = document.querySelector(selector);
            if (el) el.style.display = 'block';
        }
    }

    function hideModal(selector) {
        if (window.jQuery && window.jQuery(selector).modal) {
            window.jQuery(selector).modal('hide');
        } else {
            var el = document.querySelector(selector);
            if (el) el.style.display = 'none';
        }
    }

    function init(root) {
        root = root || document;

        var previewModalSel = root.querySelector('[data-visual-preview-modal]')
            ? root.querySelector('[data-visual-preview-modal]').dataset.visualPreviewModal
            : '#visualPreviewModal';
        var rejectModalSel = '#visualRejectModal';
        var confirmModalSel = '#visualConfirmModal';

        // Allow each page to override modal selectors via data attributes on body/container.
        var scope = root.querySelector('[data-visual-scope]');
        if (scope) {
            if (scope.dataset.previewModal) previewModalSel = scope.dataset.previewModal;
            if (scope.dataset.rejectModal) rejectModalSel = scope.dataset.rejectModal;
            if (scope.dataset.confirmModal) confirmModalSel = scope.dataset.confirmModal;
        }

        var pendingConfirm = null;
        var pendingRejectUrl = null;

        // ---- Preview (event delegation, keyboard accessible) ----
        document.addEventListener('click', function (e) {
            var trigger = e.target.closest('[data-visual-preview-trigger]');
            if (!trigger) return;
            var img = trigger.getAttribute('data-image');
            var title = trigger.getAttribute('data-title') || 'معاينة';
            if (!img) return;
            var modal = document.querySelector(previewModalSel);
            if (!modal) return;
            var imgEl = modal.querySelector('[data-visual-preview-image]');
            var titleEl = modal.querySelector('[data-visual-preview-title]');
            var captionEl = modal.querySelector('[data-visual-preview-caption]');
            var openEl = modal.querySelector('[data-visual-preview-open]');
            if (imgEl) { imgEl.src = img; imgEl.alt = title; }
            if (titleEl) titleEl.textContent = title;
            if (captionEl) captionEl.textContent = title;
            if (openEl) openEl.href = img;
            showModal(previewModalSel);
        });

        document.addEventListener('keydown', function (e) {
            if ((e.key === 'Enter' || e.key === ' ') && e.target.matches('[data-visual-preview-trigger]')) {
                e.preventDefault();
                e.target.click();
            }
        });

        // ---- Approve (with confirm modal) ----
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-visual-approve]');
            if (!btn) return;
            var url = btn.getAttribute('data-visual-approve');
            var name = btn.closest('[data-visual-card]')
                ? btn.closest('[data-visual-card]').querySelector('.visual-lib-title').textContent.trim()
                : '';
            pendingConfirm = { url: url, btn: btn, kind: 'approve' };
            var modal = document.querySelector(confirmModalSel);
            if (modal) {
                var msg = modal.querySelector('[data-visual-confirm-message]');
                if (msg) msg.textContent = 'اعتماد "' + (name || 'هذا العنصر') + '" وإتاحته للاستخدام؟';
                var submit = modal.querySelector('[data-visual-confirm-submit]');
                if (submit) {
                    submit.className = 'btn btn-sm btn-success px-4 font-bold';
                    submit.innerHTML = '<i class="fas fa-check ml-1"></i> اعتماد';
                }
                var titleEl = modal.querySelector('.modal-body h5');
                if (titleEl) titleEl.textContent = 'تأكيد الاعتماد';
            }
            showModal(confirmModalSel);
        });

        // ---- Delete (with confirm modal) ----
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-visual-delete]');
            if (!btn) return;
            var url = btn.getAttribute('data-visual-delete');
            var name = btn.getAttribute('data-visual-name') || 'هذا العنصر';
            pendingConfirm = { url: url, btn: btn, kind: 'delete' };
            var modal = document.querySelector(confirmModalSel);
            if (modal) {
                var msg = modal.querySelector('[data-visual-confirm-message]');
                if (msg) msg.textContent = 'حذف "' + name + '" نهائيًا؟ لا يمكن التراجع عن هذا الإجراء.';
                var submit = modal.querySelector('[data-visual-confirm-submit]');
                if (submit) {
                    submit.className = 'btn btn-sm btn-danger px-4 font-bold';
                    submit.innerHTML = '<i class="fas fa-trash-alt ml-1"></i> حذف نهائي';
                }
                var titleEl = modal.querySelector('.modal-body h5');
                if (titleEl) titleEl.textContent = 'تأكيد الحذف';
            }
            showModal(confirmModalSel);
        });

        document.addEventListener('click', function (e) {
            var submit = e.target.closest('[data-visual-confirm-submit]');
            if (!submit || !pendingConfirm) return;
            var current = pendingConfirm;
            pendingConfirm = null;
            setLoading(submit, true);
            var done = function () {
                setLoading(submit, false);
                hideModal(confirmModalSel);
            };
            if (current.kind === 'delete') {
                setLoading(current.btn, true);
                deleteJson(current.url).then(function (res) {
                    done();
                    setLoading(current.btn, false);
                    toast(res.body.message || 'تم تنفيذ الحذف.', res.body.success ? 'success' : 'danger');
                    if (res.body.success) window.setTimeout(function () { window.location.reload(); }, 600);
                }).catch(function () {
                    done();
                    setLoading(current.btn, false);
                    toast('تعذر الاتصال بالخادم.', 'danger');
                });
            } else {
                setLoading(current.btn, true);
                postForm(current.url, new FormData()).then(function (res) {
                    done();
                    setLoading(current.btn, false);
                    toast(res.body.message || '', res.body.success ? 'success' : 'danger');
                    if (res.body.success) window.setTimeout(function () { window.location.reload(); }, 600);
                }).catch(function () {
                    done();
                    setLoading(current.btn, false);
                    toast('تعذر الاتصال بالخادم.', 'danger');
                });
            }
        });

        // ---- Reject modal ----
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-visual-reject-open]');
            if (!btn) return;
            pendingRejectUrl = btn.getAttribute('data-visual-reject-open');
            var modal = document.querySelector(rejectModalSel);
            if (modal) {
                var area = modal.querySelector('textarea');
                if (area) area.value = '';
                var counter = modal.querySelector('[data-visual-reject-counter]');
                if (counter) counter.textContent = '0';
            }
            showModal(rejectModalSel);
        });

        document.addEventListener('input', function (e) {
            if (!e.target.matches('#visualRejectModal textarea, #ctRejectModal textarea, #bgRejectModal textarea')) return;
            var modal = e.target.closest('.modal');
            var counter = modal ? modal.querySelector('[data-visual-reject-counter]') : null;
            if (counter) counter.textContent = String(e.target.value.length);
        });

        document.addEventListener('click', function (e) {
            var submit = e.target.closest('[data-visual-reject-submit]');
            if (!submit || !pendingRejectUrl) return;
            var modal = submit.closest('.modal');
            var area = modal ? modal.querySelector('textarea') : null;
            var reason = area ? area.value.trim() : '';
            if (!reason) {
                toast('يرجى كتابة سبب الرفض أولًا.', 'danger');
                if (area) area.focus();
                return;
            }
            var fd = new FormData();
            fd.append('reason', reason);
            setLoading(submit, true);
            postForm(pendingRejectUrl, fd).then(function (res) {
                setLoading(submit, false);
                toast(res.body.message || '', res.body.success ? 'success' : 'danger');
                if (res.body.success) {
                    hideModal('#' + (modal ? modal.id : 'visualRejectModal'));
                    window.setTimeout(function () { window.location.reload(); }, 600);
                }
            }).catch(function () {
                setLoading(submit, false);
                toast('تعذر الاتصال بالخادم.', 'danger');
            });
        });

        // ---- Upload: file preview ----
        document.addEventListener('change', function (e) {
            var input = e.target.closest('[data-visual-file-input]');
            if (!input || !input.files || !input.files[0]) return;
            var file = input.files[0];
            var label = input.closest('[data-visual-dropzone]');
            var preview = label ? label.querySelector('[data-visual-file-preview]') : null;
            var empty = label ? label.querySelector('[data-visual-drop-empty]') : null;
            if (!preview) return;
            var reader = new FileReader();
            reader.onload = function (ev) {
                preview.src = ev.target.result;
                preview.classList.remove('d-none');
                if (empty) empty.classList.add('d-none');
            };
            reader.readAsDataURL(file);
        });

        // ---- Upload: submit ----
        document.addEventListener('submit', function (e) {
            var form = e.target.closest('[data-visual-upload-form]');
            if (!form) return;
            e.preventDefault();
            var url = form.getAttribute('data-url');
            var fileInput = form.querySelector('[data-visual-file-input]');
            var nameInput = form.querySelector('[data-visual-name-input]');
            var extraInput = form.querySelector('[data-visual-extra-input]');
            if (!fileInput || !fileInput.files || !fileInput.files[0]) {
                toast('يرجى اختيار ملف الصورة أولًا.', 'danger');
                return;
            }
            var fd = new FormData();
            fd.append('image', fileInput.files[0]);
            if (nameInput) fd.append('name', nameInput.value || '');
            if (extraInput) {
                if (extraInput.type === 'checkbox') {
                    if (extraInput.checked) fd.append('is_template', '1');
                } else {
                    fd.append('description', extraInput.value || '');
                }
            }
            var submit = form.querySelector('[data-visual-upload-submit]');
            setLoading(submit, true);
            postForm(url, fd).then(function (res) {
                setLoading(submit, false);
                if (!res.body.success) {
                    var msg = res.body.message || 'فشل الرفع.';
                    if (res.body.errors) {
                        var first = Object.values(res.body.errors)[0];
                        if (Array.isArray(first) && first[0]) msg = first[0];
                    }
                    toast(msg, 'danger');
                    return;
                }
                toast(res.body.message || 'تم الرفع بنجاح.', 'success');
                var modal = form.closest('.modal');
                if (modal) hideModal('#' + modal.id);
                window.setTimeout(function () { window.location.reload(); }, 600);
            }).catch(function () {
                setLoading(submit, false);
                toast('تعذر الاتصال بالخادم.', 'danger');
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { init(); });
    } else {
        init();
    }

    window.VisualLibrary = { init: init, toast: toast };
})();
