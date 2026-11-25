/**
 * =====================================================================
 * YATIYA COUTURE - Master Client-Side Application Script
 * Brand Vision: "ELEVATE YOUR EVERYDAY STYLE"
 * Pure Vanilla JavaScript (No Frameworks or Libraries)
 * =====================================================================
 */

document.addEventListener('DOMContentLoaded', function () {
    initHeroSlider();
    initCircularStoriesCarousel();
    initMobileDrawer();
    initMobileSearch();
    initQuickViewModal();
    initWishlistButtons();
    initFlashMessages();
    initHeaderScroll();
    initKeyboardAccessibility();
});

/**
 * 1. EDITORIAL HERO SLIDER
 * Supports autoplay, pause on hover, next/previous buttons, dot indicators, and touch swipe
 */
function initHeroSlider() {
    const sliderContainer = document.querySelector('.hero-slider-container');
    if (!sliderContainer) return;

    const slides = sliderContainer.querySelectorAll('.hero-slide');
    const prevBtn = document.querySelector('.hero-prev-btn');
    const nextBtn = document.querySelector('.hero-next-btn');
    const dotsContainer = document.querySelector('.hero-dots');

    if (slides.length <= 1) return;

    let currentSlide = 0;
    let slideInterval = null;
    const intervalDuration = 6500; // 6.5s per slide

    // Generate pagination dots
    if (dotsContainer) {
        dotsContainer.innerHTML = '';
        slides.forEach((_, idx) => {
            const dot = document.createElement('button');
            dot.className = `hero-dot ${idx === 0 ? 'active' : ''}`;
            dot.setAttribute('aria-label', `Navigate to slide ${idx + 1}`);
            dot.addEventListener('click', () => {
                goToSlide(idx);
                resetAutoplay();
            });
            dotsContainer.appendChild(dot);
        });
    }

    function goToSlide(index) {
        slides[currentSlide].classList.remove('active');
        const dots = dotsContainer ? dotsContainer.querySelectorAll('.hero-dot') : [];
        if (dots[currentSlide]) dots[currentSlide].classList.remove('active');

        currentSlide = (index + slides.length) % slides.length;

        slides[currentSlide].classList.add('active');
        if (dots[currentSlide]) dots[currentSlide].classList.add('active');
    }

    function nextSlide() {
        goToSlide(currentSlide + 1);
    }

    function prevSlide() {
        goToSlide(currentSlide - 1);
    }

    if (nextBtn) {
        nextBtn.addEventListener('click', () => {
            nextSlide();
            resetAutoplay();
        });
    }

    if (prevBtn) {
        prevBtn.addEventListener('click', () => {
            prevSlide();
            resetAutoplay();
        });
    }

    function startAutoplay() {
        slideInterval = setInterval(nextSlide, intervalDuration);
    }

    function resetAutoplay() {
        if (slideInterval) clearInterval(slideInterval);
        startAutoplay();
    }

    // Pause on hover
    sliderContainer.addEventListener('mouseenter', () => {
        if (slideInterval) clearInterval(slideInterval);
    });

    sliderContainer.addEventListener('mouseleave', () => {
        startAutoplay();
    });

    // Touch swipe support for mobile viewports
    let touchStartX = 0;
    let touchEndX = 0;

    sliderContainer.addEventListener('touchstart', (e) => {
        touchStartX = e.changedTouches[0].screenX;
    }, { passive: true });

    sliderContainer.addEventListener('touchend', (e) => {
        touchEndX = e.changedTouches[0].screenX;
        handleSwipe();
    }, { passive: true });

    function handleSwipe() {
        const threshold = 40;
        if (touchEndX < touchStartX - threshold) {
            nextSlide();
            resetAutoplay();
        } else if (touchEndX > touchStartX + threshold) {
            prevSlide();
            resetAutoplay();
        }
    }

    startAutoplay();
}

/**
 * 2. MOBILE NAVIGATION DRAWER
 */
