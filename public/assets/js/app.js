(function () {
    const dropdownState = new Set();

    function createIcons() {
        if (window.lucide) {
            window.lucide.createIcons();
        }
    }

    function rootEl() {
        return document.documentElement;
    }

    function closeAllDropdowns() {
        document.querySelectorAll('[data-dropdown-menu]').forEach((menu) => {
            menu.classList.add('hidden');
        });
        document.querySelectorAll('[data-dropdown-wrap] > button[aria-expanded]').forEach((btn) => {
            btn.setAttribute('aria-expanded', 'false');
            btn.classList.remove('is-open');
        });
        dropdownState.clear();
    }

    function hideLoader() {
        const loader = document.getElementById('appLoader');
        if (!loader) return;
        loader.classList.add('is-hidden');
        setTimeout(() => {
            if (loader && loader.parentNode) {
                loader.style.display = 'none';
            }
        }, 380);
    }

    function showLoader() {
        const loader = document.getElementById('appLoader');
        if (!loader) return;
        loader.style.display = 'flex';
        requestAnimationFrame(() => loader.classList.remove('is-hidden'));
    }

    window.openSidebar = function () {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('mobileOverlay');
        if (sidebar) sidebar.classList.remove('-translate-x-full');
        if (overlay) overlay.classList.remove('hidden');
    };

    window.closeSidebar = function () {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('mobileOverlay');
        if (sidebar && window.innerWidth < 1024) sidebar.classList.add('-translate-x-full');
        if (overlay) overlay.classList.add('hidden');
    };


    function syncMobileNavButton(expanded) {
        const btn = document.getElementById('mobileNavToggle');
        if (btn) btn.setAttribute('aria-expanded', expanded ? 'true' : 'false');
    }

    window.toggleMobileNav = function () {
        const drawer = document.getElementById('mobileNavDrawer');
        const overlay = document.getElementById('mobileNavOverlay');
        if (!drawer || !overlay) return;
        const isOpen = drawer.classList.contains('is-open');
        if (isOpen) {
            window.closeMobileNav();
            return;
        }
        closeAllDropdowns();
        drawer.classList.add('is-open');
        overlay.classList.remove('hidden');
        syncMobileNavButton(true);
        document.body.classList.add('app-lock-scroll');
        drawer.setAttribute('aria-hidden', 'false');
    };

    window.closeMobileNav = function () {
        const drawer = document.getElementById('mobileNavDrawer');
        const overlay = document.getElementById('mobileNavOverlay');
        if (drawer) {
            drawer.classList.remove('is-open');
            drawer.setAttribute('aria-hidden', 'true');
        }
        if (overlay) overlay.classList.add('hidden');
        syncMobileNavButton(false);
        document.body.classList.remove('app-lock-scroll');
    };

    window.toggleTheme = function () {
        const root = rootEl();
        const isDark = root.classList.toggle('dark');
        localStorage.setItem('theme', isDark ? 'dark' : 'light');
    };

    window.toggleSidebarCollapse = function () {
        const root = rootEl();
        const collapsed = root.classList.toggle('sidebar-collapsed');
        localStorage.setItem('sidebar-collapsed', collapsed ? '1' : '0');
    };

    window.toggleDropdown = function (id, trigger) {
        const menu = document.getElementById(id);
        if (!menu) return;
        const isHidden = menu.classList.contains('hidden');
        closeAllDropdowns();
        if (isHidden) {
            menu.classList.remove('hidden');
            if (trigger) {
                trigger.setAttribute('aria-expanded', 'true');
                trigger.classList.add('is-open');
            }
            dropdownState.add(id);
        }
    };

    window.dismissToast = function (id) {
        const toast = document.getElementById(id);
        if (toast) {
            toast.classList.add('opacity-0', 'translate-y-2');
            setTimeout(() => toast.remove(), 220);
        }
    };

    function autoDismissToasts() {
        document.querySelectorAll('[data-auto-dismiss]').forEach((toast) => {
            const delay = Number(toast.getAttribute('data-auto-dismiss')) || 4500;
            setTimeout(() => {
                if (toast && document.body.contains(toast)) {
                    toast.classList.add('opacity-0', 'translate-y-2');
                    setTimeout(() => toast.remove(), 220);
                }
            }, delay);
        });
    }



    function bindDropdownHover() {
        document.querySelectorAll('[data-dropdown-wrap]').forEach((wrap) => {
            const button = wrap.querySelector('button[aria-controls]');
            const menu = wrap.querySelector('[data-dropdown-menu]');
            if (!button || !menu) return;
            let timer = null;
            const openMenu = () => {
                if (window.innerWidth < 901) return;
                if (timer) clearTimeout(timer);
                closeAllDropdowns();
                menu.classList.remove('hidden');
                button.setAttribute('aria-expanded', 'true');
                button.classList.add('is-open');
            };
            const delayedClose = () => {
                if (window.innerWidth < 901) return;
                timer = setTimeout(() => {
                    menu.classList.add('hidden');
                    button.setAttribute('aria-expanded', 'false');
                    button.classList.remove('is-open');
                }, 180);
            };
            wrap.addEventListener('mouseenter', openMenu);
            wrap.addEventListener('mouseleave', delayedClose);
            menu.addEventListener('mouseenter', () => { if (timer) clearTimeout(timer); });
            menu.addEventListener('mouseleave', delayedClose);
        });
    }

    function enhanceTables() {
        document.querySelectorAll('main table').forEach((table) => {
            if (table.closest('.app-table-ignore')) return;
            if (!table.classList.contains('app-responsive-table')) {
                table.classList.add('app-responsive-table');
            }

            const thead = table.querySelector('thead');
            const headers = [];
            if (thead) {
                const headerCells = thead.querySelectorAll('th');
                headerCells.forEach((th, index) => {
                    headers[index] = (th.textContent || '').trim() || `Column ${index + 1}`;
                });
            }

            table.querySelectorAll('tbody tr').forEach((row) => {
                row.querySelectorAll('td').forEach((td, index) => {
                    if (td.hasAttribute('colspan') && Number(td.getAttribute('colspan')) > 1) return;
                    if (!td.dataset.label) {
                        td.dataset.label = headers[index] || `Column ${index + 1}`;
                    }
                });
            });

            const parent = table.parentElement;
            if (!parent || !parent.classList.contains('app-table-wrap')) {
                const wrap = document.createElement('div');
                wrap.className = 'app-table-wrap';
                table.parentNode.insertBefore(wrap, table);
                wrap.appendChild(table);
            }
        });
    }

    function enhanceLegacyLayouts() {
        document.querySelectorAll('main p > a, main div > a, main td > a, main form button, main form input[type="submit"]').forEach((el) => {
            const text = (el.textContent || el.value || '').trim().toLowerCase();
            if (!text) return;
            if (text.includes('edit') || text.includes('view') || text.includes('open') || text.includes('dashboard') || text.includes('create') || text.includes('add') || text.includes('filter') || text.includes('reset') || text.includes('submit') || text.includes('save') || text.includes('upload') || text.includes('print') || text.includes('restore') || text.includes('download')) {
                el.style.maxWidth = '100%';
            }
        });

        document.querySelectorAll('main table td').forEach((td) => {
            const forms = td.querySelectorAll('form');
            if (forms.length > 0 && !td.querySelector('.table-actions')) {
                td.classList.add('has-inline-form');
            }
        });
    }

    function applyAutoBadges() {
        const badgeMap = {
            active: 'app-badge app-badge-emerald',
            inactive: 'app-badge app-badge-rose',
            approved: 'app-badge app-badge-emerald',
            archived: 'app-badge app-badge-slate',
            draft: 'app-badge app-badge-amber',
            submitted: 'app-badge app-badge-blue',
            'in review': 'app-badge app-badge-blue',
            in_review: 'app-badge app-badge-blue',
            pending: 'app-badge app-badge-amber',
            completed: 'app-badge app-badge-emerald',
            cancelled: 'app-badge app-badge-slate',
            rejected: 'app-badge app-badge-rose',
            public: 'app-badge app-badge-sky',
            internal: 'app-badge app-badge-slate',
            confidential: 'app-badge app-badge-amber',
            restricted: 'app-badge app-badge-rose',
            read: 'app-badge app-badge-slate',
            unread: 'app-badge app-badge-blue',
            returned: 'app-badge app-badge-rose',
            forwarded: 'app-badge app-badge-sky'
        };

        document.querySelectorAll('main table td').forEach((cell) => {
            if (cell.querySelector('.app-badge') || cell.children.length > 0) return;
            const rawText = cell.textContent.trim();
            if (!rawText) return;
            const key = rawText.toLowerCase().replace(/\s+/g, ' ');
            if (!badgeMap[key]) return;
            const span = document.createElement('span');
            span.className = badgeMap[key];
            span.textContent = rawText.replace(/_/g, ' ');
            cell.textContent = '';
            cell.appendChild(span);
        });
    }

    function bindPasswordButtons() {
        document.querySelectorAll('[data-password-toggle-target]').forEach((button) => {
            button.addEventListener('click', function () {
                const input = document.getElementById(button.getAttribute('data-password-toggle-target'));
                if (!input) return;
                input.type = input.type === 'password' ? 'text' : 'password';
                button.setAttribute('aria-pressed', input.type === 'text' ? 'true' : 'false');
            });
        });
    }

    function bindModals() {
        document.querySelectorAll('[data-modal-target]').forEach((trigger) => {
            trigger.addEventListener('click', function () {
                const target = document.getElementById(trigger.getAttribute('data-modal-target'));
                if (target) target.classList.add('is-open');
            });
        });

        document.querySelectorAll('[data-modal-close]').forEach((closer) => {
            closer.addEventListener('click', function () {
                const target = closer.closest('.app-modal-backdrop');
                if (target) target.classList.remove('is-open');
            });
        });
    }

    function bindImagePreviews() {
        document.querySelectorAll('[data-image-input-preview]').forEach((input) => {
            input.addEventListener('change', function () {
                const previewId = input.getAttribute('data-image-input-preview');
                const preview = document.getElementById(previewId);
                const file = input.files && input.files[0];
                if (!preview || !file) return;
                const url = URL.createObjectURL(file);
                preview.src = url;
                preview.onload = () => URL.revokeObjectURL(url);
            });
        });
    }

    function bindDropzones() {
        document.querySelectorAll('[data-dropzone]').forEach((dropzone) => {
            const inputId = dropzone.getAttribute('data-dropzone');
            const input = document.getElementById(inputId);
            const fileNameTarget = dropzone.querySelector('[data-file-name]');
            const fileMetaTarget = dropzone.querySelector('[data-file-meta]');
            const previewWrapper = dropzone.querySelector('[data-file-preview-wrapper]');
            const previewImage = dropzone.querySelector('[data-file-preview-image]');
            const previewFallback = dropzone.querySelector('[data-file-preview-fallback]');
            if (!input) return;

            const updateLabel = () => {
                const file = input.files && input.files[0];
                if (!file) {
                    if (previewWrapper) previewWrapper.hidden = true;
                    if (previewImage) {
                        previewImage.classList.add('hidden');
                        previewImage.removeAttribute('src');
                    }
                    if (previewFallback) previewFallback.classList.remove('hidden');
                    return;
                }
                if (fileNameTarget) fileNameTarget.textContent = file.name;
                if (fileMetaTarget) fileMetaTarget.textContent = `${Math.max(1, Math.round(file.size / 1024))} KB`;

                if (previewWrapper) previewWrapper.hidden = false;
                const isImage = /^image\//.test(file.type || '');
                if (isImage && previewImage) {
                    const url = URL.createObjectURL(file);
                    previewImage.src = url;
                    previewImage.classList.remove('hidden');
                    previewImage.onload = () => URL.revokeObjectURL(url);
                    if (previewFallback) previewFallback.classList.add('hidden');
                } else {
                    if (previewImage) {
                        previewImage.classList.add('hidden');
                        previewImage.removeAttribute('src');
                    }
                    if (previewFallback) previewFallback.classList.remove('hidden');
                }
            };

            ['dragenter', 'dragover'].forEach((evt) => {
                dropzone.addEventListener(evt, (e) => {
                    e.preventDefault();
                    dropzone.classList.add('is-dragover');
                });
            });

            ['dragleave', 'drop'].forEach((evt) => {
                dropzone.addEventListener(evt, (e) => {
                    e.preventDefault();
                    dropzone.classList.remove('is-dragover');
                });
            });

            dropzone.addEventListener('drop', (e) => {
                if (!e.dataTransfer || !e.dataTransfer.files || !e.dataTransfer.files.length) return;
                input.files = e.dataTransfer.files;
                updateLabel();
            });

            input.addEventListener('change', updateLabel);
        });
    }

    function bindHouseholdAutocomplete() {
        const forms = document.querySelectorAll('[data-household-autocomplete-form]');
        forms.forEach((form) => {
            if (form.dataset.autocompleteBound === '1') return;
            form.dataset.autocompleteBound = '1';
            const input = form.querySelector('[data-household-autocomplete-input]');
            if (!input) return;

            const minChars = Number(input.getAttribute('data-autocomplete-min') || '1');
            const panel = document.createElement('div');
            panel.className = 'app-search-suggest hidden';
            panel.setAttribute('role', 'listbox');
            form.appendChild(panel);

            let items = [];
            let activeIndex = -1;
            let controller = null;
            let debounceTimer = null;

            const escapeHtml = (value) => String(value || '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');

            const closePanel = () => {
                panel.classList.add('hidden');
                panel.innerHTML = '';
                items = [];
                activeIndex = -1;
            };

            const selectItem = (item) => {
                input.value = item.household_head_name || item.household_code || item.contact_number || '';
                closePanel();
                if (item.household_id) {
                    window.location.href = '/harvest/modules/agri/households/view.php?id=' + encodeURIComponent(item.household_id);
                } else {
                    form.submit();
                }
            };

            const renderItems = () => {
                if (!items.length) {
                    panel.innerHTML = '<div class="app-search-suggest-empty">No matching family found yet.</div>';
                    panel.classList.remove('hidden');
                    return;
                }
                panel.innerHTML = items.map((item, index) => {
                    const name = item.household_head_name || 'Unnamed family';
                    const code = item.household_code || '';
                    const meta = [item.barangay_name || '', item.contact_number || 'No contact'].filter(Boolean).join(' · ');
                    const memberLine = item.matched_member_name ? `${item.matched_member_name}${item.relationship_to_head ? ' · ' + item.relationship_to_head : ''}` : (item.member_preview || '');
                    const photo = item.photo_url || '/harvest/public/assets/img/image.jpg';
                    const count = Number(item.member_count || 0);
                    return `
                        <button type="button" class="app-search-suggest-item ${index === activeIndex ? 'is-active' : ''}" data-index="${index}">
                            <img src="${escapeHtml(photo)}" alt="Family" class="app-search-suggest-avatar">
                            <span>
                                <span class="app-search-suggest-title">${escapeHtml(name)}</span>
                                <span class="app-search-suggest-meta">${escapeHtml(meta)}${count ? ' · ' + count + ' member(s)' : ''}</span>${memberLine ? `<span class="app-search-suggest-meta">${escapeHtml(memberLine)}</span>` : ''}
                            </span>
                            <span class="app-search-suggest-code">${escapeHtml(code)}</span>
                        </button>
                    `;
                }).join('');
                panel.classList.remove('hidden');
                panel.querySelectorAll('[data-index]').forEach((button) => {
                    button.addEventListener('click', function () {
                        const picked = items[Number(button.getAttribute('data-index'))];
                        if (picked) selectItem(picked);
                    });
                });
            };

            const fetchMatches = (query) => {
                if (controller) controller.abort();
                controller = new AbortController();
                fetch('/harvest/modules/api/household_lookup.php?q=' + encodeURIComponent(query), { signal: controller.signal, credentials: 'same-origin' })
                    .then((response) => response.ok ? response.json() : Promise.reject(new Error('Request failed')))
                    .then((payload) => {
                        items = Array.isArray(payload.results) ? payload.results : [];
                        activeIndex = items.length ? 0 : -1;
                        renderItems();
                    })
                    .catch((error) => {
                        if (error.name === 'AbortError') return;
                        closePanel();
                    });
            };

            input.addEventListener('input', function () {
                const query = input.value.trim();
                if (debounceTimer) window.clearTimeout(debounceTimer);
                if (query.length < minChars) {
                    closePanel();
                    return;
                }
                debounceTimer = window.setTimeout(() => fetchMatches(query), 180);
            });

            input.addEventListener('focus', function () {
                const query = input.value.trim();
                if (query.length >= minChars) fetchMatches(query);
            });

            input.addEventListener('keydown', function (event) {
                if (panel.classList.contains('hidden') || !items.length) return;
                if (event.key === 'ArrowDown') {
                    event.preventDefault();
                    activeIndex = (activeIndex + 1) % items.length;
                    renderItems();
                } else if (event.key === 'ArrowUp') {
                    event.preventDefault();
                    activeIndex = (activeIndex - 1 + items.length) % items.length;
                    renderItems();
                } else if (event.key === 'Enter' && activeIndex >= 0) {
                    event.preventDefault();
                    selectItem(items[activeIndex]);
                } else if (event.key === 'Escape') {
                    closePanel();
                }
            });

            document.addEventListener('click', function (event) {
                if (!form.contains(event.target)) closePanel();
            });
        });
    }

    function bindLoaderEvents() {
        window.addEventListener('pageshow', hideLoader);
        window.addEventListener('load', hideLoader);

        document.querySelectorAll('form').forEach((form) => {
            form.addEventListener('submit', function () {
                const target = form.getAttribute('target');
                if (target && target.toLowerCase() === '_blank') return;
                showLoader();
            });
        });

        document.querySelectorAll('a.app-nav-link, a.app-mobile-nav-link').forEach((link) => {
            link.addEventListener('click', function (event) {
                const href = link.getAttribute('href') || '';
                if (!href || href.startsWith('#') || link.hasAttribute('download')) return;
                if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
                showLoader();
            });
        });
    }

    document.addEventListener('click', function (event) {
        if (!event.target.closest('[data-dropdown-wrap]')) {
            closeAllDropdowns();
        }

        const mobileDrawer = document.getElementById('mobileNavDrawer');
        if (mobileDrawer && mobileDrawer.classList.contains('is-open') && !event.target.closest('#mobileNavDrawer') && !event.target.closest('#mobileNavToggle')) {
            window.closeMobileNav();
        }

        const backdrop = event.target.closest('.app-modal-backdrop');
        if (backdrop && event.target === backdrop) {
            backdrop.classList.remove('is-open');
        }
    });

    window.addEventListener('resize', function () {
        if (window.innerWidth >= 768) {
            window.closeMobileNav();
        }
        if (window.innerWidth >= 1024) {
            const overlay = document.getElementById('mobileOverlay');
            if (overlay) overlay.classList.add('hidden');
            const sidebar = document.getElementById('sidebar');
            if (sidebar) sidebar.classList.remove('-translate-x-full');
        }
        bindDropdownHover();
        enhanceTables();
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeAllDropdowns();
            window.closeMobileNav();
        }
    });

    document.addEventListener('DOMContentLoaded', function () {
        createIcons();
        bindDropdownHover();
        enhanceTables();
        enhanceLegacyLayouts();
        autoDismissToasts();
        applyAutoBadges();
        bindPasswordButtons();
        bindModals();
        bindImagePreviews();
        bindDropzones();
        bindLoaderEvents();
        bindHouseholdAutocomplete();
        setTimeout(hideLoader, 350);
    });
})();

