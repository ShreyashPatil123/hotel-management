/**
 * Haven Hotel Real-Time Synchronization Engine
 * Handles fast event polling, instant cross-tab broadcast,
 * dynamic in-page DOM updates, soft audio chimes, and toast notifications.
 */
(function() {
    'use strict';

    // Read meta configuration
    const userRole = document.querySelector('meta[name="user-role"]')?.getAttribute('content') || 'guest';
    const currentUserId = parseInt(document.querySelector('meta[name="user-id"]')?.getAttribute('content') || '0', 10);
    const baseUrl = document.querySelector('meta[name="base-url"]')?.getAttribute('content') || '';
    const apiEventsUrl = baseUrl + 'api/events.php';
    const apiUpdateStatusUrl = baseUrl + 'api/update_booking_status.php';

    let lastEventId = -1;
    let isPolling = false;
    let pollInterval = 2000; // 2 seconds fast-poll
    let broadcastChannel = null;

    // Cross-tab broadcast channel
    if (typeof window.BroadcastChannel === 'function') {
        try {
            broadcastChannel = new BroadcastChannel('haven_hotel_realtime');
            broadcastChannel.onmessage = function(msg) {
                if (msg.data && msg.data.type === 'EVENT') {
                    handleEvent(msg.data.event, false);
                } else if (msg.data && msg.data.type === 'STATS' && userRole === 'admin') {
                    updateDashboardStats(msg.data.stats);
                }
            };
        } catch (e) {
            console.warn('BroadcastChannel not supported in this context', e);
        }
    }

    // Audio chime using Web Audio API
    function playChime(type) {
        try {
            const AudioContext = window.AudioContext || window.webkitAudioContext;
            if (!AudioContext) return;
            const ctx = new AudioContext();
            if (ctx.state === 'suspended') {
                ctx.resume();
            }

            const now = ctx.currentTime;
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();

            osc.type = 'sine';
            gain.gain.setValueAtTime(0.001, now);

            if (type === 'success' || type === 'confirmed') {
                // Happy upward chime: C5 -> G5
                osc.frequency.setValueAtTime(523.25, now);
                osc.frequency.exponentialRampToValueAtTime(783.99, now + 0.15);
                gain.gain.exponentialRampToValueAtTime(0.15, now + 0.05);
                gain.gain.exponentialRampToValueAtTime(0.001, now + 0.35);
            } else if (type === 'danger' || type === 'cancelled') {
                // Downward chime: F4 -> C4
                osc.frequency.setValueAtTime(349.23, now);
                osc.frequency.exponentialRampToValueAtTime(261.63, now + 0.18);
                gain.gain.exponentialRampToValueAtTime(0.15, now + 0.05);
                gain.gain.exponentialRampToValueAtTime(0.001, now + 0.35);
            } else {
                // Soft chime: E5
                osc.frequency.setValueAtTime(659.25, now);
                gain.gain.exponentialRampToValueAtTime(0.12, now + 0.05);
                gain.gain.exponentialRampToValueAtTime(0.001, now + 0.3);
            }

            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.start(now);
            osc.stop(now + 0.4);
        } catch (e) {
            // Audio context blocked or unsupported
        }
    }

    // Toast notification UI
    function showToast(title, message, type = 'info') {
        const container = document.getElementById('toast-container');
        if (!container) return;

        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        
        let icon = '🔔';
        if (type === 'success' || type === 'confirmed') icon = '✅';
        else if (type === 'danger' || type === 'cancelled') icon = '⚠️';
        else if (type === 'room') icon = '🏨';

        toast.innerHTML = `
            <span class="toast-icon">${icon}</span>
            <div class="toast-body">
                <strong>${escapeHtml(title)}</strong>
                <p>${escapeHtml(message)}</p>
            </div>
            <button type="button" class="toast-close" aria-label="Close">&times;</button>
        `;

        toast.querySelector('.toast-close').addEventListener('click', () => {
            toast.classList.add('toast-hiding');
            setTimeout(() => toast.remove(), 250);
        });

        container.appendChild(toast);

        // Auto remove after 5.5s
        setTimeout(() => {
            if (toast.parentElement) {
                toast.classList.add('toast-hiding');
                setTimeout(() => toast.remove(), 250);
            }
        }, 5500);
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function highlightElement(el) {
        if (!el) return;
        el.classList.remove('live-highlight');
        void el.offsetWidth; // trigger reflow
        el.classList.add('live-highlight');
        setTimeout(() => el.classList.remove('live-highlight'), 3000);
    }

    // Handle incoming real-time event
    function handleEvent(event, fromServer = true) {
        if (!event || !event.event_type) return;

        const payload = event.payload || {};
        const eventType = event.event_type;

        // 1. New Booking Created
        if (eventType === 'booking_created') {
            if (userRole === 'admin') {
                playChime('info');
                showToast(
                    'New Booking Request',
                    `${payload.guest_name || 'A guest'} reserved Room ${payload.room_number || ''} (${payload.room_type || ''}) for ₹${Number(payload.total_amount || 0).toLocaleString()}.`,
                    'info'
                );
                insertAdminBookingRow(payload);
                insertRecentBookingRow(payload);
            } else if (userRole === 'customer' && event.user_id === currentUserId) {
                // If customer is in another tab
                insertCustomerBookingRow(payload);
            }
        }

        // 2. Booking Status Updated (Approved/Confirmed, Cancelled, Completed)
        else if (eventType === 'booking_status_updated') {
            const bookingId = payload.booking_id;
            const newStatus = payload.status;

            if (userRole === 'admin') {
                updateAdminBookingStatus(bookingId, newStatus);
                updateRecentBookingStatus(bookingId, newStatus);
            } else if (userRole === 'customer' && (event.user_id === currentUserId || payload.user_id === currentUserId)) {
                updateCustomerBookingStatus(bookingId, newStatus, payload);
                if (newStatus === 'Confirmed') {
                    playChime('success');
                    showToast(
                        'Booking Approved! 🎉',
                        `Your booking for ${payload.room_type || 'your room'} has been confirmed!`,
                        'success'
                    );
                } else if (newStatus === 'Cancelled') {
                    playChime('danger');
                    const isByHotel = payload.cancelled_by !== 'Customer';
                    showToast(
                        isByHotel ? 'Booking Cancelled by Hotel ⚠️' : 'Booking Cancelled',
                        isByHotel
                            ? `Hotel cancelled reservation: ${payload.cancellation_reason || 'Operational maintenance'}. Full refund reverted.`
                            : `Your booking for ${payload.room_type || 'your room'} was cancelled.`,
                        'danger'
                    );
                } else if (newStatus === 'Completed') {
                    showToast('Booking Completed', `Your stay in ${payload.room_type || 'your room'} is marked completed.`, 'info');
                }
            }
        }

        // 3. Booking Cancelled by Customer
        else if (eventType === 'booking_cancelled') {
            const bookingId = payload.booking_id;
            if (userRole === 'admin') {
                playChime('danger');
                showToast(
                    'Booking Cancelled by Guest',
                    `${payload.guest_name || 'Guest'} cancelled reservation #${bookingId} (${payload.room_type || ''}).`,
                    'danger'
                );
                updateAdminBookingStatus(bookingId, 'Cancelled');
                updateRecentBookingStatus(bookingId, 'Cancelled');
            } else if (userRole === 'customer' && (event.user_id === currentUserId || payload.user_id === currentUserId)) {
                updateCustomerBookingStatus(bookingId, 'Cancelled', payload);
            }
        }

        // 4. Room Updated/Added/Deleted
        else if (eventType === 'room_updated') {
            updateRoomListing(payload);
        }

        // Broadcast to other tabs if event originated from server polling
        if (fromServer && broadcastChannel) {
            broadcastChannel.postMessage({ type: 'EVENT', event: event });
        }
    }

    // --- DOM Update Functions ---

    // Admin Bookings Page
    function insertAdminBookingRow(p) {
        const tbody = document.getElementById('admin-bookings-tbody');
        if (!tbody) return;

        const emptyRow = document.getElementById('no-admin-bookings-row');
        if (emptyRow) emptyRow.remove();

        const tr = document.createElement('tr');
        tr.id = `admin-booking-row-${p.booking_id}`;
        tr.setAttribute('data-booking-id', p.booking_id);

        tr.innerHTML = `
            <td>
                ${p.booking_reference ? `<span class="badge" style="background:#eaf4f1; color:var(--teal); font-family:monospace; font-size:0.75rem; font-weight:800; margin-bottom:4px; display:inline-block; border:1px solid #c2ded7;">${escapeHtml(p.booking_reference)}</span><br>` : ''}
                <strong>${escapeHtml(p.guest_name)}</strong>
            </td>
            <td>
                <strong>${escapeHtml(p.room_type)}</strong><br>
                <small class="muted">Room ${escapeHtml(p.room_number)}</small>
            </td>
            <td>
                <div><strong>In:</strong> ${escapeHtml(p.check_in)} <small class="muted">(${escapeHtml(p.check_in_time || '02:00 PM')})</small></div>
                <div><strong>Out:</strong> ${escapeHtml(p.check_out)} <small class="muted">(${escapeHtml(p.check_out_time || '11:00 AM')})</small></div>
            </td>
            <td>
                <small>${escapeHtml(p.guest_email || '')}</small><br>
                <small>${escapeHtml(p.guest_phone || '')}</small>
            </td>
            <td>
                <strong>₹${Number(p.total_amount || 0).toLocaleString()}</strong><br>
                <small class="muted" style="display:block; margin-bottom:3px;">incl. 12% GST</small>
                <span class="badge" style="font-size:0.7rem; ${p.payment_status === 'Paid' ? 'background:#e6f5ee; color:#197357;' : 'background:#fff8e7; color:#8b642e;'}">
                    ${escapeHtml(p.payment_method || 'Pay on Arrival')} &bull; ${escapeHtml(p.payment_status || 'Pending')}
                </span>
            </td>
            <td>
                <span class="badge ${String(p.status || 'pending').toLowerCase()}" id="admin-badge-${p.booking_id}">
                    ${escapeHtml(p.status || 'Pending')}
                </span>
            </td>
            <td>
                <form method="post" class="status-update-form" data-booking-id="${p.booking_id}">
                    <input type="hidden" name="id" value="${p.booking_id}">
                    <select name="status" class="booking-status-select" data-booking-id="${p.booking_id}">
                        <option value="Pending" ${p.status === 'Pending' ? 'selected' : ''}>Pending</option>
                        <option value="Confirmed" ${p.status === 'Confirmed' ? 'selected' : ''}>Confirmed (Approve)</option>
                        <option value="Cancelled" ${p.status === 'Cancelled' ? 'selected' : ''}>Cancelled</option>
                        <option value="Completed" ${p.status === 'Completed' ? 'selected' : ''}>Completed</option>
                    </select>
                </form>
            </td>
        `;

        tbody.insertBefore(tr, tbody.firstChild);
        highlightElement(tr);
        attachStatusSelectHandler(tr.querySelector('.booking-status-select'));

        const countLabel = document.getElementById('booking-count-label');
        if (countLabel) {
            const currentCount = tbody.querySelectorAll('tr[data-booking-id]').length;
            countLabel.textContent = `${currentCount} total reservations`;
        }
    }

    function updateAdminBookingStatus(bookingId, status) {
        const row = document.getElementById(`admin-booking-row-${bookingId}`);
        const badge = document.getElementById(`admin-badge-${bookingId}`);
        const select = row?.querySelector('.booking-status-select');

        if (badge) {
            badge.textContent = status;
            badge.className = `badge ${status.toLowerCase()}`;
        }
        if (select && select.value !== status) {
            select.value = status;
        }
        if (row) {
            highlightElement(row);
        }
    }

    // Admin Dashboard Page
    function insertRecentBookingRow(p) {
        const tbody = document.getElementById('recent-bookings-tbody');
        if (!tbody) return;

        const emptyRow = document.getElementById('no-recent-bookings-row');
        if (emptyRow) emptyRow.remove();

        const tr = document.createElement('tr');
        tr.id = `recent-booking-row-${p.booking_id}`;
        tr.setAttribute('data-booking-id', p.booking_id);

        tr.innerHTML = `
            <td><strong>${escapeHtml(p.guest_name)}</strong></td>
            <td>${escapeHtml(p.room_type)}</td>
            <td>${escapeHtml(p.check_in)} → ${escapeHtml(p.check_out)}</td>
            <td>
                <span class="badge ${String(p.status || 'pending').toLowerCase()}" id="recent-badge-${p.booking_id}">
                    ${escapeHtml(p.status || 'Pending')}
                </span>
            </td>
        `;

        tbody.insertBefore(tr, tbody.firstChild);
        highlightElement(tr);

        // Keep maximum 5 rows
        const rows = tbody.querySelectorAll('tr');
        if (rows.length > 5) {
            rows[rows.length - 1].remove();
        }
    }

    function updateRecentBookingStatus(bookingId, status) {
        const badge = document.getElementById(`recent-badge-${bookingId}`);
        const row = document.getElementById(`recent-booking-row-${bookingId}`);
        if (badge) {
            badge.textContent = status;
            badge.className = `badge ${status.toLowerCase()}`;
        }
        if (row) {
            highlightElement(row);
        }
    }

    function updateDashboardStats(stats) {
        if (!stats) return;
        ['rooms', 'available', 'bookings', 'customers'].forEach(key => {
            const el = document.getElementById(`stat-${key}`);
            if (el && stats[key] !== undefined) {
                if (el.textContent != stats[key]) {
                    el.textContent = stats[key];
                    highlightElement(el.parentElement);
                }
            }
        });
    }

    // Customer My Bookings Page
    function updateCustomerBookingStatus(bookingId, status, payload) {
        // Desktop elements
        const row = document.getElementById(`booking-row-${bookingId}`);
        const badge = document.getElementById(`status-badge-${bookingId}`);
        const actionCell = document.getElementById(`action-cell-${bookingId}`);
        const desktopCallout = document.getElementById(`desktop-cancel-callout-${bookingId}`);

        // Mobile card elements
        const mobileCard = document.getElementById(`mobile-card-${bookingId}`);
        const mobileBadge = mobileCard?.querySelector('.mb-card-header .badge');
        const mobileCallout = document.getElementById(`mobile-cancel-callout-${bookingId}`);
        const mobileAction = document.getElementById(`mobile-action-${bookingId}`);

        if (badge) {
            badge.textContent = status;
            badge.className = `badge ${status.toLowerCase()}`;
        }
        if (mobileBadge) {
            mobileBadge.textContent = status;
            mobileBadge.className = `badge ${status.toLowerCase()}`;
        }

        if (actionCell) {
            if (status === 'Cancelled' || status === 'Completed') {
                actionCell.innerHTML = '—';
            }
        }
        if (mobileAction) {
            if (status === 'Cancelled' || status === 'Completed') {
                mobileAction.style.display = 'none';
            }
        }

        if (status === 'Cancelled' && payload) {
            const isCustomer = payload.cancelled_by === 'Customer';
            const reason = payload.cancellation_reason || (isCustomer ? '' : 'Operational Maintenance & Safety Requirements');
            const total = Number(payload.refund_amount || payload.total_amount || 0).toLocaleString();
            const fee = Number(payload.cancellation_fee || 0);
            const refundStatus = payload.refund_status || (isCustomer ? 'Reverted to original payment method' : '100% Full Charges Reverted to Original Payment Source');

            if (!isCustomer) {
                // Hotel Cancellation Callouts
                const hotelMobileHtml = `
                    <div class="mb-cancel-callout hotel">
                        <div class="mb-cancel-title hotel"><span>⚠️ Cancelled by Hotel</span></div>
                        <div style="color:#4c0519; margin-bottom:3px;"><strong>Hotel Reason:</strong> ${escapeHtml(reason)}</div>
                        <div class="mb-cancel-reverted">Charges Reverted: ₹${total} (100% Full Refund)</div>
                        <div class="mb-cancel-status">${escapeHtml(refundStatus)}</div>
                    </div>
                `;
                if (mobileCallout) mobileCallout.innerHTML = hotelMobileHtml;

                const hotelDesktopHtml = `
                    <div style="margin-top:8px; padding:10px 12px; background:#fff1f2; border:1px solid #fecdd3; border-radius:8px; font-size:0.78rem; text-align:left;">
                        <div style="color:#be123c; font-weight:700; display:flex; align-items:center; gap:5px; margin-bottom:4px;"><span>⚠️ Cancelled by Hotel</span></div>
                        <div style="color:#4c0519; margin-bottom:4px;"><strong>Hotel Reason:</strong> ${escapeHtml(reason)}</div>
                        <div style="color:#15803d; font-weight:700; margin-bottom:2px;">Charges Reverted: ₹${total} (100% Full Refund)</div>
                        <div style="font-size:0.72rem; color:#64748b;">${escapeHtml(refundStatus)}</div>
                    </div>
                `;
                if (desktopCallout) desktopCallout.innerHTML = hotelDesktopHtml;
            } else {
                // Customer Cancellation Callouts
                const custMobileHtml = `
                    <div class="mb-cancel-callout customer">
                        <div class="mb-cancel-title customer">Cancelled by You</div>
                        ${reason ? `<div style="color:#334155; margin-bottom:3px;"><strong>Reason:</strong> ${escapeHtml(reason)}</div>` : ''}
                        ${fee > 0 ? `<div style="color:#b91c1c; font-size:0.74rem;">Fee: ₹${fee.toLocaleString()} (10% Late cancellation)</div>` : ''}
                        <div class="mb-cancel-reverted">Charges Reverted: ₹${total}</div>
                        <div class="mb-cancel-status">${escapeHtml(refundStatus)}</div>
                    </div>
                `;
                if (mobileCallout) mobileCallout.innerHTML = custMobileHtml;

                const custDesktopHtml = `
                    <div style="margin-top:8px; padding:10px 12px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; font-size:0.78rem; text-align:left;">
                        <div style="color:#475569; font-weight:700; margin-bottom:3px;">Cancelled by You</div>
                        ${reason ? `<div style="color:#334155; margin-bottom:3px;"><strong>Reason:</strong> ${escapeHtml(reason)}</div>` : ''}
                        ${fee > 0 ? `<div style="color:#b91c1c; font-size:0.74rem;">Fee: ₹${fee.toLocaleString()} (10% Late cancellation)</div>` : ''}
                        <div style="color:#15803d; font-weight:700; margin-top:2px;">Charges Reverted: ₹${total}</div>
                        <div style="font-size:0.72rem; color:#64748b; margin-top:2px;">${escapeHtml(refundStatus)}</div>
                    </div>
                `;
                if (desktopCallout) desktopCallout.innerHTML = custDesktopHtml;
            }
        }

        if (row) highlightElement(row);
        if (mobileCard) highlightElement(mobileCard);
    }

    function insertCustomerBookingRow(p) {
        const tbody = document.getElementById('my-bookings-tbody');
        if (!tbody) return;

        const emptyRow = document.getElementById('no-bookings-row');
        if (emptyRow) emptyRow.remove();

        const tr = document.createElement('tr');
        tr.id = `booking-row-${p.booking_id}`;
        tr.setAttribute('data-booking-id', p.booking_id);

        tr.innerHTML = `
            <td>
                ${p.booking_reference ? `<span class="badge" style="background:#eaf4f1; color:var(--teal); font-family:monospace; font-size:0.76rem; font-weight:800; margin-bottom:4px; display:inline-block; border:1px solid #c2ded7;">${escapeHtml(p.booking_reference)}</span><br>` : ''}
                <strong>${escapeHtml(p.room_type)}</strong><br>
                <small class="muted">Room ${escapeHtml(p.room_number)}</small>
            </td>
            <td>
                <div><strong>In:</strong> ${escapeHtml(p.check_in)} <small class="muted">(${escapeHtml(p.check_in_time || '02:00 PM')})</small></div>
                <div><strong>Out:</strong> ${escapeHtml(p.check_out)} <small class="muted">(${escapeHtml(p.check_out_time || '11:00 AM')})</small></div>
            </td>
            <td>${escapeHtml(p.guests)}</td>
            <td>
                <strong>₹${Number(p.total_amount || 0).toLocaleString()}</strong><br>
                <small class="muted" style="display:block; margin-bottom:3px;">incl. 12% GST</small>
                <span class="badge" style="font-size:0.7rem; ${p.payment_status === 'Paid' ? 'background:#e6f5ee; color:#197357;' : 'background:#fff8e7; color:#8b642e;'}">
                    ${escapeHtml(p.payment_method || 'Pay on Arrival')} &bull; ${escapeHtml(p.payment_status || 'Pending')}
                </span>
            </td>
            <td>
                <span class="badge ${String(p.status || 'pending').toLowerCase()}" id="status-badge-${p.booking_id}">
                    ${escapeHtml(p.status || 'Pending')}
                </span>
            </td>
            <td id="action-cell-${p.booking_id}">
                <form method="post" onsubmit="return confirm('Are you sure you want to cancel this booking?');">
                    <input type="hidden" name="cancel_id" value="${p.booking_id}">
                    <button class="button danger small" type="submit">Cancel</button>
                </form>
            </td>
        `;

        tbody.insertBefore(tr, tbody.firstChild);
        highlightElement(tr);
    }

    // Public Rooms Page
    function updateRoomListing(p) {
        const card = document.getElementById(`room-card-${p.room_id}`);
        if (card) {
            if (p.action === 'deleted' || p.status === 'Maintenance' || p.status === 'Inactive') {
                card.style.opacity = '0.5';
                card.style.pointerEvents = 'none';
                highlightElement(card);
            } else if (p.action === 'updated') {
                highlightElement(card);
            }
        }
    }

    // Attach AJAX handler to admin status select dropdowns
    function attachStatusSelectHandler(selectEl) {
        if (!selectEl) return;
        selectEl.addEventListener('change', async function(e) {
            const bookingId = this.getAttribute('data-booking-id');
            const newStatus = this.value;

            // If new status is 'Cancelled', do NOT auto-submit via AJAX.
            // The hotel cancellation modal on admin/bookings.php will collect reason and confirm.
            if (newStatus === 'Cancelled') {
                return;
            }

            // Immediate optimistic UI feedback
            updateAdminBookingStatus(bookingId, newStatus);
            playChime(newStatus.toLowerCase());
            showToast('Updating Status', `Setting booking #${bookingId} to ${newStatus}...`, 'info');

            try {
                const formData = new FormData();
                formData.append('id', bookingId);
                formData.append('status', newStatus);

                const res = await fetch(apiUpdateStatusUrl, {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();

                if (data && data.success) {
                    showToast('Status Updated', `Booking #${bookingId} status is now ${newStatus}.`, 'success');
                    if (broadcastChannel) {
                        broadcastChannel.postMessage({
                            type: 'EVENT',
                            event: {
                                event_type: 'booking_status_updated',
                                payload: {
                                    booking_id: bookingId,
                                    status: newStatus
                                }
                            }
                        });
                    }
                } else {
                    showToast('Update Failed', data.error || 'Could not update status.', 'danger');
                }
            } catch (err) {
                console.error('Failed to update booking status via AJAX', err);
                // Fall back to submitting the parent form
                if (this.form) this.form.submit();
            }
        });
    }

    // Attach to existing dropdowns on page load
    document.querySelectorAll('.booking-status-select').forEach(attachStatusSelectHandler);

    // Fast polling loop
    async function poll() {
        if (isPolling) return;
        isPolling = true;

        const liveDot = document.querySelector('#live-indicator .dot');

        try {
            const url = `${apiEventsUrl}?last_id=${lastEventId}`;
            const res = await fetch(url, { cache: 'no-store' });
            if (!res.ok) throw new Error(`HTTP ${res.status}`);

            const data = await res.json();
            if (data && data.success) {
                if (liveDot) liveDot.style.background = '#2ecc71';

                // Initial connect gives us last_id without firing past events
                if (lastEventId < 0) {
                    lastEventId = data.last_id || 0;
                } else {
                    if (data.events && data.events.length > 0) {
                        data.events.forEach(ev => handleEvent(ev, true));
                    }
                    if (data.last_id !== undefined) {
                        lastEventId = data.last_id;
                    }
                }

                if (data.stats && userRole === 'admin') {
                    updateDashboardStats(data.stats);
                    if (broadcastChannel) {
                        broadcastChannel.postMessage({ type: 'STATS', stats: data.stats });
                    }
                }
            }
        } catch (err) {
            if (liveDot) liveDot.style.background = '#e74c3c';
            console.debug('Real-time poll retry:', err.message);
        } finally {
            isPolling = false;
            setTimeout(poll, pollInterval);
        }
    }

    // Start polling immediately when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', poll);
    } else {
        poll();
    }

})();