function initMobileDrawer() {
    const openBtn = document.getElementById('mobileMenuOpenBtn');
    const drawer = document.getElementById('mobileDrawer');
    const closeBtn = document.getElementById('mobileDrawerCloseBtn');
    const overlay = document.getElementById('mobileDrawerOverlay');

    if (!drawer) return;

    function openDrawer() {
        drawer.style.display = 'block';
        setTimeout(() => drawer.classList.add('open'), 10);
        document.body.style.overflow = 'hidden';
        if (openBtn) openBtn.setAttribute('aria-expanded', 'true');
    }

    function closeDrawer() {
        drawer.classList.remove('open');
        document.body.style.overflow = '';
        if (openBtn) openBtn.setAttribute('aria-expanded', 'false');
        setTimeout(() => {
            drawer.style.display = 'none';
        }, 300);
    }

    if (openBtn) openBtn.addEventListener('click', openDrawer);
    if (closeBtn) closeBtn.addEventListener('click', closeDrawer);
    if (overlay) overlay.addEventListener('click', closeDrawer);

    // Close on Escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && drawer.classList.contains('open')) {
            closeDrawer();
        }
    });
}

/**
 * 3. MOBILE SEARCH BAR TOGGLE
 */
function initMobileSearch() {
    const toggleBtn = document.getElementById('mobileSearchToggleBtn');
    const searchDropdown = document.getElementById('mobileSearchDropdown');

    if (!toggleBtn || !searchDropdown) return;

    toggleBtn.addEventListener('click', () => {
        const isOpen = searchDropdown.style.display === 'block';
        if (isOpen) {
            searchDropdown.style.display = 'none';
        } else {
            searchDropdown.style.display = 'block';
            const input = searchDropdown.querySelector('input');
            if (input) input.focus();
        }
    });
}

/**
 * 4. QUICK VIEW MODAL COMPONENT
 */
