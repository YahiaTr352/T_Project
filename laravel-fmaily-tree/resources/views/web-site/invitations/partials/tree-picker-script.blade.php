<script>
(function () {
    function initInvitationPicker(root) {
        if (!root || root.dataset.ready) return;
        root.dataset.ready = '1';

        const inputName = root.dataset.inputName;
        const flagsUrl = root.dataset.flagsUrl;
        const searchUrl = root.dataset.searchUrl;
        const treeUrl = root.dataset.treeUrl;
        const childrenBase = root.dataset.childrenUrl;
        const treeColumn = root.querySelector('.inv-picker-tree');
        const hiddenBox = root.querySelector('.inv-picker-hidden');
        const chipsBox = root.querySelector('.inv-picker-selected');
        const countBox = root.querySelector('.inv-picker-count');
        const searchInput = root.querySelector('.inv-picker-search');
        const dropdown = root.querySelector('.inv-picker-dropdown');
        const selected = new Map();
        let flags = {};

        try {
            (JSON.parse(root.dataset.selected || '[]') || []).forEach(function (id) {
                selected.set(Number(id), { id: Number(id), first_name: '#' + id });
            });
        } catch (e) {}

        function flagFor(id) {
            return flags[id] || flags[String(id)] || {
                has_whatsapp: false,
                selectable: false,
                whatsapp_descendant_count: 0,
                expandable: false
            };
        }

        function syncInputs() {
            hiddenBox.innerHTML = '';
            selected.forEach(function (person) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = inputName;
                input.value = person.id;
                hiddenBox.appendChild(input);
            });
            countBox.textContent = selected.size + ' محدد';
            chipsBox.innerHTML = '';
            selected.forEach(function (person) {
                const chip = document.createElement('button');
                chip.type = 'button';
                chip.className = 'px-3 py-1 rounded-full bg-emerald-100 text-emerald-800 text-xs font-bold';
                chip.textContent = (person.first_name || person.full_name || '') + ' ×';
                chip.addEventListener('click', function () {
                    selected.delete(person.id);
                    syncInputs();
                    refreshSelectButtons();
                });
                chipsBox.appendChild(chip);
            });
            refreshSelectButtons();
        }

        function toggleSelect(person) {
            const flag = flagFor(person.id);
            if (!flag.selectable) return;
            if (selected.has(person.id)) {
                selected.delete(person.id);
            } else {
                selected.set(person.id, person);
            }
            syncInputs();
        }

        function refreshSelectButtons() {
            root.querySelectorAll('.person-card[data-person-id]').forEach(function (card) {
                const id = Number(card.dataset.personId);
                card.classList.toggle('inv-selected', selected.has(id));
                const btn = card.querySelector('.inv-select-btn');
                if (!btn) return;
                const on = selected.has(id);
                btn.classList.toggle('inv-select-on', on);
                if (!btn.disabled) {
                    btn.innerHTML = on
                        ? '<i class="fas fa-check"></i><span>محدد</span>'
                        : '<i class="fab fa-whatsapp"></i><span>اختيار</span>';
                }
            });
        }

        function createPersonCard(person, level) {
            const hasChildren = person.children_count > 0;
            const isDeceased = !!person.death_date;
            const hasPhoto = !!person.photo_url;
            const iconClass = person.gender === 'female' ? 'fa-female' : 'fa-male';
            const mourningText = isDeceased ? (person.gender === 'female' ? 'رحمها الله' : 'رحمه الله') : '';
            const flag = flagFor(person.id);
            const noWaClass = flag.has_whatsapp ? '' : 'no-whatsapp';
            const selectedClass = selected.has(person.id) ? 'inv-selected' : '';
            const waBadge = flag.has_whatsapp
                ? '<span class="inv-wa-badge"><i class="fab fa-whatsapp"></i></span>'
                : (flag.whatsapp_descendant_count > 0
                    ? '<span class="inv-branch-badge">' + flag.whatsapp_descendant_count + ' في الفرع</span>'
                    : '');

            const photoSection = hasPhoto
                ? `<div class="card-header-section has-photo" style="background-image: url('${person.photo_url}');" data-person-id="${person.id}" data-level="${level}" data-gender="${person.gender || ''}">
                        ${isDeceased ? `<span class="mourning-badge">${mourningText}</span>` : ''}
                        ${waBadge}
                        <div class="person-name-container"><span class="person-name">${person.first_name}</span></div>
                   </div>`
                : `<div class="card-header-section" data-person-id="${person.id}" data-level="${level}" data-gender="${person.gender || ''}">
                        ${isDeceased ? `<span class="mourning-badge">${mourningText}</span>` : ''}
                        ${waBadge}
                        <div class="person-avatar"><i class="fas ${iconClass} avatar-icon"></i></div>
                        <span class="person-name">${person.first_name}</span>
                   </div>`;

            const selectBtn = flag.selectable
                ? `<button type="button" class="action-btn inv-select-btn" data-person-id="${person.id}">
                        <i class="fab fa-whatsapp"></i><span>اختيار</span>
                   </button>`
                : `<button type="button" class="action-btn" disabled title="لا يُضاف — لا يوجد واتساب">
                        <i class="fas fa-ban"></i><span>بلا واتساب</span>
                   </button>`;

                return `
                <div class="tree-item fade-in" data-person-id="${person.id}">
                    <div class="person-card ${isDeceased ? 'is-deceased' : ''} ${noWaClass} ${selectedClass}" data-person-id="${person.id}">
                        ${photoSection}
                        <div class="card-actions">${selectBtn}</div>
                        ${hasChildren ? `<div class="connection-line"></div>` : ''}
                    </div>
                    ${hasChildren ? `<div class="children-container" id="${pickerChildrenId(person.id)}"></div>` : ''}
                </div>
            `;
        }

        function pickerChildrenId(id) {
            return 'inv_children_' + root.id + '_' + id;
        }

        async function fetchJson(url) {
            const response = await fetch(url, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' });
            if (!response.ok) throw new Error('http ' + response.status);
            return response.json();
        }

        function initCardClicks() {
            treeColumn.querySelectorAll('.card-header-section').forEach(function (header) {
                if (header.dataset.bound) return;
                header.dataset.bound = '1';
                header.addEventListener('click', async function () {
                    const personId = this.dataset.personId;
                    const level = parseInt(this.dataset.level, 10) || 0;
                    const treeItem = this.closest('.tree-item');
                    const card = this.closest('.person-card');
                    const childrenContainer = document.getElementById(pickerChildrenId(personId));
                    if (!childrenContainer) return;

                    if (treeItem.classList.contains('expanded')) {
                        treeItem.classList.remove('expanded');
                        card.classList.remove('active');
                        childrenContainer.classList.remove('show');
                        return;
                    }

                    const parentColumn = treeItem.parentElement;
                    parentColumn.querySelectorAll(':scope > .tree-item.expanded').forEach(function (item) {
                        item.classList.remove('expanded');
                        item.querySelector('.person-card')?.classList.remove('active');
                        item.querySelector('.children-container')?.classList.remove('show');
                    });

                    treeItem.classList.add('expanded');
                    card.classList.add('active');

                    if (!childrenContainer.dataset.loaded) {
                        childrenContainer.innerHTML = '<div class="loading-state" style="padding:1rem;"><div class="loading-spinner"></div></div>';
                        childrenContainer.classList.add('show');
                        try {
                            const data = await fetchJson(childrenBase + '/' + personId + '/children');
                            if (data && data.children && data.children.length > 0) {
                                childrenContainer.innerHTML = data.children.map(function (child) {
                                    return createPersonCard(child, level + 1);
                                }).join('');
                                childrenContainer.dataset.loaded = 'true';
                                bindSelectButtons();
                                initCardClicks();
                                refreshSelectButtons();
                            } else if (this.dataset.gender === 'female') {
                                childrenContainer.innerHTML = '';
                                childrenContainer.dataset.loaded = 'true';
                                childrenContainer.classList.remove('show');
                                treeItem.classList.remove('expanded');
                                card.classList.remove('active');
                            } else {
                                childrenContainer.innerHTML = '<div class="empty-state" style="padding:1rem;"><p style="font-size:0.75rem;">لا يوجد أبناء</p></div>';
                                childrenContainer.dataset.loaded = 'true';
                            }
                        } catch (e) {
                            childrenContainer.innerHTML = '<div class="empty-state" style="padding:1rem;"><p style="font-size:0.75rem;">تعذر التحميل</p></div>';
                        }
                    } else {
                        childrenContainer.classList.add('show');
                    }
                });
            });
        }

        function bindSelectButtons() {
            root.querySelectorAll('.inv-select-btn').forEach(function (btn) {
                if (btn.dataset.bound) return;
                btn.dataset.bound = '1';
                btn.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    toggleSelect({
                        id: Number(this.dataset.personId),
                        first_name: this.closest('.person-card')?.querySelector('.person-name')?.textContent || ''
                    });
                });
            });
        }

        async function loadTree() {
            treeColumn.innerHTML = '<div class="loading-state"><div class="loading-spinner"></div></div>';
            try {
                const [treeData, flagData] = await Promise.all([
                    fetchJson(treeUrl),
                    fetchJson(flagsUrl)
                ]);
                flags = flagData.flags || {};
                if (treeData && treeData.tree && treeData.tree.length > 0) {
                    treeColumn.innerHTML = treeData.tree.map(function (person) {
                        return createPersonCard(person, 0);
                    }).join('');
                    bindSelectButtons();
                    initCardClicks();
                    refreshSelectButtons();
                } else {
                    treeColumn.innerHTML = '<div class="empty-state"><p>لا توجد بيانات لعرضها</p></div>';
                }
            } catch (e) {
                treeColumn.innerHTML = '<div class="empty-state"><p>تعذر تحميل الشجرة</p></div>';
            }
        }

        let searchTimer = null;
        searchInput.addEventListener('input', function () {
            const q = searchInput.value.trim();
            clearTimeout(searchTimer);
            if (!q) {
                dropdown.classList.remove('visible');
                dropdown.innerHTML = '';
                return;
            }
            searchTimer = setTimeout(async function () {
                try {
                    const data = await fetchJson(searchUrl + '?q=' + encodeURIComponent(q));
                    const persons = data.persons || [];
                    if (!persons.length) {
                        dropdown.innerHTML = '<div class="search-no-results"><i class="fas fa-search me-2"></i>لا توجد نتائج لـ "' + q + '"</div>';
                        dropdown.classList.add('visible');
                        return;
                    }
                    dropdown.innerHTML = persons.map(function (p) {
                        const avatar = p.photo_url
                            ? '<div class="search-result-avatar"><img src="' + p.photo_url + '" alt=""></div>'
                            : '<div class="search-result-avatar"><i class="fas ' + (p.gender === 'female' ? 'fa-female' : 'fa-male') + '"></i></div>';
                        const name = p.full_name || p.first_name;
                        const hint = p.selectable ? '' : ' · بلا واتساب';
                        return '<div class="search-result-item" data-person-id="' + p.id + '" data-selectable="' + (p.selectable ? '1' : '0') + '" data-name="' + name.replace(/"/g, '&quot;') + '">' +
                            avatar + '<span class="search-result-name">' + name + hint + '</span></div>';
                    }).join('');
                    dropdown.classList.add('visible');
                    dropdown.querySelectorAll('.search-result-item').forEach(function (item) {
                        item.addEventListener('click', function () {
                            const id = Number(this.dataset.personId);
                            if (this.dataset.selectable !== '1') return;
                            toggleSelect({ id: id, first_name: this.dataset.name, full_name: this.dataset.name });
                            dropdown.classList.remove('visible');
                        });
                    });
                } catch (e) {
                    dropdown.innerHTML = '<div class="search-no-results">تعذر البحث</div>';
                    dropdown.classList.add('visible');
                }
            }, 250);
        });

        document.addEventListener('click', function (e) {
            if (!root.contains(e.target)) dropdown.classList.remove('visible');
        });

        syncInputs();
        loadTree();
    }

    document.querySelectorAll('.inv-sila-tree[data-tree-url]').forEach(initInvitationPicker);
})();
</script>
