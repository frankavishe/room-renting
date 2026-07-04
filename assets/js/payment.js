// assets/js/payment.js
// Handles the full mobile money payment flow as specified in the SRS.
// Flow:
//   1. User clicks "Book & Pay" → booking created via API
//   2. Payment modal opens → user enters phone
//   3. triggerMobilePayment() sends STK Push request to our backend
//   4. startStatusPolling() checks every 4 seconds until status changes

// ----------------------------------------------------------------
// STEP 1 + 2: Create a booking then open the payment modal
// ----------------------------------------------------------------
async function initiateBooking(roomId, pricePerMonth) {
    if (!Auth.isLoggedIn()) {
        window.location.href = 'login.html';
        return;
    }

    const startDate = document.getElementById('start-date')?.value;
    const endDate   = document.getElementById('end-date')?.value;

    if (!startDate || !endDate) {
        showAlert('booking-alert', 'Please select start and end dates.');
        return;
    }

    const bookingBtn = document.getElementById('book-btn');
    bookingBtn.disabled    = true;
    bookingBtn.textContent = 'Creating booking...';

    try {
        const res = await fetch('api/bookings/create.php', {
            method:  'POST',
            headers: {
                'Content-Type':  'application/json',
                'Authorization': 'Bearer ' + Auth.getToken()
            },
            body: JSON.stringify({ room_id: roomId, start_date: startDate, end_date: endDate })
        });
        const data = await res.json();

        if (!res.ok) {
            showAlert('booking-alert', data.message);
            bookingBtn.disabled    = false;
            bookingBtn.textContent = 'Book & Pay';
            return;
        }

        // Booking created — open the payment modal.
        openPaymentModal(data.booking_id, pricePerMonth);

    } catch (err) {
        showAlert('booking-alert', 'Network error. Please try again.');
        bookingBtn.disabled    = false;
        bookingBtn.textContent = 'Book & Pay';
    }
}

// ----------------------------------------------------------------
// STEP 2: Open payment modal
// ----------------------------------------------------------------
function openPaymentModal(bookingId, amount) {
    const modal = document.getElementById('payment-modal');
    if (!modal) return;

    document.getElementById('modal-amount').textContent = formatTZS(amount);
    modal.dataset.bookingId = bookingId;
    modal.dataset.amount    = amount;
    modal.classList.add('active');
}

function closePaymentModal() {
    const modal = document.getElementById('payment-modal');
    if (modal) modal.classList.remove('active');
}

// Provider selection (MPESA, TIGOPESA, AIRTEL, HALOPESA)
function selectProvider(btn, provider) {
    document.querySelectorAll('.provider-btn').forEach(b => b.classList.remove('selected'));
    btn.classList.add('selected');
    document.getElementById('selected-provider').value = provider;
}

// ----------------------------------------------------------------
// STEP 3: Trigger STK Push — from SRS Section 5
// ----------------------------------------------------------------
async function triggerMobilePayment(bookingId, phoneNum, totalCost) {
    const payload = {
        booking_id: bookingId,
        phone:      phoneNum,
        amount:     totalCost
    };

    // Show a spinner so the user knows something is happening.
    const payBtn = document.getElementById('pay-btn');
    payBtn.disabled   = true;
    payBtn.innerHTML  = '<span class="spinner"></span> Sending prompt...';

    try {
        const response = await fetch('api/payments/initiate.php', {
            method:  'POST',
            headers: {
                'Content-Type':  'application/json',
                'Authorization': 'Bearer ' + Auth.getToken()
            },
            body: JSON.stringify(payload)
        });
        const data = await response.json();

        if (data.status === 'initiated') {
            // Show the PIN prompt waiting message.
            document.getElementById('payment-form').classList.add('hidden');
            document.getElementById('payment-waiting').classList.remove('hidden');

            // STEP 4: Start polling for payment confirmation.
            // We pass the temp_reference so we can query its status.
            startStatusPolling(data.temp_reference);
        } else {
            showAlert('payment-alert', 'Payment initiation failed: ' + data.message);
            payBtn.disabled  = false;
            payBtn.innerHTML = 'Pay Now';
        }

    } catch (err) {
        console.error('Network communication pipeline fault:', err);
        showAlert('payment-alert', 'Network error. Please try again.');
        payBtn.disabled  = false;
        payBtn.innerHTML = 'Pay Now';
    }
}

// ----------------------------------------------------------------
// STEP 4: Poll for payment status every 4 seconds
// Stops when status is 'successful' or 'failed', or after 3 minutes.
// ----------------------------------------------------------------
function startStatusPolling(reference) {
    let attempts = 0;
    const MAX_ATTEMPTS = 45;   // 45 × 4s = 3 minutes max wait
    const INTERVAL_MS  = 4000;

    const statusEl = document.getElementById('polling-status');

    const poll = setInterval(async () => {
        attempts++;

        try {
            const res  = await fetch(`api/payments/status.php?reference=${reference}`);
            const data = await res.json();

            if (data.status === 'successful') {
                clearInterval(poll);
                onPaymentSuccess();
            } else if (data.status === 'failed') {
                clearInterval(poll);
                onPaymentFailed();
            } else {
                // Still pending — update the dots animation to show we're waiting.
                if (statusEl) {
                    const dots = '.'.repeat((attempts % 3) + 1);
                    statusEl.textContent = `Waiting for confirmation${dots}`;
                }
            }
        } catch (err) {
            console.error('Polling error:', err);
        }

        // Timeout — stop polling after 3 minutes.
        if (attempts >= MAX_ATTEMPTS) {
            clearInterval(poll);
            if (statusEl) statusEl.textContent = 'Timed out. Please check My Bookings.';
        }
    }, INTERVAL_MS);
}

// ----------------------------------------------------------------
// Payment outcomes
// ----------------------------------------------------------------
function onPaymentSuccess() {
    document.getElementById('payment-waiting').classList.add('hidden');
    document.getElementById('payment-success').classList.remove('hidden');
    // Redirect to bookings page after 3 seconds.
    setTimeout(() => window.location.href = 'my-bookings.html', 3000);
}

function onPaymentFailed() {
    document.getElementById('payment-waiting').classList.add('hidden');
    document.getElementById('payment-form').classList.remove('hidden');
    showAlert('payment-alert', 'Payment failed or was cancelled. Please try again.', 'danger');
    const payBtn      = document.getElementById('pay-btn');
    payBtn.disabled   = false;
    payBtn.innerHTML  = 'Pay Now';
}

// ----------------------------------------------------------------
// PAY BUTTON click handler — wires the form to triggerMobilePayment()
// ----------------------------------------------------------------
document.addEventListener('DOMContentLoaded', () => {
    const paySubmitBtn = document.getElementById('pay-btn');
    if (paySubmitBtn) {
        paySubmitBtn.addEventListener('click', () => {
            const modal   = document.getElementById('payment-modal');
            const phone   = document.getElementById('payment-phone').value.trim();
            const amount  = modal.dataset.amount;
            const booking = modal.dataset.bookingId;

            if (!phone) {
                showAlert('payment-alert', 'Please enter your mobile money phone number.');
                return;
            }

            triggerMobilePayment(booking, phone, amount);
        });
    }
});
