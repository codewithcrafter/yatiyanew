/**
 * =====================================================================
 * YATIYA COUTURE - Shop & Filter Client Script
 * Brand Vision: "ELEVATE YOUR EVERYDAY STYLE"
 * Pure Vanilla JavaScript (No Frameworks)
 * =====================================================================
 */

document.addEventListener('DOMContentLoaded', function () {
    initMobileFilterSidebar();
    initFilterPills();
});

/**
 * Mobile Filter Sidebar Toggle
 */
function initMobileFilterSidebar() {
    const toggleBtn = document.getElementById('mobileFilterToggleBtn');
    const sidebar = document.getElementById('shopSidebar');
    const closeBtn = document.getElementById('sidebarCloseBtn');

    if (!toggleBtn || !sidebar) return;

    // Create backdrop for mobile drawer if not exists
    let backdrop = document.getElementById('shopSidebarBackdrop');
    if (!backdrop) {
        backdrop = document.createElement('div');
        backdrop.id = 'shopSidebarBackdrop';
        backdrop.style.cssText = 'position:fixed;inset:0;background:rgba(26,24,22,0.6);z-index:999;display:none;backdrop-filter:blur(2px);';
        document.body.appendChild(backdrop);
    }

    function openSidebar() {
        sidebar.classList.add('open');
        backdrop.style.display = 'block';
        document.body.style.overflow = 'hidden';
    }

    function closeSidebar() {
        sidebar.classList.remove('open');
        backdrop.style.display = 'none';
        document.body.style.overflow = '';
    }

    toggleBtn.addEventListener('click', openSidebar);
    if (closeBtn) closeBtn.addEventListener('click', closeSidebar);
    backdrop.addEventListener('click', closeSidebar);

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && sidebar.classList.contains('open')) {
            closeSidebar();
        }
    });
}

/**
 * Filter Pill Selection Visual Toggles (Sizes & Colors)
 */
function initFilterPills() {
    const sizeLabels = document.querySelectorAll('.size-filter-pill');
    sizeLabels.forEach((label) => {
        const checkbox = label.querySelector('input[type="checkbox"]');
        if (!checkbox) return;

        checkbox.addEventListener('change', () => {
            if (checkbox.checked) {
                label.classList.add('selected');
            } else {
                label.classList.remove('selected');
            }
        });
    });

    const colorItems = document.querySelectorAll('.color-filter-item');
    colorItems.forEach((label) => {
        const checkbox = label.querySelector('input[type="checkbox"]');
        if (!checkbox) return;

        checkbox.addEventListener('change', () => {
            if (checkbox.checked) {
                label.classList.add('selected');
            } else {
                label.classList.remove('selected');
            }
        });
    });
}

