/* ============================================
   HAMBURGER MENU
===============================================*/
const hamburgerBtn = document.getElementById('nav-hamburger');
const mobileMenu = document.getElementById('nav-mobile-menu');
const modalOverlay = document.getElementById('modal-overlay');

function toggleMobileMenu() {
    if (!mobileMenu) return;

    const isOpen = mobileMenu.classList.toggle('open');
    if (hamburgerBtn) {
        hamburgerBtn.classList.toggle('open', isOpen);
        hamburgerBtn.setAttribute('aria-expanded', String(isOpen));
    }

    document.body.style.overflow = isOpen ? 'hidden' : '';
}

function closeMobileMenu() {
    if (!mobileMenu) return;

    mobileMenu.classList.remove('open');
    if (hamburgerBtn) {
        hamburgerBtn.classList.remove('open');
        hamburgerBtn.setAttribute('aria-expanded', 'false');
    }

    if (!modalOverlay || !modalOverlay.classList.contains('open')) {
        document.body.style.overflow = '';
    }
}

document.addEventListener('click', function (event) {
    if (!mobileMenu || !hamburgerBtn) return;

    if (
        mobileMenu.classList.contains('open') &&
        !mobileMenu.contains(event.target) &&
        !hamburgerBtn.contains(event.target)
    ) {
        closeMobileMenu();
    }
});

window.addEventListener('resize', function () {
    closeMobileMenu();
});

/* ============================================
   AUTH MODAL WINDOW
============================================ */
function openModal(tab) {
    if (!modalOverlay) return;

    closeMobileMenu();
    modalOverlay.classList.add('open');
    document.body.style.overflow = 'hidden';
    switchTab(tab || 'login');

    const visibleForm = document.getElementById(tab === 'register' ? 'modal-form-register' : 'modal-form-login');
    const firstInput = visibleForm?.querySelector('input, select, textarea');
    if (firstInput) {
        setTimeout(() => firstInput.focus(), 60);
    }
}

function closeModal() {
    if (!modalOverlay) return;

    modalOverlay.classList.remove('open');
    document.body.style.overflow = '';

    const openBtn = document.getElementById('btn-open-modal');
    if (openBtn) {
        openBtn.focus();
    }
}

function switchTab(tab) {
    const isLogin = tab === 'login';
    const formLogin = document.getElementById('modal-form-login');
    const formRegister = document.getElementById('modal-form-register');
    const tabLogin = document.getElementById('tab-login');
    const tabRegister = document.getElementById('tab-register');
    const title = document.getElementById('modal-title');
    const sub = document.getElementById('modal-sub');

    if (!formLogin || !formRegister) return;

    formLogin.style.display = isLogin ? 'block' : 'none';
    formRegister.style.display = isLogin ? 'none' : 'block';

    if (tabLogin) {
        tabLogin.classList.toggle('active', isLogin);
        tabLogin.setAttribute('aria-selected', String(isLogin));
    }

    if (tabRegister) {
        tabRegister.classList.toggle('active', !isLogin);
        tabRegister.setAttribute('aria-selected', String(!isLogin));
    }

    if (title) {
        title.textContent = isLogin ? 'Vítej zpět' : 'Připoj se k nám';
    }

    if (sub) {
        sub.textContent = isLogin
            ? 'Přihlas se ke svému účtu komunity.'
            : 'Vytvoř si účet a staň se součástí komunity.';
    }

    if (!isLogin) {
        syncRegisterFacultyVisibility();
    }
}

function restoreAuthModalState() {
    if (!modalOverlay) return;

    const shouldOpen = modalOverlay.getAttribute('data-auto-open') === '1';
    if (!shouldOpen) return;

    const tab = modalOverlay.getAttribute('data-auth-tab') || 'login';
    openModal(tab);
    modalOverlay.setAttribute('data-auto-open', '0');
}

if (modalOverlay) {
    modalOverlay.addEventListener('click', function (event) {
        if (event.target === modalOverlay) {
            closeModal();
        }
    });
}

document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
        if (modalOverlay && modalOverlay.classList.contains('open')) {
            closeModal();
            return;
        }

        if (mobileMenu && mobileMenu.classList.contains('open')) {
            closeMobileMenu();
        }
    }
});

/* ============================================
   FORM HELPERS
============================================ */
function findRegisterRoleSelect() {
    return document.querySelector('#modal-form-register select[name*="[role]"], #modal-form-register select[name="role"]');
}

