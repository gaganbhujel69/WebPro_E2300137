/* ============================================================
   EduSkill Marketplace System (EMS) — Global JavaScript
   assets/js/main.js
   ============================================================ */

'use strict';

// ── DOM-ready wrapper ───────────────────────────────────────
document.addEventListener('DOMContentLoaded', function () {

    // ── Auto-dismiss Bootstrap alerts after 5 s ─────────────
    document.querySelectorAll('.alert-dismissible[data-auto-dismiss]').forEach(function (el) {
        const delay = parseInt(el.dataset.autoDismiss, 10) || 5000;
        setTimeout(function () {
            const bsAlert = bootstrap.Alert.getOrCreateInstance(el);
            if (bsAlert) bsAlert.close();
        }, delay);
    });

    // ── Highlight the active nav-link ───────────────────────
    const currentPath = window.location.pathname;
    document.querySelectorAll('.navbar .nav-link').forEach(function (link) {
        if (link.getAttribute('href') === currentPath) {
            link.classList.add('active');
        }
    });

    // ── Confirm-on-delete: add to any form with data-confirm ─
    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            const msg = form.dataset.confirm || 'Are you sure you want to proceed?';
            if (!window.confirm(msg)) {
                e.preventDefault();
            }
        });
    });

    // ── Confirm-on-click: buttons / links with data-confirm ──
    document.querySelectorAll('[data-confirm]:not(form)').forEach(function (el) {
        el.addEventListener('click', function (e) {
            const msg = el.dataset.confirm || 'Are you sure?';
            if (!window.confirm(msg)) {
                e.preventDefault();
            }
        });
    });

    // ── Star rating: sync hidden input value ────────────────
    document.querySelectorAll('.star-rating input[type="radio"]').forEach(function (radio) {
        radio.addEventListener('change', function () {
            const hiddenInput = document.querySelector('input[name="rating_value"]');
            if (hiddenInput) hiddenInput.value = this.value;
        });
    });

    // ── Receipt print button ─────────────────────────────────
    const printBtn = document.getElementById('btnPrintReceipt');
    if (printBtn) {
        printBtn.addEventListener('click', function () {
            window.print();
        });
    }

    // ── Provider registration: toggle extra fields ───────────
    const roleSelect = document.getElementById('registerRole');
    const providerFields = document.getElementById('providerExtraFields');
    if (roleSelect && providerFields) {
        function toggleProviderFields() {
            providerFields.style.display =
                roleSelect.value === 'training_provider' ? 'block' : 'none';
        }
        roleSelect.addEventListener('change', toggleProviderFields);
        toggleProviderFields(); // run on load
    }

    // ── Analytics: seat capacity progress bars ───────────────
    document.querySelectorAll('.seat-progress').forEach(function (bar) {
        const taken = parseInt(bar.dataset.taken, 10) || 0;
        const total = parseInt(bar.dataset.total, 10) || 1;
        const pct   = Math.min(100, Math.round((taken / total) * 100));
        bar.style.width = pct + '%';
        bar.setAttribute('aria-valuenow', pct);
        if (pct >= 90) bar.classList.add('bg-danger');
        else if (pct >= 60) bar.classList.add('bg-warning');
        else bar.classList.add('bg-success');
    });

    // ── Course filter: client-side search ────────────────────
    const courseSearch = document.getElementById('courseSearchInput');
    if (courseSearch) {
        courseSearch.addEventListener('input', function () {
            const q = this.value.toLowerCase();
            document.querySelectorAll('.course-card-wrapper').forEach(function (wrapper) {
                const text = wrapper.textContent.toLowerCase();
                wrapper.style.display = text.includes(q) ? '' : 'none';
            });
        });
    }

    // ── Table sort helpers (data-sort-table) ─────────────────
    document.querySelectorAll('table[data-sort-table] thead th[data-col]').forEach(function (th) {
        th.style.cursor = 'pointer';
        th.addEventListener('click', function () {
            sortTable(this);
        });
    });

});

// ── Utility: sort an HTML table by the clicked header column ─
function sortTable(th) {
    const table = th.closest('table');
    const col   = parseInt(th.dataset.col, 10);
    const asc   = th.dataset.sortDir !== 'asc';
    th.dataset.sortDir = asc ? 'asc' : 'desc';

    const tbody = table.querySelector('tbody');
    const rows  = Array.from(tbody.querySelectorAll('tr'));

    rows.sort(function (a, b) {
        const A = a.cells[col]?.textContent.trim().toLowerCase() ?? '';
        const B = b.cells[col]?.textContent.trim().toLowerCase() ?? '';
        if (!isNaN(A) && !isNaN(B)) return asc ? A - B : B - A;
        return asc ? A.localeCompare(B) : B.localeCompare(A);
    });

    rows.forEach(function (row) { tbody.appendChild(row); });
}

// ── Utility: show a Bootstrap toast notification ─────────────
function showToast(message, type = 'success') {
    const container = document.getElementById('toastContainer');
    if (!container) return;

    const id   = 'toast-' + Date.now();
    const icon = type === 'success' ? 'check-circle-fill' : 'exclamation-triangle-fill';
    const html = `
        <div id="${id}" class="toast align-items-center text-bg-${type} border-0"
             role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="4000">
            <div class="d-flex">
                <div class="toast-body">
                    <i class="bi bi-${icon} me-2"></i>${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto"
                        data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>`;
    container.insertAdjacentHTML('beforeend', html);
    bootstrap.Toast.getOrCreateInstance(document.getElementById(id)).show();
}
