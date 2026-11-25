/**
 * YATIYA COUTURE - Luxury Admin Management Console Client Controller
 * Pure Vanilla JavaScript: Mobile Drawer, Modal Handlers, Dynamic Variant Form Rows,
 * Image Previews, and SVG Sales Analytics Visualization.
 */

document.addEventListener('DOMContentLoaded', function () {
    initMobileSidebar();
    initModals();
    initSalesChart();
    initVariantRows();
    initImagePreviews();
    initConfirmations();
    initSectionSorting();
});

/* Mobile Sidebar Drawer Toggle */
function initMobileSidebar() {
    const menuBtn = document.getElementById('mobileMenuToggle');
    const sidebar = document.getElementById('adminSidebar');
    const backdrop = document.getElementById('sidebarBackdrop');

    if (!menuBtn || !sidebar) return;

    function openSidebar() {
        sidebar.classList.add('open');
        if (backdrop) backdrop.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeSidebar() {
        sidebar.classList.remove('open');
        if (backdrop) backdrop.classList.remove('active');
        document.body.style.overflow = '';
    }

    menuBtn.addEventListener('click', function () {
        if (sidebar.classList.contains('open')) {
            closeSidebar();
        } else {
            openSidebar();
        }
    });

    if (backdrop) {
        backdrop.addEventListener('click', closeSidebar);
    }
}

/* Modal Controller */
function initModals() {
    // Open modal triggers
    document.querySelectorAll('[data-modal-target]').forEach(trigger => {
        trigger.addEventListener('click', function (e) {
            e.preventDefault();
            const targetId = this.getAttribute('data-modal-target');
            openModal(targetId);
        });
    });

    // Close modal triggers
    document.querySelectorAll('[data-modal-close]').forEach(btn => {
        btn.addEventListener('click', function () {
            const modal = this.closest('.modal-backdrop');
            if (modal) closeModal(modal);
        });
    });

    // Backdrop click close
    document.querySelectorAll('.modal-backdrop').forEach(backdrop => {
        backdrop.addEventListener('click', function (e) {
            if (e.target === this) {
                closeModal(this);
            }
        });
    });
}

function openModal(id) {
    const modal = document.getElementById(id);
    if (!modal) return;
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeModal(modal) {
    if (typeof modal === 'string') {
        modal = document.getElementById(modal);
    }
    if (!modal) return;
    modal.classList.remove('active');
    document.body.style.overflow = '';
}

/* Sales Chart SVG Generator */
function initSalesChart() {
    const chartEl = document.getElementById('salesChartContainer');
    if (!chartEl) return;

    const dataAttr = chartEl.getAttribute('data-chart');
    if (!dataAttr) return;

    let points = [];
    try {
        points = JSON.parse(dataAttr);
    } catch (e) {
        return;
    }

    if (!points || points.length === 0) {
        chartEl.innerHTML = '<div style="display:flex;align-items:center;justify-content:center;height:100%;color:#777168;">No sales activity recorded for this period.</div>';
        return;
    }

    const width = 600;
    const height = 200;
    const padding = { top: 20, right: 20, bottom: 30, left: 50 };

    const maxRev = Math.max(...points.map(p => p.revenue), 1000);
    const barWidth = 36;
    const step = (width - padding.left - padding.right) / points.length;

    let svg = `<svg viewBox="0 0 ${width} ${height}" class="chart-svg" preserveAspectRatio="none">`;
    
    // Grid lines
    for (let i = 0; i <= 4; i++) {
        const y = padding.top + (i * ((height - padding.top - padding.bottom) / 4));
        const val = Math.round(maxRev - (i * (maxRev / 4)));
        svg += `<line x1="${padding.left}" y1="${y}" x2="${width - padding.right}" y2="${y}" stroke="#E8E2D5" stroke-dasharray="3,3" />`;
        svg += `<text x="${padding.left - 8}" y="${y + 4}" text-anchor="end" font-size="10" fill="#777168">₹${val >= 1000 ? (val/1000).toFixed(1) + 'k' : val}</text>`;
    }

    // Bars
    points.forEach((pt, idx) => {
        const x = padding.left + (idx * step) + (step / 2) - (barWidth / 2);
        const barHeight = ((pt.revenue / maxRev) * (height - padding.top - padding.bottom));
        const y = height - padding.bottom - barHeight;

        // Bar
        svg += `
            <rect x="${x}" y="${y}" width="${barWidth}" height="${barHeight}" rx="3" fill="#C6A15B" opacity="0.88">
                <title>${pt.label}: ₹${pt.revenue.toLocaleString('en-IN')} (${pt.orders} orders)</title>
            </rect>
        `;

        // X Label
        svg += `<text x="${x + (barWidth / 2)}" y="${height - 10}" text-anchor="middle" font-size="10" fill="#777168">${pt.label}</text>`;
    });

    svg += `</svg>`;
    chartEl.innerHTML = svg;
}

/* Dynamic Variant Rows in Product Form */
function initVariantRows() {
    const addBtn = document.getElementById('btnAddVariantRow');
    const container = document.getElementById('variantsContainer');
    if (!addBtn || !container) return;

    addBtn.addEventListener('click', function () {
        const rowIndex = container.querySelectorAll('.variant-row').length;
        const row = document.createElement('div');
        row.className = 'variant-row form-row';
        row.style.marginBottom = '12px';
        row.style.paddingBottom = '12px';
        row.style.borderBottom = '1px dashed #E8E2D5';

        row.innerHTML = `
            <div>
                <label class="form-label">Size</label>
                <input type="text" name="variants[${rowIndex}][size]" class="form-control" placeholder="e.g. S, M, L, XL" required>
            </div>
            <div>
                <label class="form-label">Color</label>
                <input type="text" name="variants[${rowIndex}][color]" class="form-control" placeholder="e.g. Royal Ivory" required>
            </div>
            <div>
                <label class="form-label">Color Code</label>
                <input type="color" name="variants[${rowIndex}][color_code]" class="form-control" value="#C6A15B" style="height:38px;padding:2px;">
            </div>
            <div>
                <label class="form-label">SKU</label>
                <input type="text" name="variants[${rowIndex}][sku]" class="form-control" placeholder="Auto-generated if blank">
            </div>
            <div>
                <label class="form-label">Stock Quantity</label>
                <input type="number" name="variants[${rowIndex}][stock_quantity]" class="form-control" min="0" value="10" required>
            </div>
            <div>
                <label class="form-label">Add'l Price (₹)</label>
                <input type="number" name="variants[${rowIndex}][additional_price]" class="form-control" step="0.01" value="0.00">
            </div>
            <div style="display:flex;align-items:flex-end;">
                <button type="button" class="btn btn-outline btn-sm btn-remove-variant" style="color:#D32F2F;border-color:#D32F2F;">Remove</button>
            </div>
        `;

        container.appendChild(row);
    });

    container.addEventListener('click', function (e) {
        if (e.target.classList.contains('btn-remove-variant')) {
            const row = e.target.closest('.variant-row');
            if (row) {
                row.remove();
            }
        }
    });
}

/* Image File Input Preview */
function initImagePreviews() {
    const fileInputs = document.querySelectorAll('input[type="file"][data-preview-target]');
    fileInputs.forEach(input => {
        input.addEventListener('change', function () {
            const targetId = this.getAttribute('data-preview-target');
            const previewEl = document.getElementById(targetId);
            if (!previewEl || !this.files || !this.files[0]) return;

            const file = this.files[0];
            const reader = new FileReader();
            reader.onload = function (e) {
                previewEl.src = e.target.result;
                previewEl.style.display = 'block';
            };
            reader.readAsDataURL(file);
        });
    });
}

/* Action Confirmations */
function initConfirmations() {
    document.querySelectorAll('[data-confirm]').forEach(btn => {
        btn.addEventListener('click', function (e) {
            const msg = this.getAttribute('data-confirm') || 'Are you sure you want to proceed with this operation?';
            if (!confirm(msg)) {
                e.preventDefault();
            }
        });
    });
}

/* Drag and Drop / Move Up-Down Reordering for Homepage Sections */
function initSectionSorting() {
    const list = document.getElementById('sectionSortingList');
    if (!list) return;

    let draggedItem = null;

    list.querySelectorAll('.section-item-card').forEach(item => {
        item.setAttribute('draggable', 'true');

        item.addEventListener('dragstart', function (e) {
            draggedItem = this;
            setTimeout(() => this.classList.add('dragging'), 0);
        });

        item.addEventListener('dragend', function () {
            draggedItem = null;
            this.classList.remove('dragging');
            updateOrderBadges();
        });

        item.addEventListener('dragover', function (e) {
            e.preventDefault();
            if (!draggedItem) return;
            const afterElement = getDragAfterElement(list, e.clientY);
            if (afterElement == null) {
                list.appendChild(draggedItem);
            } else {
                list.insertBefore(draggedItem, afterElement);
            }
        });
    });

    function getDragAfterElement(container, y) {
        const draggableElements = [...container.querySelectorAll('.section-item-card:not(.dragging)')];
        return draggableElements.reduce((closest, child) => {
            const box = child.getBoundingClientRect();
            const offset = y - box.top - box.height / 2;
            if (offset < 0 && offset > closest.offset) {
                return { offset: offset, element: child };
            } else {
                return closest;
            }
        }, { offset: Number.NEGATIVE_INFINITY }).element;
    }

    function updateOrderBadges() {
        const items = list.querySelectorAll('.section-item-card');
        items.forEach((item, index) => {
            const badge = item.querySelector('.section-order-badge');
            if (badge) badge.textContent = index + 1;
            const input = item.querySelector('input[name^="order"]');
            if (input) input.value = index + 1;
        });
    }

    // Move up / down arrow button support
    list.addEventListener('click', function (e) {
        const upBtn = e.target.closest('.btn-move-up');
        const downBtn = e.target.closest('.btn-move-down');
        if (upBtn) {
            const card = upBtn.closest('.section-item-card');
            const prev = card.previousElementSibling;
            if (prev) {
                list.insertBefore(card, prev);
                updateOrderBadges();
            }
        }
        if (downBtn) {
            const card = downBtn.closest('.section-item-card');
            const next = card.nextElementSibling;
            if (next) {
                list.insertBefore(next, card);
                updateOrderBadges();
            }
        }
    });
}