function findRegisterFacultySelect() {
    return document.querySelector('#modal-form-register select[name*="[faculty]"], #modal-form-register select[name="faculty"]');
}

function syncRegisterFacultyVisibility() {
    const roleSelect = findRegisterRoleSelect();
    const facultySelect = findRegisterFacultySelect();

    if (!roleSelect || !facultySelect) return;

    const facultyWrapper = facultySelect.closest('tr')
        || facultySelect.closest('.form-group')
        || facultySelect.closest('td')
        || facultySelect.parentElement;
    const showFaculty = roleSelect.value === 'student' || roleSelect.value === 'absolvent';

    if (facultyWrapper) {
        facultyWrapper.style.display = showFaculty ? '' : 'none';
    }

    facultySelect.required = false;
    if (!showFaculty) {
        facultySelect.value = '';
    }
}

document.addEventListener('change', function (event) {
    const roleSelect = findRegisterRoleSelect();
    if (roleSelect && event.target === roleSelect) {
        syncRegisterFacultyVisibility();
    }
});

function initializePageUi() {
    restoreAuthModalState();
    syncRegisterFacultyVisibility();
    syncProfileFacultyVisibility();
    initializeBackToTop();
    initializeFilterableGrid('events-grid', 'events-empty', 'events-reset', 'events-load-more-wrap');
    initializeFilterableGrid('home-events-grid', 'home-events-empty', 'home-events-reset');
    initializeFilterableGrid('experts-grid', 'experts-empty', 'experts-reset', 'experts-load-more-wrap');
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializePageUi);
} else {
    initializePageUi();
}

function initializeBackToTop() {
    const button = document.getElementById('back-to-top');
    if (!button || button.dataset.initialized === '1') return;

    button.dataset.initialized = '1';

    const syncVisibility = () => {
        button.classList.toggle('visible', window.scrollY > 360);
    };

    button.addEventListener('click', () => {
        const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        window.scrollTo({
            top: 0,
            behavior: prefersReducedMotion ? 'auto' : 'smooth',
        });
    });

    window.addEventListener('scroll', syncVisibility, { passive: true });
    syncVisibility();
}

function switchProfileTab(tabId, button) {
    document.querySelectorAll('.profile-tab').forEach((tab) => tab.classList.remove('active'));
    document.querySelectorAll('.profile-tab-content').forEach((content) => content.classList.remove('active'));

    if (button) {
        button.classList.add('active');
    }

    const target = document.getElementById(tabId);
    if (target) {
        target.classList.add('active');
    }
}

function findProfileRoleSelect() {
    return document.getElementById('profile-role-select');
}

function findProfileFacultySelect() {
    return document.getElementById('profile-faculty-select');
}

function syncProfileFacultyVisibility() {
    const roleSelect = findProfileRoleSelect();
    const facultySelect = findProfileFacultySelect();
    const facultyWrapper = document.getElementById('profile-faculty-group');

    if (!roleSelect || !facultySelect || !facultyWrapper) return;

    const showFaculty = roleSelect.value === 'student' || roleSelect.value === 'absolvent';
    facultyWrapper.style.display = showFaculty ? '' : 'none';
    facultySelect.required = false;

    if (!showFaculty) {
        facultySelect.value = '';
    }
}

document.addEventListener('change', function (event) {
    const profileRoleSelect = findProfileRoleSelect();
    if (profileRoleSelect && event.target === profileRoleSelect) {
        syncProfileFacultyVisibility();
    }
});

/* ============================================
   FILTERS (TAGS)
============================================ */
const activeTags = {};
const gridVisibleCounts = {};

function getGridVisibleCount(gridId) {
    const grid = document.getElementById(gridId);
    if (!grid) return 0;

    if (typeof gridVisibleCounts[gridId] === 'number') {
        return gridVisibleCounts[gridId];
    }

    const initial = parseInt(grid.getAttribute('data-visible-count') || grid.getAttribute('data-visible-limit') || '0', 10) || 0;
    gridVisibleCounts[gridId] = initial;
    return initial;
}

function setGridVisibleCount(gridId, count) {
    gridVisibleCounts[gridId] = count;

    const grid = document.getElementById(gridId);
    if (grid) {
        grid.setAttribute('data-visible-count', String(count));
    }
}

