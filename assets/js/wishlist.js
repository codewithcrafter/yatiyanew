/**
 * =====================================================================
 * YATIYA COUTURE - Private Wishlist Client Controller
 * Pure Vanilla JavaScript (No Frameworks or Libraries)
 * Handles wishlist card interactions, removals, and bag additions
 * =====================================================================
 */

document.addEventListener('DOMContentLoaded', function () {
    initWishlistCardInteractions();
});

/**
 * Get current CSRF token from page meta tag or form
 */
function getWishlistCsrfToken() {
    const metaTag = document.querySelector('meta[name="csrf-token"]');
    if (metaTag && metaTag.content) {
        return metaTag.content;
    }
    const input = document.querySelector('input[name="csrf_token"]');
    return input ? input.value : '';
}

/**
 * Initialize wishlist card actions with seamless AJAX
 */
function initWishlistCardInteractions() {
    const grid = document.getElementById('wishlistGrid');
    if (!grid) return;

    // Delegate remove form submissions
    grid.addEventListener('submit', function (e) {
        const removeForm = e.target.closest('.wishlist-remove-form');
        if (removeForm) {
            e.preventDefault();
            handleCardRemoval(removeForm);
            return;
        }

        const moveForm = e.target.closest('.wishlist-move-form');
        if (moveForm) {
            // Allow standard form submission to redirect to cart with flash message
            return;
        }
    });
}

/**
 * Handle AJAX removal of wishlist creation
 */
function handleCardRemoval(form) {
    const card = form.closest('.wishlist-card');
    const productId = form.querySelector('input[name="product_id"]')?.value;
    const csrfToken = getWishlistCsrfToken();

    if (!card || !productId) return;

    const formData = new FormData();
    formData.append('action', 'remove');
    formData.append('product_id', productId);
    formData.append('csrf_token', csrfToken);
    formData.append('ajax', '1');

    // Visual feedback
    card.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
    card.style.opacity = '0.4';

    fetch(form.action, {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            card.style.transform = 'scale(0.95)';
            setTimeout(() => {
                card.remove();

                // Update header wishlist badge
                const headerCount = document.getElementById('headerWishlistCount');
                if (headerCount && data.wishlist_count !== undefined) {
                    headerCount.innerText = data.wishlist_count;
                    headerCount.style.display = data.wishlist_count > 0 ? '' : 'none';
                }

                // Update counter on page
                const pageCounter = document.querySelector('.wishlist-counter');
                if (pageCounter && data.wishlist_count !== undefined) {
                    pageCounter.innerText = `(${data.wishlist_count} ${data.wishlist_count === 1 ? 'Creation' : 'Creations'})`;
                }

                // If grid is now empty, reload page to show empty state
                const remainingCards = document.querySelectorAll('.wishlist-card');
                if (!remainingCards.length) {
                    window.location.reload();
                }
            }, 300);
        } else {
            card.style.opacity = '1';
            alert(data.message || 'Unable to remove creation from wishlist.');
        }
    })
    .catch(() => {
        // Fallback to traditional form submission
        form.submit();
    });
}