function initQuickViewModal() {
    const modal = document.getElementById('quickViewModal');
    const closeBtn = document.getElementById('quickViewCloseBtn');
    if (!modal) return;

    const modalTitle = document.getElementById('quickViewTitle');
    const modalImage = document.getElementById('quickViewImage');
    const modalPrice = document.getElementById('quickViewPrice');
    const modalOriginalPrice = document.getElementById('quickViewOriginalPrice');
    const modalDiscount = document.getElementById('quickViewDiscount');
    const modalDesc = document.getElementById('quickViewDesc');
    const modalDetailsLink = document.getElementById('quickViewDetailsLink');

    // Attach click listeners to all Quick View buttons (using event delegation)
    document.body.addEventListener('click', function (e) {
        const btn = e.target.closest('.product-quickview-btn');
        if (!btn) return;

        e.preventDefault();
        e.stopPropagation();

        const card = btn.closest('.product-card');
        const title = btn.getAttribute('data-title') || (card ? card.querySelector('.product-title')?.innerText : 'Luxury Couture');
        const price = btn.getAttribute('data-price') || (card ? card.querySelector('.product-selling-price')?.innerText : '₹ 0.00');
        const originalPrice = btn.getAttribute('data-original-price') || (card ? card.querySelector('.product-original-price')?.innerText : '');
        const discount = btn.getAttribute('data-discount') || (card ? card.querySelector('.product-discount-label')?.innerText : '');
        const image = btn.getAttribute('data-image') || (card ? card.querySelector('.product-img')?.getAttribute('src') : '');
        const desc = btn.getAttribute('data-desc') || 'Crafted with master artisanal precision, pure handloom silks and heirloom embroidery from the Yatiya Couture private ateliers.';
        const link = btn.getAttribute('data-link') || (card ? card.querySelector('.product-title a')?.getAttribute('href') : '#');
        const stockStr = btn.getAttribute('data-stock');
        const stock = (stockStr !== null && stockStr !== '') ? parseInt(stockStr, 10) : 5;
        const rawSizes = btn.getAttribute('data-sizes');
        const rawColors = btn.getAttribute('data-colors');

        if (modalTitle) modalTitle.innerText = title;
        if (modalPrice) modalPrice.innerText = price;
        if (modalOriginalPrice) modalOriginalPrice.innerText = originalPrice;
        if (modalDiscount) modalDiscount.innerText = discount;
        if (modalImage) {
            modalImage.src = image;
            modalImage.alt = title;
        }
        if (modalDesc) modalDesc.innerText = desc;
        if (modalDetailsLink) modalDetailsLink.href = link;

        // Render Sizes
        const sizesContainer = document.getElementById('quickViewSizes');
        if (sizesContainer) {
            const sizes = rawSizes ? rawSizes.split(',').map(s => s.trim()).filter(Boolean) : ['S', 'M', 'L', 'XL'];
            sizesContainer.innerHTML = '';
            sizes.forEach((s, idx) => {
                const pill = document.createElement('button');
                pill.type = 'button';
                pill.className = `size-pill ${idx === 0 ? 'active' : ''}`;
                pill.innerText = s;
                pill.addEventListener('click', () => {
                    sizesContainer.querySelectorAll('.size-pill').forEach(p => p.classList.remove('active'));
                    pill.classList.add('active');
                });
                sizesContainer.appendChild(pill);
            });
        }

        // Render Colors
        const colorsGroup = document.getElementById('quickViewColorsGroup');
        const colorsContainer = document.getElementById('quickViewColors');
        if (colorsContainer && colorsGroup) {
            if (rawColors) {
                colorsGroup.style.display = 'block';
                const colors = rawColors.split(',').map(c => c.trim()).filter(Boolean);
                colorsContainer.innerHTML = '';
                colors.forEach((c) => {
                    const tag = document.createElement('span');
                    tag.className = 'quickview-color-tag';
                    tag.style.cssText = 'display:inline-block;padding:3px 8px;margin-right:6px;border:1px solid var(--border-subtle);font-size:0.75rem;border-radius:2px;background:var(--bg-primary);';
                    tag.innerText = c;
                    colorsContainer.appendChild(tag);
                });
            } else {
                colorsGroup.style.display = 'none';
            }
        }

        // Stock status
        const stockContainer = document.getElementById('quickViewStock');
        if (stockContainer) {
            if (stock <= 0) {
                stockContainer.innerHTML = '<span class="stock-pill stock-out"><span class="stock-dot"></span> Sold Out from Atelier</span>';
                const cartBtn = document.getElementById('quickViewAddToCartBtn');
                if (cartBtn) {
                    cartBtn.disabled = true;
                    cartBtn.style.opacity = '0.5';
                    cartBtn.style.pointerEvents = 'none';
                    cartBtn.innerText = 'Sold Out';
                }
            } else if (stock <= 3) {
                stockContainer.innerHTML = `<span class="stock-pill stock-available" style="background:#FEF3C7;color:#92400E;"><span class="stock-dot" style="background:#B45309;"></span> Limited Stock: Only ${stock} remaining</span>`;
                const cartBtn = document.getElementById('quickViewAddToCartBtn');
                if (cartBtn) {
                    cartBtn.disabled = false;
                    cartBtn.style.opacity = '1';
                    cartBtn.style.pointerEvents = '';
                    cartBtn.innerText = 'Add to Shopping Bag';
                }
            } else {
                stockContainer.innerHTML = '<span class="stock-pill stock-available"><span class="stock-dot"></span> In Atelier Stock &bull; Ready to Dispatch</span>';
                const cartBtn = document.getElementById('quickViewAddToCartBtn');
                if (cartBtn) {
                    cartBtn.disabled = false;
                    cartBtn.style.opacity = '1';
                    cartBtn.style.pointerEvents = '';
                    cartBtn.innerText = 'Add to Shopping Bag';
                }
            }
        }

        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    });

    function closeModal() {
        modal.style.display = 'none';
        document.body.style.overflow = '';
    }

    if (closeBtn) closeBtn.addEventListener('click', closeModal);

    modal.addEventListener('click', (e) => {
        if (e.target === modal) closeModal();
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && modal.style.display === 'flex') {
            closeModal();
        }
    });

    // Size selector pills inside modal
    const sizePills = modal.querySelectorAll('.size-pill');
    sizePills.forEach((pill) => {
        pill.addEventListener('click', () => {
            sizePills.forEach(p => p.classList.remove('active'));
            pill.classList.add('active');
        });
    });
}

/**
 * 5. WISHLIST BUTTONS INTERACTION
 * Micro-interaction feedback toast and visual state toggle
 */
