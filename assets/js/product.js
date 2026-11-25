/**
 * =====================================================================
 * YATIYA COUTURE - Product Details Dossier Client Script
 * Brand Vision: "ELEVATE YOUR EVERYDAY STYLE"
 * Pure Vanilla JavaScript (No Frameworks)
 * =====================================================================
 */

document.addEventListener('DOMContentLoaded', function () {
    initGalleryThumbnails();
    initImageZoom();
    initVariantSelector();
    initQuantityStepper();
    initDossierTabs();
    initPageWishlist();
});

/**
 * 1. GALLERY THUMBNAIL SWITCHER
 */
function initGalleryThumbnails() {
    const thumbs = document.querySelectorAll('.gallery-thumb-btn');
    const mainImg = document.getElementById('productMainImg');
    const zoomContainer = document.getElementById('zoomContainer');

    if (!thumbs.length || !mainImg) return;

    thumbs.forEach((thumb) => {
        thumb.addEventListener('click', () => {
            thumbs.forEach(t => t.classList.remove('active'));
            thumb.classList.add('active');

            const fullImg = thumb.getAttribute('data-full-image');
            if (fullImg) {
                mainImg.src = fullImg;
                mainImg.setAttribute('data-zoom', fullImg);
                if (zoomContainer) {
                    const zoomResult = document.getElementById('zoomResult');
                    if (zoomResult) {
                        zoomResult.style.backgroundImage = `url('${fullImg}')`;
                    }
                }
            }
        });
    });
}

/**
 * 2. IMAGE ZOOM LENS & MAGNIFIER
 */
function initImageZoom() {
    const container = document.getElementById('zoomContainer');
    const img = document.getElementById('productMainImg');
    const lens = document.getElementById('zoomLens');
    const result = document.getElementById('zoomResult');

    if (!container || !img || !lens || !result) return;

    // Desktop hover only
    if (window.innerWidth < 1025) return;

    function getCursorPos(e) {
        const a = img.getBoundingClientRect();
        let x = e.pageX - a.left - window.pageXOffset;
        let y = e.pageY - a.top - window.pageYOffset;
        return { x: x, y: y };
    }

    function moveLens(e) {
        e.preventDefault();
        const pos = getCursorPos(e);
        let x = pos.x - (lens.offsetWidth / 2);
        let y = pos.y - (lens.offsetHeight / 2);

        // Prevent lens from going outside image
        if (x > img.width - lens.offsetWidth) { x = img.width - lens.offsetWidth; }
        if (x < 0) { x = 0; }
        if (y > img.height - lens.offsetHeight) { y = img.height - lens.offsetHeight; }
        if (y < 0) { y = 0; }

        lens.style.left = x + 'px';
        lens.style.top = y + 'px';

        const cx = result.offsetWidth / lens.offsetWidth;
        const cy = result.offsetHeight / lens.offsetHeight;

        result.style.backgroundPosition = `-${x * cx}px -${y * cy}px`;
        result.style.backgroundSize = `${img.width * cx}px ${img.height * cy}px`;
    }

    container.addEventListener('mouseenter', () => {
        const zoomSrc = img.getAttribute('data-zoom') || img.src;
        result.style.backgroundImage = `url('${zoomSrc}')`;
        lens.style.display = 'block';
        result.style.display = 'block';
    });

    container.addEventListener('mouseleave', () => {
        lens.style.display = 'none';
        result.style.display = 'none';
    });

    container.addEventListener('mousemove', moveLens);
}

/**
 * 3. INTERACTIVE VARIANT SELECTOR (Sizes & Colors)
 */
