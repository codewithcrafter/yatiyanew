/**
 * YATIYA COUTURE - Luxury Checkout & Razorpay Integration
 * Vanilla JavaScript implementation for multi-step interaction,
 * address selection, PIN validation, and Razorpay standard checkout modal.
 */

document.addEventListener('DOMContentLoaded', function () {
    // 0. Dismiss any site loader immediately on checkout page to prevent stuck overlay
    const siteLoader = document.getElementById('siteLoader');
    if (siteLoader) {
        siteLoader.style.display = 'none';
    }
    document.body.classList.add('page-loaded');

    const checkoutForm = document.getElementById('checkoutForm');
    const placeOrderBtn = document.getElementById('placeOrderBtn');
    const billingCheckbox = document.getElementById('billingSameAsShipping');
    const billingSection = document.getElementById('billingAddressSection');
    const addressCards = document.querySelectorAll('.address-card-choice');
    const paymentCards = document.querySelectorAll('.payment-method-card');

    // If an address card is pre-selected on load, ensure shipping inputs are populated
    const preSelectedCard = document.querySelector('.address-card-choice.selected');
    if (preSelectedCard) {
        populateAddressFromCard(preSelectedCard);
    }

    // 1. Address Selection Cards
    if (addressCards.length > 0) {
        addressCards.forEach(card => {
            card.addEventListener('click', function () {
                addressCards.forEach(c => c.classList.remove('selected'));
                this.classList.add('selected');
                const radio = this.querySelector('input[type="radio"]');
                if (radio) {
                    radio.checked = true;
                    // Populate shipping fields if data attributes exist
                    populateAddressFromCard(this);
                }
            });
        });
    }

    function populateAddressFromCard(card) {
        const name = card.dataset.name || '';
        const phone = card.dataset.phone || '';
        const line1 = card.dataset.line1 || '';
        const line2 = card.dataset.line2 || '';
        const landmark = card.dataset.landmark || '';
        const city = card.dataset.city || '';
        const state = card.dataset.state || '';
        const postal = card.dataset.postal || '';

        setInputValue('shipping_full_name', name);
        setInputValue('shipping_phone', phone);
        setInputValue('shipping_address_line1', line1);
        setInputValue('shipping_address_line2', line2);
        setInputValue('shipping_landmark', landmark);
        setInputValue('shipping_city', city);
        setInputValue('shipping_state', state);
        setInputValue('shipping_postal_code', postal);
    }

    function setInputValue(id, val) {
        const el = document.getElementById(id);
        if (el) el.value = val;
    }

    // Toggle New Address Form
    const toggleNewAddressBtn = document.getElementById('toggleNewAddressBtn');
    const newAddressForm = document.getElementById('newAddressForm');
    if (toggleNewAddressBtn && newAddressForm) {
        toggleNewAddressBtn.addEventListener('click', function (e) {
            e.preventDefault();
            const isHidden = newAddressForm.style.display === 'none';
            newAddressForm.style.display = isHidden ? 'block' : 'none';
            this.textContent = isHidden ? '- Use Existing Saved Address' : '+ Add New Atelier Address';
            if (isHidden) {
                addressCards.forEach(c => c.classList.remove('selected'));
                const checkedRadio = document.querySelector('input[name="selected_address_id"]:checked');
                if (checkedRadio) checkedRadio.checked = false;
            }
        });
    }

    // 2. Billing Address Same as Shipping Toggle
    if (billingCheckbox && billingSection) {
        billingCheckbox.addEventListener('change', function () {
            billingSection.style.display = this.checked ? 'none' : 'block';
        });
    }

    // 3. Payment Method Card Selection
    if (paymentCards.length > 0) {
        paymentCards.forEach(card => {
            if (card.classList.contains('disabled')) return;

            card.addEventListener('click', function () {
                paymentCards.forEach(c => c.classList.remove('selected'));
                this.classList.add('selected');
                const radio = this.querySelector('input[type="radio"]');
                if (radio) radio.checked = true;
            });
        });
    }

    // 4. Live PIN Code Validation
    const pinInput = document.getElementById('shipping_postal_code');
    const pinStatus = document.getElementById('pinCodeStatus');
    if (pinInput && pinStatus) {
        pinInput.addEventListener('input', function () {
            const clean = this.value.replace(/\D/g, '');
            this.value = clean;
            if (clean.length === 6) {
                pinStatus.textContent = '✓ Valid PIN code format';
                pinStatus.style.color = '#2E7D32';
            } else if (clean.length > 0) {
                pinStatus.textContent = 'Requires 6 digits';
                pinStatus.style.color = '#C6A15B';
            } else {
                pinStatus.textContent = '';
            }
        });
    }

    // 5. Checkout Form Submission & Razorpay Trigger
    if (checkoutForm) {
        checkoutForm.addEventListener('submit', function (e) {
            e.preventDefault();

            // Client-side quick check
            const fullName = document.getElementById('shipping_full_name')?.value.trim();
            const phone = document.getElementById('shipping_phone')?.value.trim();
            const line1 = document.getElementById('shipping_address_line1')?.value.trim();
            const city = document.getElementById('shipping_city')?.value.trim();
            const state = document.getElementById('shipping_state')?.value.trim();
            const pin = document.getElementById('shipping_postal_code')?.value.trim();

            if (!fullName || !phone || !line1 || !city || !state || !pin) {
                showCheckoutAlert('Please complete all mandatory delivery address fields (*).', 'error');
                return;
            }

            if (placeOrderBtn) {
                placeOrderBtn.disabled = true;
                placeOrderBtn.innerHTML = '<span class="spinner" style="display:inline-block;width:14px;height:14px;border:2px solid #FFF;border-top-color:transparent;border-radius:50%;animation:spin 0.6s linear infinite;margin-right:8px;"></span> Curating Your Order...';
            }

            const formData = new FormData(checkoutForm);
            formData.append('action', 'place_order');

            const baseUrl = checkoutForm.dataset.actionUrl || 'actions/checkout_actions.php';

            fetch(baseUrl, {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (!data.success) {
                    showCheckoutAlert(data.message || 'Unable to place order. Please review your details.', 'error');
                    if (data.redirect) {
                        setTimeout(() => window.location.href = data.redirect, 1200);
                    } else {
                        resetPlaceOrderButton();
                    }
                    return;
                }

                if (data.payment_method === 'cod') {
                    window.location.href = data.redirect;
                    return;
                }

                if (data.requires_payment && data.razorpay) {
                    launchRazorpayModal(data.razorpay, data.order_id, baseUrl, formData.get('csrf_token'));
                } else if (data.redirect) {
                    window.location.href = data.redirect;
                }
            })
            .catch(err => {
                console.error('Checkout error:', err);
                showCheckoutAlert('A communication error occurred. Please try again.', 'error');
                resetPlaceOrderButton();
            });
        });
    }

    function resetPlaceOrderButton() {
        if (placeOrderBtn) {
            placeOrderBtn.disabled = false;
            placeOrderBtn.innerHTML = 'Place Order & Pay <span style="font-size: 16px;">&rarr;</span>';
        }
    }

    function showCheckoutAlert(msg, type) {
        const box = document.getElementById('checkoutAlertBox');
        if (box) {
            box.style.display = 'block';
            box.className = 'alert alert-' + (type === 'error' ? 'danger' : 'success');
            box.textContent = msg;
            box.scrollIntoView({ behavior: 'smooth', block: 'center' });
        } else {
            alert(msg);
        }
    }

    // 6. Razorpay Standard Modal or Test Simulator
    function launchRazorpayModal(rpConfig, orderId, actionUrl, csrfToken) {
        if (typeof Razorpay !== 'undefined') {
            const options = {
                key: rpConfig.key,
                amount: rpConfig.amount,
                currency: rpConfig.currency,
                name: rpConfig.name || 'YATIYA COUTURE',
                description: rpConfig.description || 'Luxury Purchase',
                order_id: rpConfig.order_id,
                prefill: rpConfig.prefill || {},
                notes: rpConfig.notes || {},
                theme: rpConfig.theme || { color: '#C6A15B' },
                handler: function (response) {
                    // Send to backend for cryptographic signature verification
                    verifyPaymentSignature(orderId, response, actionUrl, csrfToken);
                },
                modal: {
                    ondismiss: function () {
                        recordPaymentFailure(orderId, 'Patron dismissed payment window without completion.', actionUrl, csrfToken);
                    }
                }
            };
            const rzp = new Razorpay(options);
            rzp.on('payment.failed', function (response) {
                const reason = response.error ? response.error.description : 'Payment gateway declined transaction.';
                recordPaymentFailure(orderId, reason, actionUrl, csrfToken);
            });
            rzp.open();
        } else {
            // Test Mode Fallback Simulator (e.g. offline development or blocked external CDN)
            renderSimulatedPaymentModal(rpConfig, orderId, actionUrl, csrfToken);
        }
    }

    function verifyPaymentSignature(orderId, rpResponse, actionUrl, csrfToken) {
        const verifyData = new FormData();
        verifyData.append('action', 'verify_payment');
        verifyData.append('order_id', orderId);
        verifyData.append('razorpay_order_id', rpResponse.razorpay_order_id || '');
        verifyData.append('razorpay_payment_id', rpResponse.razorpay_payment_id || '');
        verifyData.append('razorpay_signature', rpResponse.razorpay_signature || '');
        verifyData.append('csrf_token', csrfToken);

        fetch(actionUrl, {
            method: 'POST',
            body: verifyData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success && data.redirect) {
                window.location.href = data.redirect;
            } else {
                showCheckoutAlert(data.message || 'Payment verification failed.', 'error');
                if (data.redirect) {
                    setTimeout(() => window.location.href = data.redirect, 2000);
                } else {
                    resetPlaceOrderButton();
                }
            }
        })
        .catch(err => {
            console.error('Verification error:', err);
            showCheckoutAlert('Payment recorded, reconciling status...', 'info');
        });
    }

    function recordPaymentFailure(orderId, reason, actionUrl, csrfToken) {
        const failData = new FormData();
        failData.append('action', 'record_failure');
        failData.append('order_id', orderId);
        failData.append('reason', reason);
        failData.append('csrf_token', csrfToken);

        fetch(actionUrl, {
            method: 'POST',
            body: failData
        })
        .then(res => res.json())
        .then(data => {
            if (data.redirect) {
                window.location.href = data.redirect;
            } else {
                resetPlaceOrderButton();
            }
        })
        .catch(() => resetPlaceOrderButton());
    }

    // Simulated Test Payment Modal (Used if offline / test environment)
    function renderSimulatedPaymentModal(rpConfig, orderId, actionUrl, csrfToken) {
        const overlay = document.createElement('div');
        overlay.id = 'simulatedPaymentModal';
        overlay.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(36,33,30,0.7);display:flex;align-items:center;justify-content:center;z-index:99999;';

        overlay.innerHTML = `
            <div style="background:#FFF;padding:32px;border-radius:4px;max-width:440px;width:90%;box-shadow:0 10px 40px rgba(0,0,0,0.3);text-align:center;">
                <div style="font-family:var(--font-heading);font-size:22px;color:#24211E;margin-bottom:8px;">Yatiya Luxury Checkout</div>
                <div style="font-size:12px;color:#777168;margin-bottom:20px;">Standard Gateway Sandbox Fixture</div>
                <div style="background:#F7F4ED;padding:16px;border-radius:3px;margin-bottom:20px;text-align:left;font-size:13px;">
                    <div><strong>Amount:</strong> ₹${(rpConfig.amount / 100).toLocaleString('en-IN', {minimumFractionDigits: 2})}</div>
                    <div><strong>Gateway Order:</strong> ${rpConfig.order_id}</div>
                </div>
                <div style="display:flex;flex-direction:column;gap:10px;">
                    <button id="simSuccessBtn" style="padding:14px;background:#C6A15B;color:#FFF;border:none;border-radius:2px;font-weight:600;cursor:pointer;text-transform:uppercase;letter-spacing:1px;font-size:12px;">Simulate Successful Payment</button>
                    <button id="simFailBtn" style="padding:12px;background:#EEEEEE;color:#4A453E;border:none;border-radius:2px;font-weight:600;cursor:pointer;font-size:12px;">Simulate Payment Failure</button>
                    <button id="simCancelBtn" style="padding:8px;background:transparent;color:#777168;border:none;cursor:pointer;font-size:11px;">Dismiss</button>
                </div>
            </div>
        `;

        document.body.appendChild(overlay);

        document.getElementById('simSuccessBtn').onclick = function () {
            overlay.remove();
            // In simulation, generate a simulated payment ID
            const simPaymentId = 'pay_sim_' + Math.random().toString(36).substring(2, 12);
            // We can post directly to verify or call helper
            const verifyData = new FormData();
            verifyData.append('action', 'verify_payment');
            verifyData.append('order_id', orderId);
            verifyData.append('razorpay_order_id', rpConfig.order_id);
            verifyData.append('razorpay_payment_id', simPaymentId);
            verifyData.append('csrf_token', csrfToken);

            fetch(actionUrl, {
                method: 'POST',
                body: verifyData
            })
            .then(res => res.json())
            .then(data => {
                if (data.redirect) {
                    window.location.href = data.redirect;
                } else {
                    window.location.href = 'order-success.php?order=' + encodeURIComponent(data.order_number || '');
                }
            })
            .catch(() => {
                window.location.reload();
            });
        };

        document.getElementById('simFailBtn').onclick = function () {
            overlay.remove();
            recordPaymentFailure(orderId, 'Patron simulated failed card authentication in sandbox.', actionUrl, csrfToken);
        };

        document.getElementById('simCancelBtn').onclick = function () {
            overlay.remove();
            recordPaymentFailure(orderId, 'Transaction dismissed by patron.', actionUrl, csrfToken);
        };
    }
});

