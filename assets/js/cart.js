/**
 * =====================================================================
 * YATIYA COUTURE - Shopping Bag Client Controller
 * Pure Vanilla JavaScript (No Frameworks or Libraries)
 * Handles quantity steppers, AJAX bag updates, and dynamic summary refresh
 * =====================================================================
 */

document.addEventListener('DOMContentLoaded', function () {
    initCartQuantitySteppers();
    initCouponForm();
});

/**
 * Get current CSRF token from page meta tag or form
 */
function getCsrfToken() {
    const metaTag = document.querySelector('meta[name="csrf-token"]');
    if (metaTag && metaTag.content) {
        return metaTag.content;
    }
    const input = document.querySelector('input[name="csrf_token"]');
    return input ? input.value : '';
}

/**
 * Initialize quantity steppers with debounce and AJAX updates
 */
function initCartQuantitySteppers() {
    const rows = document.querySelectorAll('.cart-item-row');
    if (!rows.length) return;

    rows.forEach(row => {
        const itemId = row.getAttribute('data-item-id');
        const decrementBtn = row.querySelector('.cart-qty-decrement');
        const incrementBtn = row.querySelector('.cart-qty-increment');
        const input = row.querySelector('.cart-qty-input');
        const form = row.querySelector('.cart-qty-form');

        if (!input || !decrementBtn || !incrementBtn) return;

        const maxStock = parseInt(input.getAttribute('max'), 10) || 99;
        const minQty = parseInt(input.getAttribute('min'), 10) || 1;

        let debounceTimeout = null;

        function triggerQuantityUpdate(newVal) {
            input.value = newVal;

            if (debounceTimeout) clearTimeout(debounceTimeout);
            debounceTimeout = setTimeout(() => {
                sendCartUpdate(itemId, newVal, row, form);
            }, 300);
        }

        decrementBtn.addEventListener('click', function (e) {
            e.preventDefault();
            let current = parseInt(input.value, 10) || 1;
            if (current > minQty) {
                triggerQuantityUpdate(current - 1);
            }
        });

        incrementBtn.addEventListener('click', function (e) {
            e.preventDefault();
            let current = parseInt(input.value, 10) || 1;
            if (current < maxStock) {
                triggerQuantityUpdate(current + 1);
            } else {
                alert(`Atelier stock limit reached. Only ${maxStock} pieces available in this size/color variant.`);
            }
        });
    });
}

/**
 * Dispatch AJAX update for line item quantity
 */
function sendCartUpdate(itemId, quantity, rowElement, fallbackForm) {
    const csrfToken = getCsrfToken();
    const formData = new FormData();
    formData.append('action', 'update_qty');
    formData.append('item_id', itemId);
    formData.append('quantity', quantity);
    formData.append('csrf_token', csrfToken);
    formData.append('ajax', '1');

    fetch(fallbackForm.action, {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            // Update quantity input if server clamped it
            if (data.clamped && data.new_quantity) {
                const input = rowElement.querySelector('.cart-qty-input');
                if (input) input.value = data.new_quantity;
                alert(data.message);
            }

            // Update header cart count
            const headerCount = document.getElementById('headerCartCount');
            if (headerCount && data.cart_count !== undefined) {
                headerCount.innerText = data.cart_count;
            }

            // If item was removed (quantity <= 0)
            if (data.removed) {
                rowElement.style.transition = 'opacity 0.3s ease, height 0.3s ease';
                rowElement.style.opacity = '0';
                setTimeout(() => {
                    rowElement.remove();
                    if (data.cart_count === 0) {
                        window.location.reload();
                    }
                }, 300);
            }

            // Update summary values
            if (data.totals) {
                updateSummaryDisplay(data.totals);
            } else {
                window.location.reload();
            }
        } else {
            alert(data.message || 'Unable to update item quantity.');
            window.location.reload();
        }
    })
    .catch(() => {
        // Fallback to traditional form submission
        fallbackForm.submit();
    });
}

/**
 * Update Order Summary DOM elements
 */
function updateSummaryDisplay(totals) {
    const subtotalEl = document.getElementById('summarySubtotal');
    const shippingEl = document.getElementById('summaryShipping');
    const couponDiscountEl = document.getElementById('summaryCouponDiscount');
    const grandTotalEl = document.getElementById('summaryGrandTotal');
    const couponRow = document.getElementById('summaryCouponRow');

    if (subtotalEl && totals.subtotal_formatted) {
        subtotalEl.innerText = totals.subtotal_formatted;
    }

    if (shippingEl && totals.shipping_fee_formatted) {
        if (totals.is_free_shipping) {
            shippingEl.innerHTML = '<span class="complimentary-tag">COMPLIMENTARY</span>';
        } else {
            shippingEl.innerText = totals.shipping_fee_formatted;
        }
    }

    if (couponDiscountEl && totals.coupon_discount_formatted) {
        couponDiscountEl.innerText = '− ' + totals.coupon_discount_formatted;
        if (couponRow) {
            couponRow.style.display = (totals.coupon_discount_formatted !== '₹ 0.00' && totals.coupon_discount_formatted !== '₹0.00') ? '' : 'none';
        }
    }

    if (grandTotalEl && totals.grand_total_formatted) {
        grandTotalEl.innerText = totals.grand_total_formatted;
    }
}

/**
 * Coupon form submission enhancements
 */
function initCouponForm() {
    const couponForm = document.getElementById('couponForm');
    if (!couponForm) return;

    // Optional AJAX enhancement for coupon apply
    couponForm.addEventListener('submit', function (e) {
        const input = document.getElementById('couponCodeInput');
        if (!input || !input.value.trim()) {
            e.preventDefault();
            alert('Please enter a valid privilege coupon code.');
            input.focus();
        }
    });
}