function resetGridVisibleCount(gridId) {
    const grid = document.getElementById(gridId);
    if (!grid) return;

    const initial = parseInt(grid.getAttribute('data-visible-limit') || '0', 10) || 0;
    setGridVisibleCount(gridId, initial);
}

function getGridFilterState(gridId) {
    if (!activeTags[gridId]) {
        activeTags[gridId] = {};
    }

    return activeTags[gridId];
}

function getGridDateInput(gridId, type) {
    return document.querySelector(`.filter-date-input[data-grid-id="${gridId}"][data-filter-date="${type}"]`);
}

function splitFilterValues(value) {
    return (value || '')
        .split('|')
        .map((item) => item.trim())
        .filter((item) => item !== '');
}

function toggleTag(btn, gridId, emptyId, resetId, loadMoreWrapId = null) {
    const tag = btn.getAttribute('data-tag');
    const group = btn.getAttribute('data-filter-group') || 'default';
    const state = getGridFilterState(gridId);
    if (!state[group]) {
        state[group] = new Set();
    }

    if (state[group].has(tag)) {
        state[group].delete(tag);
        btn.classList.remove('active');
    } else {
        state[group].add(tag);
        btn.classList.add('active');
    }

    resetGridVisibleCount(gridId);
    applyTagFilter(gridId, emptyId, resetId, loadMoreWrapId);
}

function updateDateFilter(gridId, emptyId, resetId, loadMoreWrapId = null) {
    resetGridVisibleCount(gridId);
    applyTagFilter(gridId, emptyId, resetId, loadMoreWrapId);
}

function applyTagFilter(gridId, emptyId, resetId, loadMoreWrapId = null) {
    const grid = document.getElementById(gridId);
    const empty = document.getElementById(emptyId);
    const reset = document.getElementById(resetId);
    const loadMoreWrap = loadMoreWrapId ? document.getElementById(loadMoreWrapId) : null;

    if (!grid) return;

    const state = getGridFilterState(gridId);
    const selectedGroups = Object.entries(state).filter(([, selected]) => selected instanceof Set && selected.size > 0);
    const hasAnyTagFilter = selectedGroups.length > 0;
    const dateFrom = getGridDateInput(gridId, 'from')?.value || '';
    const dateTo = getGridDateInput(gridId, 'to')?.value || '';
    const hasDateFilter = dateFrom !== '' || dateTo !== '';
    const visibleLimit = getGridVisibleCount(gridId);
    let matched = 0;
    let visible = 0;

    grid.querySelectorAll('[data-filter-card]').forEach(function (card) {
        let matches = true;

        for (const [group, selected] of selectedGroups) {
            const cardTags = splitFilterValues(card.getAttribute(`data-tags-${group}`));
            if (!cardTags.some((tag) => selected.has(tag))) {
                matches = false;
                break;
            }
        }

        if (matches && hasDateFilter) {
            const cardDate = (card.getAttribute('data-date') || '').trim();
            if (dateFrom !== '' && (cardDate === '' || cardDate < dateFrom)) {
                matches = false;
            }

            if (matches && dateTo !== '' && (cardDate === '' || cardDate > dateTo)) {
                matches = false;
            }
        }

        if (!matches) {
            card.style.display = 'none';
            return;
        }

        matched++;
        const canShow = visibleLimit <= 0 || matched <= visibleLimit;
        card.style.display = canShow ? '' : 'none';
        if (canShow) visible++;
    });

    if (empty) {
        empty.style.display = visible === 0 && (hasAnyTagFilter || hasDateFilter) ? 'block' : 'none';
    }

    if (reset) {
        reset.style.display = hasAnyTagFilter || hasDateFilter ? 'inline-flex' : 'none';
    }

    if (loadMoreWrap) {
        loadMoreWrap.style.display = matched > visible ? '' : 'none';
    }
}

function resetTags(gridId, emptyId, resetId, loadMoreWrapId = null) {
    activeTags[gridId] = {};

    const grid = document.getElementById(gridId);
    if (!grid) return;

    document.querySelectorAll(`.filter-chip[data-grid-id="${gridId}"]`).forEach((btn) => btn.classList.remove('active'));
    document.querySelectorAll(`.filter-date-input[data-grid-id="${gridId}"]`).forEach((input) => {
        input.value = '';
    });
    resetGridVisibleCount(gridId);
    applyTagFilter(gridId, emptyId, resetId, loadMoreWrapId);
}

