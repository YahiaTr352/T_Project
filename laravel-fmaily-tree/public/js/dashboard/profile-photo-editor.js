/**
 * Profile update request — personal photo tools.
 *
 * Adds two capabilities to the review screen (resources/views/dashboard/profile-updates/show.blade.php):
 *  1. A zoomable full-size viewer (no third-party dependency).
 *  2. A crop/rotate/flip/replace editor (Cropper.js v1.6.2, vendored in
 *     public/admin-assets/vendor/cropperjs/) whose result is stored as the effective photo of the
 *     pending request through POST dashboard.profile-updates.photo.update.
 *
 * All server communication degrades gracefully: when Cropper.js is missing, the plain multipart
 * fallback form rendered by the Blade view keeps the "replace photo" path usable.
 */
(function () {
    'use strict';

    var root = document.getElementById('profilePhotoEditor');
    if (!root) {
        return;
    }

    var config = {};
    try {
        config = JSON.parse(root.getAttribute('data-config') || '{}');
    } catch (e) {
        config = {};
    }

    var messages = Object.assign({
        saving: 'جارٍ حفظ الصورة...',
        saved: 'تم حفظ الصورة المعدّلة',
        saveFailed: 'تعذّر حفظ الصورة، يرجى إعادة المحاولة.',
        resetConfirm: 'سيتم استبدال الصورة المعدّلة بالصورة الأصلية التي أرسلها العضو. متابعة؟',
        resetDone: 'تمت استعادة صورة العضو الأصلية',
        resetFailed: 'تعذّر استعادة الصورة الأصلية.',
        missingTool: 'تعذّر تحميل أداة القص، يمكنك رفع صورة بديلة من النموذج أدناه.',
        emptyCrop: 'تعذّر تجهيز الصورة بعد القص، يرجى إعادة المحاولة.',
    }, config.messages || {});

    var csrfToken = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

    function notify(type, title) {
        if (window.Swal && typeof window.Swal.fire === 'function') {
            window.Swal.fire({
                toast: true,
                position: 'top-start',
                icon: type,
                title: title,
                showConfirmButton: false,
                timer: type === 'error' ? 5000 : 2800,
            });
            return;
        }
        window.alert(title);
    }

    function confirmAction(text) {
        if (window.Swal && typeof window.Swal.fire === 'function') {
            return window.Swal.fire({
                icon: 'question',
                title: 'تأكيد',
                text: text,
                showCancelButton: true,
                confirmButtonText: 'نعم، متابعة',
                cancelButtonText: 'إلغاء',
                confirmButtonColor: '#28a745',
                cancelButtonColor: '#6c757d',
            }).then(function (result) {
                return !!result.isConfirmed;
            });
        }
        return Promise.resolve(window.confirm(text));
    }

    /* ---------------------------------------------------------------------
     * 1) Full-size zoom viewer
     * ------------------------------------------------------------------- */
    (function initViewer() {
        var modal = document.getElementById('photoViewerModal');
        if (!modal) {
            return;
        }

        var image = document.getElementById('photoViewerImage');
        var titleEl = document.getElementById('photoViewerTitle');
        var zoomLabel = document.getElementById('photoViewerZoomLabel');
        var openTab = document.getElementById('photoViewerOpenTab');
        var stage = document.getElementById('photoViewerStage');
        var errorBox = document.getElementById('photoViewerError');
        var zoom = 100;
        var minZoom = 25;
        var maxZoom = 500;

        function applyZoom() {
            if (!image) {
                return;
            }
            image.style.width = zoom + '%';
            image.style.maxWidth = zoom <= 100 ? '100%' : 'none';
            if (zoomLabel) {
                zoomLabel.textContent = Math.round(zoom) + '%';
            }
        }

        function setZoom(value) {
            zoom = Math.min(maxZoom, Math.max(minZoom, value));
            applyZoom();
        }

        function resetImageState() {
            if (image) {
                image.style.display = '';
            }
            if (errorBox) {
                errorBox.classList.add('d-none');
            }
        }

        // Core open routine: sets the source synchronously, so it can be called from a click
        // (before the Bootstrap modal opens) and from the modal's show event as a safety net.
        function setViewerSource(trigger) {
            if (!trigger || !image) {
                return false;
            }

            var src = trigger.getAttribute('data-photo-src') || '';
            if (!src) {
                return false;
            }

            resetImageState();
            image.setAttribute('src', src);
            if (titleEl) {
                titleEl.textContent = trigger.getAttribute('data-photo-title') || 'تكبير الصورة';
            }
            if (openTab) {
                openTab.setAttribute('href', src);
            }
            zoom = 100;
            applyZoom();
            if (stage) {
                stage.scrollTop = 0;
            }
            return true;
        }

        // 1) Direct click handler: guarantees the image is loaded even if the modal's show event
        //    path (relatedTarget) is not available in the running Bootstrap build.
        Array.prototype.forEach.call(document.querySelectorAll('.photo-zoomable'), function (trigger) {
            trigger.addEventListener('click', function () {
                setViewerSource(trigger);
            });
        });

        // 2) Safety net for programmatic opens / data-toggle.
        modal.addEventListener('show.bs.modal', function (event) {
            var trigger = event.relatedTarget || document.querySelector('.photo-zoomable');
            setViewerSource(trigger);
        });

        modal.addEventListener('hidden.bs.modal', function () {
            if (image) {
                image.removeAttribute('src');
            }
            resetImageState();
        });

        if (image) {
            image.addEventListener('error', function () {
                image.style.display = 'none';
                if (errorBox) {
                    errorBox.classList.remove('d-none');
                }
            });
        }

        var zoomInBtn = document.getElementById('photoViewerZoomIn');
        var zoomOutBtn = document.getElementById('photoViewerZoomOut');
        var resetBtn = document.getElementById('photoViewerReset');
        var fitBtn = document.getElementById('photoViewerFit');

        if (zoomInBtn) {
            zoomInBtn.addEventListener('click', function () { setZoom(zoom * 1.25); });
        }
        if (zoomOutBtn) {
            zoomOutBtn.addEventListener('click', function () { setZoom(zoom / 1.25); });
        }
        if (resetBtn) {
            resetBtn.addEventListener('click', function () { setZoom(100); });
        }
        if (fitBtn) {
            fitBtn.addEventListener('click', function () { setZoom(100); });
        }
        if (stage) {
            stage.addEventListener('wheel', function (event) {
                if (!event.ctrlKey) {
                    return;
                }
                event.preventDefault();
                setZoom(zoom + (event.deltaY < 0 ? 10 : -10));
            }, { passive: false });
        }
    })();

    /* ---------------------------------------------------------------------
     * 2) Crop / rotate / replace editor
     * ------------------------------------------------------------------- */
    (function initEditor() {
        var modalEl = document.getElementById('photoEditorModal');
        if (!modalEl) {
            return;
        }

        var hasCropper = typeof window.Cropper !== 'undefined';
        var standaloneFallback = document.getElementById('photoStandaloneFallback'); // outside the modal
        var cropperWrap = document.getElementById('photoCropperWrap');
        var toolbar = document.getElementById('photoEditorToolbar');
        var image = document.getElementById('photoEditorImage');
        var preview = document.querySelector('.cropper-preview');
        var originalSrc = image ? image.getAttribute('src') : null;
        var zoomRange = document.getElementById('photoEditorZoom');
        var saveBtn = document.getElementById('photoEditorSave');
        var replaceInput = document.getElementById('photoEditorReplaceInput');
        var resetButtons = document.querySelectorAll('[data-photo-reset]');
        var cropper = null;
        var scaleX = 1;
        var scaleY = 1;
        var objectUrl = null;
        var busy = false;

        function showFallback(reason) {
            if (cropperWrap) cropperWrap.classList.add('d-none');
            if (toolbar) toolbar.classList.add('d-none');
            if (preview) preview.classList.add('d-none');
            if (saveBtn) saveBtn.classList.add('d-none');
            if (standaloneFallback) standaloneFallback.classList.remove('d-none');
            Array.prototype.forEach.call(document.querySelectorAll('[data-photo-editor-open]'), function (button) {
                button.classList.add('d-none');
            });
            if (reason) {
                notify('info', reason);
            }
        }

        if (!hasCropper || !image) {
            showFallback(messages.missingTool);
            return;
        }

        // The inline fallback form is only a no-JS / no-Cropper safety net: hide it when the editor works.
        if (standaloneFallback) {
            standaloneFallback.classList.add('d-none');
        }

        if (config.canEdit === false) {
            return;
        }

        function cropperOptions(previewSelector) {
            var options = {
                viewMode: 1,
                dragMode: 'move',
                aspectRatio: 1,
                autoCropArea: 1,
                responsive: true,
                restore: false,
                guides: false,
                center: true,
                highlight: true,
                background: false,
                modal: true,
                rotatable: true,
                scalable: true,
                zoomable: true,
                checkOrientation: true,
                toggleDragModeOnDblclick: false,
                minContainerHeight: 320,
                minCanvasHeight: 180,
            };
            if (previewSelector) {
                options.preview = previewSelector;
            }
            return options;
        }

        function syncZoomSlider() {
            if (!cropper || !zoomRange) {
                return;
            }
            var data = cropper.getImageData();
            zoomRange.value = data.ratio;
        }

        function initCropper() {
            if (cropper || !image) {
                return;
            }

            try {
                cropper = new window.Cropper(image, cropperOptions(preview ? '.cropper-preview' : null));
            } catch (error) {
                cropper = null;
                if (window.console && window.console.error) {
                    window.console.error('Cropper init failed:', error);
                }
                showFallback('تعذّر تشغيل محرر القص، يمكنك رفع صورة بديلة من النموذج أدناه.');
                return;
            }

            cropper.element.addEventListener('zoom', function (event) {
                if (zoomRange) {
                    zoomRange.value = event.detail.ratio;
                }
            });
        }

        function resetCropperView() {
            if (cropper) {
                cropper.reset();
            }
            scaleX = 1;
            scaleY = 1;
            syncZoomSlider();
        }

        function onEditorShown() {
            if (!hasCropper) {
                return;
            }
            initCropper();
            resetCropperView();
        }

        function destroyCropper() {
            if (cropper) {
                cropper.destroy();
                cropper = null;
            }
            if (objectUrl) {
                URL.revokeObjectURL(objectUrl);
                objectUrl = null;
                // Restore the server-side photo so reopening the editor is not broken.
                if (image && originalSrc) {
                    image.setAttribute('src', originalSrc);
                }
            }
            scaleX = 1;
            scaleY = 1;
            if (zoomRange) {
                zoomRange.value = 1;
            }
        }

        // Bind through jQuery (the same event system Bootstrap 4 uses) so the editor is guaranteed
        // to initialize when the modal finishes opening, and fall back to native listeners otherwise.
        var jqModal = window.jQuery ? window.jQuery(modalEl) : null;
        if (jqModal) {
            jqModal.on('shown.bs.modal', onEditorShown);
            jqModal.on('hidden.bs.modal', destroyCropper);
        } else {
            modalEl.addEventListener('shown.bs.modal', onEditorShown);
            modalEl.addEventListener('hidden.bs.modal', destroyCropper);
        }

        // Safety net: if the "shown" event never fires in the current Bootstrap build,
        // initialize the editor shortly after the open button is clicked.
        Array.prototype.forEach.call(document.querySelectorAll('[data-photo-editor-open]'), function (button) {
            button.addEventListener('click', function () {
                if (!hasCropper) {
                    return;
                }
                window.setTimeout(function () {
                    if (modalEl.classList.contains('show') && !cropper) {
                        initCropper();
                        resetCropperView();
                    }
                }, 350);
            });
        });

        // A broken staged photo (already failed to load) should not leave a dead crop area.
        if (image && image.complete && image.naturalWidth === 0) {
            showFallback('تعذّر تحميل الصورة المرفقة، يمكنك رفع صورة بديلة من النموذج أدناه.');
        } else if (image) {
            image.addEventListener('error', function () {
                if (!cropper) {
                    showFallback('تعذّر تحميل الصورة المرفقة، يمكنك رفع صورة بديلة من النموذج أدناه.');
                }
            });
        }

        var zoomInBtn = document.getElementById('photoEditorZoomIn');
        var zoomOutBtn = document.getElementById('photoEditorZoomOut');
        var rotateLeftBtn = document.getElementById('photoEditorRotateLeft');
        var rotateRightBtn = document.getElementById('photoEditorRotateRight');
        var flipHBtn = document.getElementById('photoEditorFlipH');
        var flipVBtn = document.getElementById('photoEditorFlipV');
        var resetBtnEditor = document.getElementById('photoEditorReset');

        function withCropper(callback) {
            return function () {
                if (!cropper) {
                    return;
                }
                callback();
            };
        }

        if (zoomInBtn) {
            zoomInBtn.addEventListener('click', withCropper(function () { cropper.zoom(0.1); }));
        }
        if (zoomOutBtn) {
            zoomOutBtn.addEventListener('click', withCropper(function () { cropper.zoom(-0.1); }));
        }
        if (rotateLeftBtn) {
            rotateLeftBtn.addEventListener('click', withCropper(function () { cropper.rotate(-90); }));
        }
        if (rotateRightBtn) {
            rotateRightBtn.addEventListener('click', withCropper(function () { cropper.rotate(90); }));
        }
        if (flipHBtn) {
            flipHBtn.addEventListener('click', withCropper(function () {
                scaleX = -scaleX;
                cropper.scaleX(scaleX);
            }));
        }
        if (flipVBtn) {
            flipVBtn.addEventListener('click', withCropper(function () {
                scaleY = -scaleY;
                cropper.scaleY(scaleY);
            }));
        }
        if (resetBtnEditor) {
            resetBtnEditor.addEventListener('click', withCropper(function () {
                cropper.reset();
                scaleX = 1;
                scaleY = 1;
                syncZoomSlider();
            }));
        }
        if (zoomRange) {
            zoomRange.addEventListener('input', withCropper(function () {
                cropper.zoomTo(parseFloat(zoomRange.value));
            }));
        }

        Array.prototype.forEach.call(document.querySelectorAll('[data-photo-aspect]'), function (button) {
            button.addEventListener('click', withCropper(function () {
                var value = button.getAttribute('data-photo-aspect');
                var ratio = value === 'free' ? NaN : parseFloat(value);

                cropper.setAspectRatio(ratio);

                Array.prototype.forEach.call(document.querySelectorAll('[data-photo-aspect]'), function (other) {
                    other.classList.toggle('active', other === button);
                });
            }));
        });

        if (replaceInput) {
            replaceInput.addEventListener('change', function () {
                if (!replaceInput.files || !replaceInput.files[0] || !cropper) {
                    return;
                }
                var label = replaceInput.parentElement ? replaceInput.parentElement.querySelector('.custom-file-label') : null;
                if (label) {
                    label.textContent = replaceInput.files[0].name;
                }
                if (objectUrl) {
                    URL.revokeObjectURL(objectUrl);
                }
                objectUrl = URL.createObjectURL(replaceInput.files[0]);
                cropper.replace(objectUrl);
                scaleX = 1;
                scaleY = 1;
                if (zoomRange) {
                    zoomRange.value = 1;
                }
            });
        }

        function canvasToBlob(canvas) {
            return new Promise(function (resolve, reject) {
                canvas.toBlob(function (blob) {
                    if (blob) {
                        resolve(blob);
                    } else {
                        reject(new Error(messages.emptyCrop));
                    }
                }, 'image/jpeg', 0.9);
            });
        }

        function postPhoto(url, formData) {
            return fetch(url, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: formData,
                credentials: 'same-origin',
            }).then(function (response) {
                return response.json().catch(function () {
                    return { ok: false, message: messages.saveFailed };
                }).then(function (payload) {
                    if (!response.ok || payload.ok === false) {
                        throw new Error(payload.message || messages.saveFailed);
                    }
                    return payload;
                });
            });
        }

        if (saveBtn) {
            saveBtn.addEventListener('click', function () {
                if (!cropper || busy) {
                    return;
                }

                busy = true;
                var originalHtml = saveBtn.innerHTML;
                saveBtn.disabled = true;
                saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin ml-1"></i> ' + messages.saving;

                var canvas = cropper.getCroppedCanvas({
                    maxWidth: 1000,
                    maxHeight: 1000,
                    fillColor: '#ffffff',
                    imageSmoothingEnabled: true,
                    imageSmoothingQuality: 'high',
                });

                if (!canvas) {
                    busy = false;
                    saveBtn.disabled = false;
                    saveBtn.innerHTML = originalHtml;
                    notify('error', messages.emptyCrop);
                    return;
                }

                canvasToBlob(canvas).then(function (blob) {
                    var formData = new FormData();
                    var extension = blob.type === 'image/png' ? 'png' : 'jpg';
                    formData.append('photo', blob, 'edited-photo.' + extension);

                    return postPhoto(config.updateUrl, formData).then(function (payload) {
                        notify('success', payload.message || messages.saved);
                        window.setTimeout(function () { window.location.reload(); }, 700);
                    });
                }).catch(function (error) {
                    busy = false;
                    saveBtn.disabled = false;
                    saveBtn.innerHTML = originalHtml;
                    notify('error', error && error.message ? error.message : messages.saveFailed);
                });
            });
        }

        Array.prototype.forEach.call(resetButtons, function (resetBtn) {
            resetBtn.addEventListener('click', function () {
                if (busy) {
                    return;
                }

                confirmAction(messages.resetConfirm).then(function (confirmed) {
                    if (!confirmed) {
                        return;
                    }

                    busy = true;
                    resetBtn.disabled = true;

                    var formData = new FormData();
                    formData.append('_token', csrfToken);

                    postPhoto(config.resetUrl, formData).then(function (payload) {
                        notify('success', payload.message || messages.resetDone);
                        window.setTimeout(function () { window.location.reload(); }, 700);
                    }).catch(function (error) {
                        busy = false;
                        resetBtn.disabled = false;
                        notify('error', error && error.message ? error.message : messages.resetFailed);
                    });
                });
            });
        });
    })();
})();
