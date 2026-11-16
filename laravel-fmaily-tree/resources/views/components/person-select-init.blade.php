@once
@push('styles')
<style>
    /* =========================================================
       Select2 Custom Result & Selection Styling
       ========================================================= */
    .select2-results__option .person-result-item {
        display: flex;
        align-items: center;
        padding: 6px 4px;
        gap: 12px;
        transition: background 0.15s ease;
    }
    .select2-results__option .person-result-avatar {
        width: 42px;
        height: 42px;
        border-radius: 50%;
        object-fit: cover;
        flex-shrink: 0;
        border: 2px solid rgba(255, 255, 255, 0.15);
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15);
    }
    .select2-results__option .person-result-info {
        flex: 1;
        min-width: 0;
    }
    .select2-results__option .person-result-name {
        font-weight: 600;
        font-size: 0.95rem;
        color: #e2e8f0;
        margin-bottom: 2px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    body:not(.dark) .select2-results__option .person-result-name {
        color: #1e293b;
    }
    .select2-results__option .person-result-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        align-items: center;
        font-size: 0.78rem;
    }
    .select2-results__option--highlighted .person-result-name {
        color: #ffffff !important;
    }

    /* Selection Box Avatar */
    .person-selection-item {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        font-weight: 600;
    }
    .person-selection-avatar {
        width: 24px;
        height: 24px;
        border-radius: 50%;
        object-fit: cover;
        border: 1px solid rgba(255, 255, 255, 0.3);
    }

    /* =========================================================
       Live Person Preview Cards
       ========================================================= */
    .person-preview-card {
        display: none;
        background: linear-gradient(135deg, rgba(30, 41, 59, 0.8) 0%, rgba(15, 23, 42, 0.9) 100%);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 12px;
        padding: 14px 16px;
        margin-top: 10px;
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
        animation: previewFadeIn 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        position: relative;
    }
    body:not(.dark) .person-preview-card {
        background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        border: 1px solid #e2e8f0;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    }

    @keyframes previewFadeIn {
        from {
            opacity: 0;
            transform: translateY(-6px) scale(0.98);
        }
        to {
            opacity: 1;
            transform: translateY(0) scale(1);
        }
    }

    .person-preview-card .preview-body {
        display: flex;
        align-items: center;
        gap: 14px;
    }
    .person-preview-card .preview-avatar {
        width: 56px;
        height: 56px;
        border-radius: 50%;
        object-fit: cover;
        border: 3px solid #3b82f6;
        box-shadow: 0 4px 10px rgba(59, 130, 246, 0.3);
        flex-shrink: 0;
    }
    .person-preview-card.female .preview-avatar {
        border-color: #ec4899;
        box-shadow: 0 4px 10px rgba(236, 72, 153, 0.3);
    }
    .person-preview-card .preview-details {
        flex: 1;
        min-width: 0;
    }
    .person-preview-card .preview-name {
        font-weight: 700;
        font-size: 1rem;
        color: #f8fafc;
        margin-bottom: 4px;
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }
    body:not(.dark) .person-preview-card .preview-name {
        color: #0f172a;
    }
    .person-preview-card .preview-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 8px 12px;
        font-size: 0.82rem;
        color: #94a3b8;
    }
    body:not(.dark) .person-preview-card .preview-meta {
        color: #64748b;
    }
    .person-preview-card .preview-meta-item {
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }

    /* Interactive Couple Visual Connector */
    .marriage-couple-connector {
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 10px 0;
        position: relative;
    }
    .marriage-connector-line {
        position: absolute;
        top: 50%;
        left: 10%;
        right: 10%;
        height: 2px;
        background: linear-gradient(90deg, #3b82f6, #ec4899);
        z-index: 1;
        opacity: 0.4;
    }
    .marriage-connector-badge {
        position: relative;
        z-index: 2;
        background: #1e293b;
        border: 2px solid rgba(255, 255, 255, 0.15);
        color: #fbbf24;
        width: 46px;
        height: 46px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    body:not(.dark) .marriage-connector-badge {
        background: #ffffff;
        border-color: #cbd5e1;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
    }
    .marriage-connector-badge.active {
        transform: scale(1.1);
        border-color: #f59e0b;
        box-shadow: 0 0 20px rgba(245, 158, 11, 0.5);
        animation: pulseHeart 1.5s infinite;
    }

    @keyframes pulseHeart {
        0% { transform: scale(1); }
        50% { transform: scale(1.12); }
        100% { transform: scale(1); }
    }
</style>
@endpush

@push('scripts')
<script>
    /**
     * Unified Person Select2 Initializer with Custom Styling and Live Previews.
     */
    function initPersonSelect2(selector, options) {
        options = options || {};
        const $el = $(selector);
        if (!$el.length) return;

        const defaultPlaceholder = $el.data('placeholder') || options.placeholder || 'ابحث بالاسم الكامل أو النسب...';
        const searchUrl = options.url || "{{ route('persons.search') }}";
        const parentModal = options.parentModal || undefined;

        $el.select2({
            theme: 'bootstrap4',
            width: '100%',
            dropdownParent: parentModal ? $(parentModal) : undefined,
            placeholder: defaultPlaceholder,
            allowClear: options.allowClear !== false,
            minimumInputLength: 0,
            ajax: {
                url: searchUrl,
                dataType: 'json',
                delay: 250,
                data: function(params) {
                    const query = {
                        term: params.term || '',
                        page: params.page || 1
                    };

                    // Dynamic gender filtering
                    if (typeof options.gender === 'function') {
                        const g = options.gender();
                        if (g) query.gender = g;
                    } else if (options.gender) {
                        query.gender = options.gender;
                    } else if ($el.data('gender')) {
                        query.gender = $el.data('gender');
                    }

                    // Dynamic exclude_id
                    if (typeof options.excludeId === 'function') {
                        const ex = options.excludeId();
                        if (ex) query.exclude_id = ex;
                    } else if (options.excludeId) {
                        query.exclude_id = options.excludeId;
                    } else if ($el.data('exclude-id')) {
                        query.exclude_id = $el.data('exclude-id');
                    }

                    // include_outside
                    if (typeof options.includeOutside !== 'undefined') {
                        query.include_outside = options.includeOutside ? 1 : 0;
                    } else if ($el.data('include-outside') !== undefined) {
                        query.include_outside = $el.data('include-outside') ? 1 : 0;
                    }

                    return query;
                },
                processResults: function(data) {
                    return {
                        results: data.results || []
                    };
                },
                cache: true
            },
            templateResult: function(person) {
                if (!person.id) {
                    return person.text;
                }

                const avatar = person.avatar || person.photo_url || (person.gender === 'female' 
                    ? "{{ asset('assets/img/avatar-female-hejap-1.png') }}" 
                    : "{{ asset('assets/img/avatar-male.png') }}");

                const isMale = person.gender === 'male';
                const genderIcon = isMale ? '<i class="fas fa-mars text-primary"></i> ذكر' : '<i class="fas fa-venus text-danger"></i> أنثى';
                const genderBadgeClass = isMale ? 'badge-primary' : 'badge-danger';
                
                let badges = '';
                badges += `<span class="badge ${genderBadgeClass} px-2 py-1">${genderIcon}</span>`;

                if (person.age) {
                    badges += `<span class="badge badge-info px-2 py-1"><i class="fas fa-birthday-cake mr-1"></i> ${person.age} سنة</span>`;
                }

                if (person.from_outside_the_family) {
                    badges += `<span class="badge badge-warning px-2 py-1"><i class="fas fa-external-link-alt mr-1"></i> خارج العائلة</span>`;
                }

                if (person.is_alive === false) {
                    badges += `<span class="badge badge-secondary px-2 py-1"><i class="fas fa-ribbon mr-1"></i> متوفى</span>`;
                }

                let locationInfo = '';
                if (person.location) {
                    locationInfo = `<span class="text-muted mr-2"><i class="fas fa-map-marker-alt text-muted mr-1"></i>${person.location}</span>`;
                }

                return $(`
                    <div class="person-result-item">
                        <img src="${avatar}" alt="${person.full_name || person.text}" class="person-result-avatar">
                        <div class="person-result-info">
                            <div class="person-result-name">${person.full_name || person.text}</div>
                            <div class="person-result-meta">
                                ${badges}
                                ${locationInfo}
                            </div>
                        </div>
                    </div>
                `);
            },
            templateSelection: function(person) {
                if (!person.id) {
                    return person.text;
                }
                const name = person.full_name || person.text;
                const avatar = person.avatar || person.photo_url;
                if (avatar) {
                    return $(`
                        <span class="person-selection-item">
                            <img src="${avatar}" class="person-selection-avatar" alt="${name}">
                            <span>${name}</span>
                        </span>
                    `);
                }
                return name;
            },
            escapeMarkup: function(markup) {
                return markup;
            }
        });

        // Event listener for select and clear
        $el.on('select2:select', function(e) {
            const data = e.params.data;
            if (options.previewContainer) {
                renderPersonPreviewCard(options.previewContainer, data);
            }
            if (typeof options.onSelect === 'function') {
                options.onSelect(data, $el);
            }
        });

        $el.on('select2:clear', function() {
            if (options.previewContainer) {
                $(options.previewContainer).fadeOut(200, function() {
                    $(this).empty();
                });
            }
            if (typeof options.onClear === 'function') {
                options.onClear($el);
            }
        });

        // Preload initial person data if available
        const initialId = $el.data('initial-id');
        if (initialId && !$el.find(`option[value="${initialId}"]`).length) {
            $.ajax({
                url: searchUrl,
                dataType: 'json',
                data: { id: initialId },
                success: function(response) {
                    if (response.results && response.results.length > 0) {
                        const person = response.results[0];
                        const option = new Option(person.full_name || person.text, person.id, true, true);
                        $(option).data('data', person);
                        $el.append(option).trigger('change');
                        
                        if (options.previewContainer) {
                            renderPersonPreviewCard(options.previewContainer, person);
                        }
                    }
                }
            });
        }
    }

    /**
     * Render the Live Person Preview Card with animated reveal.
     */
    function renderPersonPreviewCard(containerSelector, person) {
        const $container = $(containerSelector);
        if (!$container.length || !person || !person.id) return;

        const isFemale = person.gender === 'female';
        const avatar = person.avatar || person.photo_url || (isFemale 
            ? "{{ asset('assets/img/avatar-female-hejap-1.png') }}" 
            : "{{ asset('assets/img/avatar-male.png') }}");

        const genderLabel = isFemale ? 'أنثى' : 'ذكر';
        const genderBadgeClass = isFemale ? 'badge-danger' : 'badge-primary';
        const genderIcon = isFemale ? 'fa-venus' : 'fa-mars';

        let metaHtml = '';
        if (person.age) {
            metaHtml += `<div class="preview-meta-item"><i class="fas fa-birthday-cake text-info"></i> <span>${person.age} سنة</span></div>`;
        }
        if (person.birth_date) {
            metaHtml += `<div class="preview-meta-item"><i class="fas fa-calendar-alt text-muted"></i> <span>الميلاد: ${person.birth_date}</span></div>`;
        }
        if (person.location) {
            metaHtml += `<div class="preview-meta-item"><i class="fas fa-map-marker-alt text-danger"></i> <span>${person.location}</span></div>`;
        }
        if (person.from_outside_the_family) {
            metaHtml += `<div class="preview-meta-item"><span class="badge badge-warning"><i class="fas fa-external-link-alt"></i> خارج العائلة</span></div>`;
        }
        if (person.occupation) {
            metaHtml += `<div class="preview-meta-item"><i class="fas fa-briefcase text-primary"></i> <span>${person.occupation}</span></div>`;
        }

        const cardHtml = `
            <div class="preview-body">
                <img src="${avatar}" alt="${person.full_name || person.text}" class="preview-avatar">
                <div class="preview-details">
                    <div class="preview-name">
                        <span>${person.full_name || person.text}</span>
                        <span class="badge ${genderBadgeClass} font-weight-normal py-1 px-2">
                            <i class="fas ${genderIcon}"></i> ${genderLabel}
                        </span>
                    </div>
                    <div class="preview-meta">
                        ${metaHtml}
                    </div>
                </div>
            </div>
        `;

        $container.html(cardHtml).removeClass('female male').addClass(isFemale ? 'female' : 'male').fadeIn(250);
    }
</script>
@endpush
@endonce