function initializeFilterableGrid(gridId, emptyId, resetId, loadMoreWrapId = null) {
    const grid = document.getElementById(gridId);
    if (!grid) return;

    if (grid.hasAttribute('data-visible-limit')) {
        resetGridVisibleCount(gridId);
    }

    applyTagFilter(gridId, emptyId, resetId, loadMoreWrapId);
}

window.loadMoreCards = function (gridId, step = 12, emptyId = null, resetId = null, loadMoreWrapId = null) {
    const current = getGridVisibleCount(gridId);
    setGridVisibleCount(gridId, current + step);

    const resolvedEmptyId = emptyId ?? (gridId === 'events-grid' ? 'events-empty' : `${gridId}-empty`);
    const resolvedResetId = resetId ?? (gridId === 'events-grid' ? 'events-reset' : `${gridId}-reset`);
    applyTagFilter(gridId, resolvedEmptyId, resolvedResetId, loadMoreWrapId);
};

window.updateDateFilter = updateDateFilter;

function normalizeFilterText(value) {
    return (value || '')
        .toString()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .trim();
}

function matchesPrefixFilter(source, query) {
    if (query === '') {
        return true;
    }

    return source.startsWith(query);
}

function matchesWordPrefixFilter(source, query) {
    if (query === '') {
        return true;
    }

    return source
        .split(/[\s.\-]+/)
        .filter((part) => part !== '')
        .some((part) => part.startsWith(query));
}

window.filterAdminEvents = function () {
    const status = document.getElementById('admin-events-status')?.value || '';
    const time = document.getElementById('admin-events-time')?.value || '';
    const name = normalizeFilterText(document.getElementById('admin-events-name')?.value || '');
    const reset = document.getElementById('admin-events-reset');
    const empty = document.getElementById('admin-events-empty');
    const rows = document.querySelectorAll('#admin-events-table [data-filter-row]');

    let visible = 0;
    rows.forEach((row) => {
        const rowStatus = row.getAttribute('data-status') || '';
        const rowTime = row.getAttribute('data-time') || '';
        const rowName = normalizeFilterText(row.getAttribute('data-name') || '');

        const matchesStatus = status === '' || rowStatus === status;
        const matchesTime = time === '' || rowTime === time;
        const matchesName = matchesPrefixFilter(rowName, name);
        const matches = matchesStatus && matchesTime && matchesName;

        row.style.display = matches ? '' : 'none';
        if (matches) {
            visible++;
        }
    });

    const hasFilter = status !== '' || time !== '' || name !== '';
    if (reset) {
        reset.style.display = hasFilter ? 'inline-flex' : 'none';
    }
    if (empty) {
        empty.style.display = visible === 0 ? 'block' : 'none';
    }
};

window.resetAdminEventsFilters = function () {
    const status = document.getElementById('admin-events-status');
    const time = document.getElementById('admin-events-time');
    const name = document.getElementById('admin-events-name');

    if (status) status.value = '';
    if (time) time.value = '';
    if (name) name.value = '';

    window.filterAdminEvents();
};

window.filterAdminExperts = function () {
    const name = normalizeFilterText(document.getElementById('admin-experts-name')?.value || '');
    const reset = document.getElementById('admin-experts-reset');
    const empty = document.getElementById('admin-experts-empty');
    const cards = document.querySelectorAll('#admin-experts-grid [data-filter-card]');

    let visible = 0;
    cards.forEach((card) => {
        const cardName = normalizeFilterText(card.getAttribute('data-name') || '');
        const matches = matchesWordPrefixFilter(cardName, name);

        card.style.display = matches ? '' : 'none';
        if (matches) {
            visible++;
        }
    });

    if (reset) {
        reset.style.display = name !== '' ? 'inline-flex' : 'none';
    }
    if (empty) {
        empty.style.display = visible === 0 ? 'block' : 'none';
    }
};

window.resetAdminExpertsFilters = function () {
    const name = document.getElementById('admin-experts-name');
    if (name) {
        name.value = '';
    }

    window.filterAdminExperts();
};

