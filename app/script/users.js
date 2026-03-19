/**
 * users.js  v4
 * Fully functional users management:
 *  - Live search + tab filtering (All / Pending / Approved / Archived / Inactive)
 *  - Sortable columns
 *  - Paginated table (10 per page)
 *  - Add / Edit / Delete / Approve / Archive / Restore via AJAX
 *  - Stat cards update after every mutation
 */

(() => {
    'use strict';

    // ===== STATE =====
    let state = {
        page:      1,
        perPage:   10,
        status:    'all',
        search:    '',
        sortCol:   'user_id',
        sortDir:   'ASC',
        total:     0,
        deletingId: null,
    };

    // ===== DOM REFS =====
    const tableBody    = document.getElementById('tableBody');
    const paginationBar = document.getElementById('paginationBar');
    const showingText  = document.getElementById('showingText');
    const searchInput  = document.getElementById('searchInput');
    const tabBtns      = document.querySelectorAll('.tab-btn');
    const checkAll     = document.getElementById('checkAll');

    // Stat spans
    const statTotal    = document.getElementById('statTotal');
    const statInactive = document.getElementById('statInactive');
    const statAdmins   = document.getElementById('statAdmins');
    const badgeAll     = document.getElementById('badgeAll');
    const badgePending = document.getElementById('badgePending');
    const badgeApproved= document.getElementById('badgeApproved');
    const badgeArchived= document.getElementById('badgeArchived');

    // Modals
    const addModal    = document.getElementById('addModal');
    const editModal   = document.getElementById('editModal');
    const deleteModal = document.getElementById('deleteModal');
    const addForm     = document.getElementById('addForm');
    const editForm    = document.getElementById('editForm');
    const addError    = document.getElementById('addError');
    const editError   = document.getElementById('editError');

    // ===== UTILITIES =====
    const esc = (str) => String(str ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');

    const roleIcon = (role) => {
        const map = { Admin: 'fa-star', Moderator: 'fa-shield-halved', User: 'fa-user' };
        return `<i class="fa-solid ${map[role] || 'fa-user'}" title="${role}"></i>`;
    };

    const statusBadge = (status, is_inactive) => {
        if (is_inactive == 1 && status === 'Approved') {
            return `<span class="badge badge--inactive">Inactive</span>`;
        }
        const map = {
            Pending:  'badge--pending',
            Approved: 'badge--approved',
            Archived: 'badge--archived',
        };
        return `<span class="badge ${map[status] || ''}">${esc(status)}</span>`;
    };

    const avatarHtml = (pic, name) => {
        const src = `assets/profiles/${esc(pic || 'default.jpg')}`;
        return `<img class="avatar-sm" src="${src}" alt="${esc(name)}" onerror="this.src='assets/profiles/default.jpg'">`;
    };

    // ===== FETCH & RENDER TABLE =====
    async function loadUsers() {
        tableBody.innerHTML = `<tr><td colspan="8" class="loading-row"><i class="fa-solid fa-spinner fa-spin"></i> Loading…</td></tr>`;

        const params = new URLSearchParams({
            search:   state.search,
            status:   state.status,
            page:     state.page,
            per_page: state.perPage,
            sort:     state.sortCol,
            dir:      state.sortDir,
        });

        try {
            const res  = await fetch(`users_fetch.php?${params}`);
            const data = await res.json();

            if (!data.success) throw new Error(data.error || 'Server error');

            state.total = data.total;
            renderTable(data.users);
            renderPagination(data.total);
            updateStats(data.stats);
            updateShowingText(data.users.length, data.total);

        } catch (err) {
            tableBody.innerHTML = `<tr><td colspan="8" class="error-row"><i class="fa-solid fa-circle-exclamation"></i> ${esc(err.message)}</td></tr>`;
        }
    }

    function renderTable(users) {
        if (!users.length) {
            tableBody.innerHTML = `<tr><td colspan="8" class="empty-row"><i class="fa-solid fa-users-slash"></i> No users found</td></tr>`;
            return;
        }

        const rows = users.map((u, idx) => {
            const num = (state.page - 1) * state.perPage + idx + 1;
            return `
            <tr data-id="${u.user_id}">
                <td class="col-check"><input type="checkbox" class="row-check" value="${u.user_id}"></td>
                <td class="col-num">${num}</td>
                <td class="col-name">
                    <div class="user-cell">
                        ${avatarHtml(u.profile_pic, u.user_name)}
                        <div class="user-info">
                            <span class="user-name">${esc(u.user_name)}</span>
                            <span class="user-stud">${esc(u.stud_num || '')}</span>
                        </div>
                    </div>
                </td>
                <td class="col-email">${esc(u.user_email)}</td>
                <td class="col-org">${esc(u.org_body)}</td>
                <td class="col-role">
                    <span class="role-pill role-${(u.role || 'user').toLowerCase()}">
                        ${roleIcon(u.role)} ${esc(u.role)}
                    </span>
                </td>
                <td class="col-status">${statusBadge(u.status, u.is_inactive)}</td>
                <td class="col-actions">
                    <div class="action-menu">
                        <button class="action-dots" data-id="${u.user_id}" aria-label="Actions">
                            <i class="fa-solid fa-ellipsis"></i>
                        </button>
                        <div class="action-dropdown" id="dropdown-${u.user_id}">
                            <button class="action-item" data-action="edit"
                                data-id="${u.user_id}"
                                data-name="${esc(u.user_name)}"
                                data-email="${esc(u.user_email)}"
                                data-stud="${esc(u.stud_num || '')}"
                                data-org="${esc(u.org_body)}"
                                data-role="${esc(u.role)}"
                                data-status="${esc(u.status)}">
                                <i class="fa-solid fa-pen"></i> Edit
                            </button>
                            ${u.status !== 'Approved' ? `
                            <button class="action-item action-item--approve" data-action="approve" data-id="${u.user_id}">
                                <i class="fa-solid fa-circle-check"></i> Approve
                            </button>` : ''}
                            ${u.status !== 'Archived' ? `
                            <button class="action-item action-item--warn" data-action="archive" data-id="${u.user_id}">
                                <i class="fa-solid fa-box-archive"></i> Archive
                            </button>` : `
                            <button class="action-item action-item--approve" data-action="restore" data-id="${u.user_id}">
                                <i class="fa-solid fa-rotate-left"></i> Restore
                            </button>`}
                            <hr class="action-divider">
                            <button class="action-item action-item--danger" data-action="delete"
                                data-id="${u.user_id}" data-name="${esc(u.user_name)}">
                                <i class="fa-solid fa-trash"></i> Delete
                            </button>
                        </div>
                    </div>
                </td>
            </tr>`;
        });

        tableBody.innerHTML = rows.join('');
    }

    function renderPagination(total) {
        const pages = Math.ceil(total / state.perPage);
        if (pages <= 1) { paginationBar.innerHTML = ''; return; }

        const MAX_VISIBLE = 5;
        let html = '';

        // Prev
        html += `<button class="page-btn ${state.page === 1 ? 'disabled' : ''}" data-page="${state.page - 1}" ${state.page === 1 ? 'disabled' : ''}>
            <i class="fa-solid fa-chevron-left"></i>
        </button>`;

        // Page numbers
        let start = Math.max(1, state.page - Math.floor(MAX_VISIBLE / 2));
        let end   = Math.min(pages, start + MAX_VISIBLE - 1);
        if (end - start < MAX_VISIBLE - 1) start = Math.max(1, end - MAX_VISIBLE + 1);

        for (let i = start; i <= end; i++) {
            html += `<button class="page-btn ${i === state.page ? 'page-btn--active' : ''}" data-page="${i}">${i}</button>`;
        }

        // Next
        html += `<button class="page-btn ${state.page === pages ? 'disabled' : ''}" data-page="${state.page + 1}" ${state.page === pages ? 'disabled' : ''}>
            <i class="fa-solid fa-chevron-right"></i>
        </button>`;

        paginationBar.innerHTML = html;
    }

    function updateStats(s) {
        if (!s) return;
        statTotal.textContent    = s.total;
        statInactive.textContent = s.inactive;
        statAdmins.textContent   = s.admins;
        badgeAll.textContent     = s.total;
        badgePending.textContent = s.pending_count;
        badgeApproved.textContent= `· ${s.approved_count}`;
        badgeArchived.textContent= `· ${s.archived_count}`;
    }

    function updateShowingText(shown, total) {
        const from = total === 0 ? 0 : (state.page - 1) * state.perPage + 1;
        const to   = Math.min(state.page * state.perPage, total);
        showingText.textContent = `Showing ${from} to ${to} of ${total}`;
    }

    // ===== MODAL HELPERS =====
    function openModal(id) {
        document.getElementById(id).classList.add('is-open');
        document.body.style.overflow = 'hidden';
    }

    function closeModal(id) {
        document.getElementById(id).classList.remove('is-open');
        document.body.style.overflow = '';
    }

    function closeAllModals() {
        document.querySelectorAll('.modal-overlay').forEach(m => m.classList.remove('is-open'));
        document.body.style.overflow = '';
    }

    function showFormError(el, msg) {
        el.textContent = msg;
        el.hidden = false;
    }

    function clearFormError(el) {
        el.textContent = '';
        el.hidden = true;
    }

    // ===== MUTATIONS (POST to users_action.php) =====
    async function mutate(payload) {
        const fd = new FormData();
        for (const [k, v] of Object.entries(payload)) fd.append(k, v);

        const res  = await fetch('users_action.php', { method: 'POST', body: fd });
        const data = await res.json();
        return data;
    }

    // ===== ADD FORM SUBMIT =====
    addForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        clearFormError(addError);
        const btn = addForm.querySelector('[type=submit]');
        btn.disabled = true;

        const fd = new FormData(addForm);
        fd.append('action', 'add');
        const res  = await fetch('users_action.php', { method: 'POST', body: fd });
        const data = await res.json();

        btn.disabled = false;

        if (!data.success) {
            showFormError(addError, data.message);
            return;
        }

        closeModal('addModal');
        addForm.reset();
        state.page = 1;
        loadUsers();
    });

    // ===== EDIT FORM SUBMIT =====
    editForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        clearFormError(editError);
        const btn = editForm.querySelector('[type=submit]');
        btn.disabled = true;

        const fd = new FormData(editForm);
        const res  = await fetch('users_action.php', { method: 'POST', body: fd });
        const data = await res.json();

        btn.disabled = false;

        if (!data.success) {
            showFormError(editError, data.message);
            return;
        }

        closeModal('editModal');
        loadUsers();
    });

    // ===== DELETE CONFIRM =====
    document.getElementById('btnConfirmDelete').addEventListener('click', async () => {
        if (!state.deletingId) return;
        const btn = document.getElementById('btnConfirmDelete');
        btn.disabled = true;

        const data = await mutate({ action: 'delete', user_id: state.deletingId });
        btn.disabled = false;

        closeModal('deleteModal');
        state.deletingId = null;

        if (data.success) {
            loadUsers();
        } else {
            alert(data.message || 'Delete failed.');
        }
    });

    // ===== TABLE BODY EVENT DELEGATION =====
    tableBody.addEventListener('click', async (e) => {
        // Action dots toggle
        const dots = e.target.closest('.action-dots');
        if (dots) {
            e.stopPropagation();
            const dd = document.getElementById(`dropdown-${dots.dataset.id}`);
            document.querySelectorAll('.action-dropdown.is-open').forEach(d => {
                if (d !== dd) d.classList.remove('is-open');
            });
            dd && dd.classList.toggle('is-open');
            return;
        }

        const btn = e.target.closest('[data-action]');
        if (!btn) return;

        const action  = btn.dataset.action;
        const user_id = btn.dataset.id;

        // Close dropdown
        document.querySelectorAll('.action-dropdown.is-open').forEach(d => d.classList.remove('is-open'));

        if (action === 'edit') {
            // Populate edit form
            document.getElementById('edit_user_id').value    = user_id;
            document.getElementById('edit_user_name').value  = btn.dataset.name;
            document.getElementById('edit_user_email').value = btn.dataset.email;
            document.getElementById('edit_stud_num').value   = btn.dataset.stud;
            document.getElementById('edit_org_body').value   = btn.dataset.org;
            document.getElementById('edit_role').value       = btn.dataset.role;
            document.getElementById('edit_status').value     = btn.dataset.status;
            clearFormError(editError);
            openModal('editModal');
            return;
        }

        if (action === 'delete') {
            state.deletingId = user_id;
            document.getElementById('deleteUserName').textContent = btn.dataset.name;
            openModal('deleteModal');
            return;
        }

        if (['approve', 'archive', 'restore'].includes(action)) {
            const data = await mutate({ action, user_id });
            if (data.success) {
                loadUsers();
            } else {
                alert(data.message || `${action} failed.`);
            }
            return;
        }
    });

    // ===== CLOSE DROPDOWNS ON OUTSIDE CLICK =====
    document.addEventListener('click', () => {
        document.querySelectorAll('.action-dropdown.is-open').forEach(d => d.classList.remove('is-open'));
    });

    // ===== PAGINATION CLICKS =====
    paginationBar.addEventListener('click', (e) => {
        const btn = e.target.closest('.page-btn:not(.disabled)');
        if (!btn) return;
        state.page = parseInt(btn.dataset.page, 10);
        loadUsers();
    });

    // ===== TAB BUTTONS =====
    tabBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            tabBtns.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            state.status = btn.dataset.status;
            state.page   = 1;
            loadUsers();
        });
    });

    // ===== SEARCH (debounced) =====
    let searchTimer;
    searchInput.addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => {
            state.search = searchInput.value.trim();
            state.page   = 1;
            loadUsers();
        }, 300);
    });

    // ===== COLUMN SORTING =====
    document.querySelectorAll('th.sortable').forEach(th => {
        th.addEventListener('click', () => {
            const col = th.dataset.col;
            if (state.sortCol === col) {
                state.sortDir = state.sortDir === 'ASC' ? 'DESC' : 'ASC';
            } else {
                state.sortCol = col;
                state.sortDir = 'ASC';
            }
            // Update header icons
            document.querySelectorAll('th.sortable').forEach(t => {
                t.classList.remove('sort-asc', 'sort-desc');
            });
            th.classList.add(state.sortDir === 'ASC' ? 'sort-asc' : 'sort-desc');
            state.page = 1;
            loadUsers();
        });
    });

    // ===== SELECT ALL CHECKBOX =====
    checkAll.addEventListener('change', () => {
        document.querySelectorAll('.row-check').forEach(cb => cb.checked = checkAll.checked);
    });

    // ===== OPEN ADD MODAL =====
    document.getElementById('btnOpenAddModal').addEventListener('click', () => {
        addForm.reset();
        clearFormError(addError);
        openModal('addModal');
    });

    // ===== MODAL CLOSE BUTTONS (data-close) =====
    document.querySelectorAll('[data-close]').forEach(btn => {
        btn.addEventListener('click', () => closeModal(btn.dataset.close));
    });

    // ===== CLOSE ON BACKDROP CLICK =====
    document.querySelectorAll('.modal-overlay').forEach(overlay => {
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) closeAllModals();
        });
    });

    // ===== CLOSE ON ESC =====
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') closeAllModals();
    });

    // ===== INITIAL LOAD =====
    loadUsers();

})();