function initVariantSelector() {
    const variants = window.YATIYA_VARIANTS_DATA || [];
    const colorBtns = document.querySelectorAll('.color-pill-btn');
    const sizeBtns = document.querySelectorAll('.size-choice-btn');
    const selectedColorLabel = document.getElementById('selectedColorLabel');
    const selectedSizeLabel = document.getElementById('selectedSizeLabel');
    const variantIdInput = document.getElementById('selectedVariantId');
    const skuDisplay = document.getElementById('displaySku');
    const stockStatusText = document.getElementById('stockStatusText');
    const stockWrapper = document.getElementById('stockStatusWrapper');
    const addToBagBtn = document.getElementById('addToBagBtn');
    const buyNowBtn = document.getElementById('buyNowBtn');
    const qtyInput = document.getElementById('productQtyInput');

    let currentColor = colorBtns.length ? colorBtns[0].getAttribute('data-color') : '';
    let currentSize = sizeBtns.length ? sizeBtns[0].getAttribute('data-size') : '';

    function updateActiveVariant() {
        let matched = variants.find(v => {
            const matchColor = !currentColor || v.color === currentColor;
            const matchSize = !currentSize || v.size === currentSize;
            return matchColor && matchSize;
        });

        // Fallback to size only or color only if exact pairing not found
        if (!matched && variants.length) {
            matched = variants.find(v => v.size === currentSize) || variants[0];
        }

        if (matched) {
            if (variantIdInput) variantIdInput.value = matched.id;
            if (skuDisplay && matched.sku) skuDisplay.innerText = matched.sku;

            const isAvailable = matched.stock > 0;

            if (stockWrapper && stockStatusText) {
                if (isAvailable) {
                    stockWrapper.innerHTML = `
                        <span class="stock-pill stock-available">
                            <span class="stock-dot"></span>
                            <span id="stockStatusText">${matched.stock <= 3 ? `Limited Atelier Stock: Only ${matched.stock} creations remaining` : 'In Atelier Stock &bull; Ready for Expedited Dispatch'}</span>
                        </span>`;
                } else {
                    stockWrapper.innerHTML = `
                        <span class="stock-pill stock-out">
                            <span class="stock-dot"></span>
                            <span id="stockStatusText">Selected Size/Color Sold Out from Salon Archive</span>
                        </span>`;
                }
            }

            if (addToBagBtn) {
                addToBagBtn.disabled = !isAvailable;
                const btnText = addToBagBtn.querySelector('span');
                if (btnText) btnText.innerText = isAvailable ? 'Add to Shopping Bag' : 'Sold Out';
            }

            if (buyNowBtn) {
                buyNowBtn.disabled = !isAvailable;
            }

            if (qtyInput) {
                qtyInput.disabled = !isAvailable;
                qtyInput.max = Math.max(1, matched.stock);
                if (parseInt(qtyInput.value, 10) > matched.stock) {
                    qtyInput.value = Math.max(1, matched.stock);
                }
            }
        }
    }

    colorBtns.forEach((btn) => {
        btn.addEventListener('click', () => {
            colorBtns.forEach(b => b.classList.remove('selected'));
            btn.classList.add('selected');
            currentColor = btn.getAttribute('data-color');
            if (selectedColorLabel) selectedColorLabel.innerText = currentColor;
            updateActiveVariant();
        });
    });

    sizeBtns.forEach((btn) => {
        btn.addEventListener('click', () => {
            sizeBtns.forEach(b => b.classList.remove('selected'));
            btn.classList.add('selected');
            currentSize = btn.getAttribute('data-size');
            if (selectedSizeLabel) selectedSizeLabel.innerText = currentSize;
            updateActiveVariant();
        });
    });

    if (variants.length) {
        updateActiveVariant();
    }
}

/**
 * 4. QUANTITY STEPPER
 */
function initQuantityStepper() {
    const decBtn = document.getElementById('qtyDecrementBtn');
    const incBtn = document.getElementById('qtyIncrementBtn');
    const input = document.getElementById('productQtyInput');

    if (!input || !decBtn || !incBtn) return;

    decBtn.addEventListener('click', () => {
        let val = parseInt(input.value, 10) || 1;
        if (val > 1) {
            input.value = val - 1;
        }
    });

    incBtn.addEventListener('click', () => {
        let val = parseInt(input.value, 10) || 1;
        const max = parseInt(input.max, 10) || 99;
        if (val < max) {
            input.value = val + 1;
        }
    });
}

/**
 * 5. DOSSIER TABS
 */
function initDossierTabs() {
    const tabBtns = document.querySelectorAll('.dossier-tab-btn');
    const tabPanels = document.querySelectorAll('.tab-panel');

    if (!tabBtns.length) return;

    tabBtns.forEach((btn) => {
        btn.addEventListener('click', () => {
            const targetId = btn.getAttribute('data-tab');

            tabBtns.forEach(b => b.classList.remove('active'));
            tabPanels.forEach(p => p.classList.remove('active'));

            btn.classList.add('active');
            const targetPanel = document.getElementById(targetId);
            if (targetPanel) {
                targetPanel.classList.add('active');
            }
        });
    });
}

/**
 * 6. WISHLIST BUTTON ON PRODUCT PAGE
 */
function initPageWishlist() {
    const btn = document.getElementById('pageWishlistBtn');
    const toast = document.getElementById('wishlistToast');
    const toastMsg = document.getElementById('wishlistToastMsg');
    const label = document.getElementById('wishlistBtnLabel');
    const headerCount = document.getElementById('headerWishlistCount');
    let toastTimeout = null;

    if (!btn) return;

    btn.addEventListener('click', () => {
        const isFavorited = btn.classList.toggle('active');
        const prodId = btn.getAttribute('data-id');

        if (label) {
            label.innerText = isFavorited ? 'Saved in Private Wishlist' : 'Add to Private Wishlist';
        }

        // Send AJAX toggle request
        if (prodId) {
            const metaToken = document.querySelector('meta[name="csrf-token"]')?.content;
            const inputToken = document.querySelector('input[name="csrf_token"]')?.value;
            const token = metaToken || inputToken;

            if (token) {
                const fd = new FormData();
                fd.append('action', 'toggle');
                fd.append('product_id', prodId);
                fd.append('csrf_token', token);
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

        if (headerCount) {
            let current = parseInt(headerCount.innerText, 10) || 0;
            current = isFavorited ? current + 1 : Math.max(0, current - 1);
            headerCount.innerText = current;
            headerCount.style.display = current > 0 ? '' : 'none';
        }

        if (toast && toastMsg) {
            toastMsg.innerText = isFavorited 
                ? 'Piece saved to your Private Wishlist.'
                : 'Piece removed from your Wishlist.';
            toast.style.display = 'flex';

            if (toastTimeout) clearTimeout(toastTimeout);
            toastTimeout = setTimeout(() => {
                toast.style.display = 'none';
            }, 3500);
        }
    });
}