window.filterAdminUsers = function () {
    const name = normalizeFilterText(document.getElementById('admin-users-name')?.value || '');
    const role = document.getElementById('admin-users-role')?.value || '';
    const reset = document.getElementById('admin-users-reset');
    const empty = document.getElementById('admin-users-empty');
    const rows = document.querySelectorAll('#admin-users-table [data-filter-row]');

    let visible = 0;
    rows.forEach((row) => {
        const rowName = normalizeFilterText(row.getAttribute('data-name') || '');
        const rowRole = row.getAttribute('data-role') || '';
        const matchesName = matchesWordPrefixFilter(rowName, name);
        const matchesRole = role === '' || rowRole === role;
        const matches = matchesName && matchesRole;

        row.style.display = matches ? '' : 'none';
        if (matches) {
            visible++;
        }
    });

    if (reset) {
        reset.style.display = name !== '' || role !== '' ? 'inline-flex' : 'none';
    }
    if (empty) {
        empty.style.display = visible === 0 ? 'block' : 'none';
    }
};

window.resetAdminUsersFilters = function () {
    const name = document.getElementById('admin-users-name');
    const role = document.getElementById('admin-users-role');
    if (name) {
        name.value = '';
    }
    if (role) {
        role.value = '';
    }

    window.filterAdminUsers();
};

let activeAdminReportsMonth = '';

window.toggleAdminReportsMonth = function (button) {
    const month = button.getAttribute('data-month') || '';
    const isActive = button.classList.contains('active');

    document.querySelectorAll('[data-month-chip]').forEach((chip) => chip.classList.remove('active'));

    if (isActive) {
        activeAdminReportsMonth = '';
    } else {
        activeAdminReportsMonth = month;
        button.classList.add('active');
    }

    window.filterAdminReports();
};

window.filterAdminReports = function () {
    const name = normalizeFilterText(document.getElementById('admin-reports-name')?.value || '');
    const reset = document.getElementById('admin-reports-reset');
    const empty = document.getElementById('admin-reports-empty');
    const cards = document.querySelectorAll('#admin-reports-grid [data-filter-card]');

    let visible = 0;
    cards.forEach((card) => {
        const cardName = normalizeFilterText(card.getAttribute('data-name') || '');
        const cardMonth = card.getAttribute('data-month') || '';
        const matchesName = matchesPrefixFilter(cardName, name);
        const matchesMonth = activeAdminReportsMonth === '' || cardMonth === activeAdminReportsMonth;
        const matches = matchesName && matchesMonth;

        card.style.display = matches ? '' : 'none';
        if (matches) {
            visible++;
        }
    });

    if (reset) {
        reset.style.display = name !== '' || activeAdminReportsMonth !== '' ? 'inline-flex' : 'none';
    }
    if (empty) {
        empty.style.display = visible === 0 ? 'block' : 'none';
    }
};

window.resetAdminReportsFilters = function () {
    const name = document.getElementById('admin-reports-name');
    if (name) {
        name.value = '';
    }

    activeAdminReportsMonth = '';
    document.querySelectorAll('[data-month-chip]').forEach((chip) => chip.classList.remove('active'));

    window.filterAdminReports();
};

/* ============================================
   FLASH MESSAGES
============================================ */
(function () {
    const flashes = document.querySelectorAll('.flash');
    if (!flashes.length) return;

    flashes.forEach((flash) => {
        setTimeout(() => {
            flash.style.transition = 'opacity 0.4s, transform 0.4s';
            flash.style.opacity = '0';
            flash.style.transform = 'translateY(-4px)';
            setTimeout(() => flash.remove(), 420);
        }, 5000);
    });
})();

/* ============================================
   PROGRESS BAR
============================================ */
(function () {
    document.querySelectorAll('.capacity-fill[data-percent]').forEach((bar) => {
        const pct = Math.min(100, Math.max(0, parseInt(bar.getAttribute('data-percent'), 10) || 0));
        setTimeout(() => {
            bar.style.width = `${pct}%`;
            if (pct >= 90) bar.style.background = '#D94F10';
            if (pct >= 100) bar.style.background = '#C0371A';
        }, 120);
    });
})();

/* ============================================
   SHARE EVENT
============================================ */
window.shareEvent = function () {
    if (navigator.share) {
        navigator.share({
            title: document.title,
            url: window.location.href,
        }).catch(() => {});
        return;
    }

    if (navigator.clipboard?.writeText) {
        navigator.clipboard.writeText(window.location.href).then(() => {
            alert('Odkaz byl zkopírován do schránky.');
        }).catch(() => {
            alert('Odkaz se nepodařilo zkopírovat.');
        });
        return;
    }

    alert(window.location.href);
};