/* HARVEST UI SYSTEM UPDATE v2: non-breaking enhancement layer */
(function () {
  function ready(fn) {
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', fn);
    else fn();
  }

  function normalizeActions() {
    document.querySelectorAll('main td, main .flex, main .inline-flex').forEach(function (box) {
      var actions = Array.from(box.children || []).filter(function (el) {
        if (!el.matches) return false;
        return el.matches('a,button,form') && /edit|view|open|delete|archive|restore|print|download|save|submit|cancel|scan|approve|return/i.test(el.textContent || el.value || '');
      });
      if (actions.length >= 2) box.classList.add('app-action-row');
    });
  }

  function markReportPages() {
    var path = location.pathname.toLowerCase();
    if (path.indexOf('/reports/') !== -1 || path.indexOf('/print') !== -1 || /report|summary/i.test(document.title)) {
      document.documentElement.classList.add('app-report-mode');
      document.querySelectorAll('main section, main .page-card, main .app-panel').forEach(function (el) {
        el.setAttribute('data-report-card', 'true');
      });
    }
  }

  function addReadableTitles() {
    document.querySelectorAll('main h1, main h2, main h3, main td, main th, main .truncate').forEach(function (el) {
      var text = (el.textContent || '').replace(/\s+/g, ' ').trim();
      if (text && text.length > 18 && !el.getAttribute('title')) el.setAttribute('title', text);
    });
  }

  function improveForms() {
    document.querySelectorAll('main form').forEach(function (form) {
      form.classList.add('app-form-polished');
    });
    document.querySelectorAll('main input, main select, main textarea').forEach(function (field) {
      if (!field.getAttribute('autocomplete') && field.name && !/password|token|csrf/i.test(field.name)) {
        field.setAttribute('autocomplete', 'off');
      }
    });
  }

  function enhanceCards() {
    document.querySelectorAll('main section, main .rounded-3xl, main .rounded-\[2rem\], main .dashboard-stat').forEach(function (card) {
      if (!card.classList.contains('app-ui-polished')) card.classList.add('app-ui-polished');
    });
  }

  function run() {
    document.documentElement.classList.add('harvest-ui-v2');
    normalizeActions();
    markReportPages();
    addReadableTitles();
    improveForms();
    enhanceCards();
    if (window.lucide) window.lucide.createIcons();
  }

  ready(function () {
    run();
    setTimeout(run, 300);
  });
})();