function initWishlistButtons() {
    const toast = document.getElementById('wishlistToast');
    const toastMsg = document.getElementById('wishlistToastMsg');
    const headerCount = document.getElementById('headerWishlistCount');
    let toastTimeout = null;

    document.body.addEventListener('click', function (e) {
        const btn = e.target.closest('.product-wishlist-btn');
        if (!btn) return;

        e.preventDefault();
        e.stopPropagation();

        const isFavorited = btn.classList.toggle('active');
        const title = btn.closest('.product-card')?.querySelector('.product-title')?.innerText || 'Item';
        const prodId = btn.getAttribute('data-id');

        // Send AJAX toggle request
        if (prodId) {
            const metaToken = document.querySelector('meta[name="csrf-token"]')?.content;
            if (metaToken) {
                const fd = new FormData();
                fd.append('action', 'toggle');
                fd.append('product_id', prodId);
                fd.append('csrf_token', metaToken);
                fd.append('ajax', '1');

                fetch('actions/wishlist_actions.php', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: fd
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success && headerCount && data.wishlist_count !== undefined) {
                        headerCount.innerText = data.wishlist_count;
                        headerCount.style.display = data.wishlist_count > 0 ? '' : 'none';
                    }
                })
                .catch(() => {});
            }
        }

        // Update visual counter in header
        if (headerCount) {
            let current = parseInt(headerCount.innerText, 10) || 0;
            current = isFavorited ? current + 1 : Math.max(0, current - 1);
            headerCount.innerText = current;
            headerCount.style.display = current > 0 ? '' : 'none';
        }

        // Show feedback toast
        if (toast && toastMsg) {
            toastMsg.innerText = isFavorited 
                ? `"${title}" added to your Private Wishlist.`
                : `"${title}" removed from your Wishlist.`;
            toast.style.display = 'flex';

            if (toastTimeout) clearTimeout(toastTimeout);
            toastTimeout = setTimeout(() => {
                toast.style.display = 'none';
            }, 3500);
        }
    });
}

/**
 * 6. AUTO-DISMISS FLASH ALERTS
 */
function initFlashMessages() {
    const flashContainer = document.getElementById('flashMessages');
    if (!flashContainer) return;

    const alerts = flashContainer.querySelectorAll('.alert');
    alerts.forEach((alert) => {
        setTimeout(() => {
            alert.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
            alert.style.opacity = '0';
            alert.style.transform = 'translateX(20px)';
            setTimeout(() => alert.remove(), 400);
        }, 5000);
    });
}

/**
 * 7. HEADER SCROLL ELEVATION
 */
function initHeaderScroll() {
    const header = document.querySelector('.site-header');
    if (!header) return;

    window.addEventListener('scroll', () => {
        if (window.scrollY > 30) {
            header.style.boxShadow = '0 6px 24px rgba(36, 33, 30, 0.08)';
        } else {
            header.style.boxShadow = 'var(--shadow-soft)';
        }
    }, { passive: true });
}

/**
 * 8. ACCESSIBILITY & KEYBOARD NAVIGATION
 */
function initKeyboardAccessibility() {
    // Mega Menu keyboard accessibility
    const navItems = document.querySelectorAll('.nav-item.has-mega-menu');
    navItems.forEach((item) => {
        const link = item.querySelector('.nav-link');
        const menu = item.querySelector('.mega-menu');

        if (!link || !menu) return;

        link.addEventListener('focus', () => {
            link.setAttribute('aria-expanded', 'true');
        });

        item.addEventListener('focusout', (e) => {
            if (!item.contains(e.relatedTarget)) {
                link.setAttribute('aria-expanded', 'false');
            }
        });
    });
}

/**
 * 9. CIRCULAR CATEGORY STORIES CAROUSEL CONTROLLER
 * Smooth horizontal scroll with next/prev buttons
 */
function initCircularStoriesCarousel() {
    const track = document.getElementById('storiesTrack');
    const prevBtn = document.getElementById('storyPrevBtn');
    const nextBtn = document.getElementById('storyNextBtn');

    if (!track) return;

    if (prevBtn) {
        prevBtn.addEventListener('click', function () {
            track.scrollBy({ left: -260, behavior: 'smooth' });
        });
    }

    if (nextBtn) {
        nextBtn.addEventListener('click', function () {
            track.scrollBy({ left: 260, behavior: 'smooth' });
        });
    }
}
