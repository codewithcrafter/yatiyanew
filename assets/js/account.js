/**
 * =====================================================================
 * YATIYA COUTURE - Customer Account Client Controller
 * Pure Vanilla JavaScript (No Frameworks or Libraries)
 * Address modals, tab navigation, and client confirmation handling
 * =====================================================================
 */

document.addEventListener('DOMContentLoaded', function () {
    initAddressModal();
});

/**
 * Address Creation & Editing Modal Controller
 */
function initAddressModal() {
    const modal = document.getElementById('addressModal');
    const openBtn = document.getElementById('openAddAddressBtn');
    const closeBtn = document.getElementById('closeAddressModalBtn');
    const cancelBtn = document.getElementById('cancelAddressModalBtn');
    const form = document.getElementById('addressForm');
    const modalTitle = document.getElementById('addressModalTitle');

    if (!modal) return;

    function openModal(isEdit = false, addressData = null) {
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';

        if (form) {
            form.reset();
            const idInput = form.querySelector('input[name="address_id"]');
            if (idInput) idInput.value = '';

            if (isEdit && addressData) {
                if (modalTitle) modalTitle.innerText = 'Edit Atelier Address';
                if (idInput) idInput.value = addressData.id || '';
                if (form.full_name) form.full_name.value = addressData.full_name || '';
                if (form.phone) form.phone.value = addressData.phone || '';
                if (form.address_type) form.address_type.value = addressData.address_type || 'home';
                if (form.address_line1) form.address_line1.value = addressData.address_line1 || '';
                if (form.address_line2) form.address_line2.value = addressData.address_line2 || '';
                if (form.landmark) form.landmark.value = addressData.landmark || '';
                if (form.city) form.city.value = addressData.city || '';
                if (form.state) form.state.value = addressData.state || '';
                if (form.postal_code) form.postal_code.value = addressData.postal_code || '';
                if (form.country) form.country.value = addressData.country || 'India';
                if (form.is_default_shipping) form.is_default_shipping.checked = !!parseInt(addressData.is_default_shipping, 10);
                if (form.is_default_billing) form.is_default_billing.checked = !!parseInt(addressData.is_default_billing, 10);
            } else {
                if (modalTitle) modalTitle.innerText = 'Add New Atelier Address';
            }
        }
    }

    function closeModal() {
        modal.style.display = 'none';
        document.body.style.overflow = '';
    }

    if (openBtn) {
        openBtn.addEventListener('click', () => openModal(false));
    }

    if (closeBtn) closeBtn.addEventListener('click', closeModal);
    if (cancelBtn) cancelBtn.addEventListener('click', closeModal);

    modal.addEventListener('click', function (e) {
        if (e.target === modal) closeModal();
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal.style.display === 'flex') {
            closeModal();
        }
    });

    // Delegate edit address buttons
    document.body.addEventListener('click', function (e) {
        const editBtn = e.target.closest('.edit-address-btn');
        if (!editBtn) return;

        e.preventDefault();
        const rawJson = editBtn.getAttribute('data-address');
        if (rawJson) {
            try {
                const data = JSON.parse(rawJson);
                openModal(true, data);
            } catch (err) {}
        }
    });
}

