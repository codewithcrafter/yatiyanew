/**
 * =====================================================================
 * YATIYA COUTURE — MASTER MOTION & INTERACTION ENGINE
 * Brand Vision: "ELEVATE YOUR EVERYDAY STYLE"
 * Pure Vanilla JavaScript (Zero External Animation Frameworks)
 * High-Performance: requestAnimationFrame, IntersectionObserver, Passive Listeners
 * =====================================================================
 */

(function () {
    'use strict';

    // Respect user's motion preferences
    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    document.addEventListener('DOMContentLoaded', function () {
        initSiteLoader();
        initScrollProgress();
        initScrollReveal();
        initLuxuryCursor();
        initMagneticElements();
        initCardTilt();
        initWishlistHeartPulse();
        initAddToCartMicroInteraction();
        initCartShippingMeter();
        initParallaxBanners();
        initAnimatedCounters();
        initInteractiveInputs();
        initTaxonomyMarquee();
    });

    /**
     * 1. CINEMATIC WEBSITE LOADING EXPERIENCE
     * Session-cached: Executes on first visit; instantly bypasses on repeat navigation
     */
    function initSiteLoader() {
        const loader = document.getElementById('siteLoader');
        if (!loader) return;

        // If user has already visited in this session, skip loader for instant response
        if (sessionStorage.getItem('yatiya_loader_seen') === '1' || prefersReducedMotion) {
            loader.style.display = 'none';
            document.body.classList.add('page-loaded');
            return;
        }

        // Animate initial entrance smoothly
        window.addEventListener('load', function () {
            setTimeout(function () {
                loader.classList.add('fade-out');
                document.body.classList.add('page-loaded');
                sessionStorage.setItem('yatiya_loader_seen', '1');

                setTimeout(function () {
                    loader.style.display = 'none';
                }, 450);
            }, 600);
        });

        // Fallback safety timeout if window.load takes unexpectedly long
        setTimeout(function () {
            if (loader.style.display !== 'none') {
                loader.classList.add('fade-out');
                document.body.classList.add('page-loaded');
                sessionStorage.setItem('yatiya_loader_seen', '1');
                setTimeout(function () {
                    loader.style.display = 'none';
                }, 450);
            }
        }, 1800);
    }

    /**
     * 2. SCROLL PROGRESS INDICATOR
     * Subtle 2.5px gold gradient bar fixed at top tracking viewport position
     */
    function initScrollProgress() {
        const progressBar = document.getElementById('scrollProgressBar');
        if (!progressBar) return;

        let ticking = false;

        function updateProgress() {
            const scrollTop = window.scrollY || document.documentElement.scrollTop;
            const docHeight = document.documentElement.scrollHeight - window.innerHeight;
            if (docHeight > 0) {
                const progress = Math.min(Math.max(scrollTop / docHeight, 0), 1);
                progressBar.style.transform = 'scaleX(' + progress + ')';
            }
            ticking = false;
        }

        window.addEventListener('scroll', function () {
            if (!ticking) {
                window.requestAnimationFrame(updateProgress);
                ticking = true;
            }
        }, { passive: true });

        updateProgress();
    }

    /**
     * 3. SCROLL REVEAL ENGINE
     * Uses browser-native IntersectionObserver for smooth staggered entrance
     */
    function initScrollReveal() {
        if (prefersReducedMotion) {
            document.querySelectorAll('[data-reveal], .product-card, .category-card, .style-story-card, .why-pillar, .testimonial-card').forEach(function (el) {
                el.classList.add('is-revealed');
            });
            return;
        }

        const revealElements = document.querySelectorAll(
            '[data-reveal], [data-reveal-stagger], .product-card, .category-card, .style-story-card, .why-pillar, .testimonial-card, .exclusive-collection-section, .newsletter-section'
        );

        if (!revealElements.length) return;

        const observerOptions = {
            root: null,
            rootMargin: '0px 0px -40px 0px',
            threshold: 0.1
        };

        const revealObserver = new IntersectionObserver(function (entries, observer) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    const target = entry.target;

                    // Handle staggered container
                    if (target.hasAttribute('data-reveal-stagger')) {
                        const children = target.children;
                        Array.from(children).forEach(function (child, index) {
                            child.style.transitionDelay = (index * 75) + 'ms';
                            child.classList.add('is-revealed');
                        });
                    }

                    target.classList.add('is-revealed');
                    observer.unobserve(target);
                }
            });
        }, observerOptions);

        revealElements.forEach(function (el) {
            revealObserver.observe(el);
        });
    }

    /**
     * 4. INTERACTIVE LUXURY CURSOR AURA
     * Fluid ambient light follower active only on desktop fine-pointer devices
     */
    function initLuxuryCursor() {
        const cursor = document.getElementById('luxuryCursorAura');
        if (!cursor) return;

        // Strictly disable on touch or coarse pointer devices
        const isFinePointer = window.matchMedia('(hover: hover) and (pointer: fine)').matches;
        if (!isFinePointer || prefersReducedMotion) {
            cursor.style.display = 'none';
            return;
        }

        let mouseX = -100;
        let mouseY = -100;
        let auraX = -100;
        let auraY = -100;
        let isVisible = false;

        window.addEventListener('mousemove', function (e) {
            mouseX = e.clientX;
            mouseY = e.clientY;
            if (!isVisible) {
                cursor.classList.add('active');
                isVisible = true;
            }
        }, { passive: true });

        window.addEventListener('mouseleave', function () {
            cursor.classList.remove('active');
            isVisible = false;
        });

        function renderCursor() {
            if (isVisible) {
                // Smooth linear interpolation (lerp)
                auraX += (mouseX - auraX) * 0.18;
                auraY += (mouseY - auraY) * 0.18;
                cursor.style.transform = 'translate3d(' + auraX + 'px, ' + auraY + 'px, 0)';
            }
            window.requestAnimationFrame(renderCursor);
        }
        window.requestAnimationFrame(renderCursor);

        // Expand aura on interactive elements
        const hoverSelectors = 'a, button, .product-card, .category-card, .story-item, .nav-link, .taxonomy-link, input, select, textarea, .product-wishlist-btn';
        document.addEventListener('mouseover', function (e) {
            if (e.target.closest(hoverSelectors)) {
                cursor.classList.add('cursor-hover');
            }
        });

        document.addEventListener('mouseout', function (e) {
            if (e.target.closest(hoverSelectors)) {
                cursor.classList.remove('cursor-hover');
            }
        });
    }

    /**
     * 5. MAGNETIC BUTTONS & ICONS
     * Subtle physics attraction when hovering near primary buttons
     */
    function initMagneticElements() {
        if (prefersReducedMotion || !window.matchMedia('(hover: hover) and (pointer: fine)').matches) return;

        const magneticItems = document.querySelectorAll('[data-magnetic], .btn-gold, .product-wishlist-btn, .story-arrow-btn, .hero-arrow-btn');

        magneticItems.forEach(function (el) {
            el.addEventListener('mousemove', function (e) {
                const rect = el.getBoundingClientRect();
                const centerX = rect.left + rect.width / 2;
                const centerY = rect.top + rect.height / 2;
                const deltaX = (e.clientX - centerX) * 0.22;
                const deltaY = (e.clientY - centerY) * 0.22;

                el.style.transform = 'translate3d(' + deltaX + 'px, ' + deltaY + 'px, 0)';
            });

            el.addEventListener('mouseleave', function () {
                el.style.transform = 'translate3d(0, 0, 0)';
                el.style.transition = 'transform 0.4s cubic-bezier(0.34, 1.56, 0.64, 1)';
            });

            el.addEventListener('mouseenter', function () {
                el.style.transition = 'none';
            });
        });
    }

    /**
     * 6. CARD 3D TILT & LIGHT GLARE
     * Editorial 3D perspective rotation on hover
     */
    function initCardTilt() {
        if (prefersReducedMotion || !window.matchMedia('(hover: hover) and (pointer: fine)').matches) return;

        const tiltCards = document.querySelectorAll('.category-card, .style-story-card, .luxury-card-interactive');

        tiltCards.forEach(function (card) {
            card.addEventListener('mousemove', function (e) {
                const rect = card.getBoundingClientRect();
                const x = e.clientX - rect.left;
                const y = e.clientY - rect.top;
                const centerX = rect.width / 2;
                const centerY = rect.height / 2;

                const rotateX = ((y - centerY) / centerY) * -4; // Max -4 to 4 deg
                const rotateY = ((x - centerX) / centerX) * 4;

                card.style.transform = 'perspective(900px) rotateX(' + rotateX.toFixed(2) + 'deg) rotateY(' + rotateY.toFixed(2) + 'deg) translateY(-4px)';
            });

            card.addEventListener('mouseleave', function () {
                card.style.transform = 'perspective(900px) rotateX(0deg) rotateY(0deg) translateY(0)';
                card.style.transition = 'transform 0.5s cubic-bezier(0.16, 1, 0.3, 1)';
            });

            card.addEventListener('mouseenter', function () {
                card.style.transition = 'transform 0.15s ease-out';
            });
        });
    }

    /**
     * 7. WISHLIST HEART BEAT BURST
     * Micro-burst celebration on saving creations
     */
    function initWishlistHeartPulse() {
        document.addEventListener('click', function (e) {
            const btn = e.target.closest('.product-wishlist-btn');
            if (!btn) return;

            btn.classList.add('heart-burst');
            setTimeout(function () {
                btn.classList.remove('heart-burst');
            }, 600);
        });
    }

    /**
     * 8. ADD TO CART CONFIRMATION FEEDBACK
     * Micro spinner followed by gold checkmark confirmation
     */
    function initAddToCartMicroInteraction() {
        document.addEventListener('click', function (e) {
            const btn = e.target.closest('.btn-add-to-bag, .btn-add-to-cart, .quickview-add-btn');
            if (!btn || btn.disabled) return;

            // Only add visual feedback if not already submitting
            if (!btn.classList.contains('is-loading')) {
                const originalText = btn.innerHTML;
                btn.classList.add('is-animating');

                setTimeout(function () {
                    btn.classList.remove('is-animating');
                }, 900);
            }
        });
    }

    /**
     * 9. CART SHIPPING PROGRESS METER ANIMATION
     */
    function initCartShippingMeter() {
        const meter = document.querySelector('.shipping-meter-progress');
        if (!meter) return;

        const targetWidth = meter.getAttribute('data-percent') || '0';
        meter.style.width = '0%';

        setTimeout(function () {
            meter.style.transition = 'width 1.2s cubic-bezier(0.16, 1, 0.3, 1)';
            meter.style.width = targetWidth + '%';
        }, 300);
    }

    /**
     * 10. PARALLAX DEPTH ON HERO & BANNERS
     */
    function initParallaxBanners() {
        if (prefersReducedMotion || !window.matchMedia('(hover: hover) and (pointer: fine)').matches) return;

        const parallaxSections = document.querySelectorAll('[data-parallax], .exclusive-collection-section');
        if (!parallaxSections.length) return;

        let ticking = false;

        function updateParallax() {
            const scrollY = window.scrollY;
            parallaxSections.forEach(function (section) {
                const rect = section.getBoundingClientRect();
                if (rect.top < window.innerHeight && rect.bottom > 0) {
                    const img = section.querySelector('img, .exclusive-media img');
                    if (img) {
                        const offset = (rect.top * -0.06);
                        img.style.transform = 'translate3d(0, ' + offset.toFixed(1) + 'px, 0) scale(1.04)';
                    }
                }
            });
            ticking = false;
        }

        window.addEventListener('scroll', function () {
            if (!ticking) {
                window.requestAnimationFrame(updateParallax);
                ticking = true;
            }
        }, { passive: true });
    }

    /**
     * 11. ANIMATED NUMBER COUNTERS
     */
    function initAnimatedCounters() {
        const counters = document.querySelectorAll('[data-counter]');
        if (!counters.length) return;

        const counterObserver = new IntersectionObserver(function (entries, observer) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    const el = entry.target;
                    const target = parseInt(el.getAttribute('data-counter'), 10) || 0;
                    const suffix = el.getAttribute('data-counter-suffix') || '';
                    const duration = 1400;
                    const startTime = performance.now();

                    function count(now) {
                        const elapsed = now - startTime;
                        const progress = Math.min(elapsed / duration, 1);
                        // Ease out cubic
                        const ease = 1 - Math.pow(1 - progress, 3);
                        const current = Math.floor(ease * target);
                        el.textContent = current.toLocaleString() + suffix;

                        if (progress < 1) {
                            window.requestAnimationFrame(count);
                        } else {
                            el.textContent = target.toLocaleString() + suffix;
                        }
                    }

                    window.requestAnimationFrame(count);
                    observer.unobserve(el);
                }
            });
        }, { threshold: 0.3 });

        counters.forEach(function (c) {
            counterObserver.observe(c);
        });
    }

    /**
     * 12. LUXURY INPUT FOCUS GLOW & MICRO-INTERACTION
     */
    function initInteractiveInputs() {
        const inputs = document.querySelectorAll('input, select, textarea');
        inputs.forEach(function (input) {
            input.addEventListener('focus', function () {
                const wrap = input.closest('.form-group, .header-search-wrapper, .newsletter-input-group');
                if (wrap) wrap.classList.add('is-focused');
            });
            input.addEventListener('blur', function () {
                const wrap = input.closest('.form-group, .header-search-wrapper, .newsletter-input-group');
                if (wrap) wrap.classList.remove('is-focused');
            });
        });
    }

    /**
     * 13. CONTINUOUS LUXURY TAXONOMY MARQUEE TICKER
     * Seamless infinite marquee with programmatic duplication & pause-on-hover
     */
    function initTaxonomyMarquee() {
        const bar = document.getElementById('luxuryTaxonomyBar');
        const track = document.getElementById('taxonomyBarTrack');
        const inner = document.getElementById('taxonomyBarInner');
        if (!bar || !track || !inner) return;

        if (prefersReducedMotion) {
            bar.classList.add('is-reduced-motion');
            return;
        }

        // Programmatically clone content for seamless infinite looping
        const clone = inner.cloneNode(true);
        clone.id = 'taxonomyBarInnerClone';
        clone.setAttribute('aria-hidden', 'true');
        // Prevent keyboard tab navigation from stopping twice on identical links
        clone.querySelectorAll('a').forEach(function (link) {
            link.setAttribute('tabindex', '-1');
            link.setAttribute('aria-hidden', 'true');
        });
        track.appendChild(clone);

        // Start continuous movement
        track.classList.add('is-animating');

        // Pause on pointer hover & keyboard focus
        bar.addEventListener('mouseenter', function () {
            track.style.animationPlayState = 'paused';
        });

        bar.addEventListener('mouseleave', function () {
            track.style.animationPlayState = 'running';
        });

        bar.addEventListener('focusin', function () {
            track.style.animationPlayState = 'paused';
        });

        bar.addEventListener('focusout', function () {
            track.style.animationPlayState = 'running';
        });

        // Visibility observer: only run animation when in or near viewport for maximum battery & CPU efficiency
        if ('IntersectionObserver' in window) {
            const visibilityObserver = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) {
                        track.style.animationPlayState = 'running';
                    } else {
                        track.style.animationPlayState = 'paused';
                    }
                });
            }, { threshold: 0 });
            visibilityObserver.observe(bar);
        }
    }

})();

