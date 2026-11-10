// Enhance calendar links with availability updates without reloading the page.
let calendarRequest;
let requestVersion = 0;

function updateSelectedTime() {
    const selected = document.querySelector('input[name="time"]:checked');
    const summary = document.querySelector('[data-selected-time]');
    if (summary) summary.textContent = selected ? `· ${selected.value}` : '· Choose a time above';
}

document.addEventListener('change', (event) => {
    if (event.target.matches('input[name="time"]')) updateSelectedTime();
});

document.addEventListener('click', async (event) => {
    const link = event.target.closest('a[data-availability-link]');
    if (!link || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
    const workspace = document.querySelector('#booking-workspace');
    if (!workspace || !window.fetch || !window.DOMParser) return;
    event.preventDefault();

    const url = new URL(link.href);
    const status = document.querySelector('#availability-status');
    calendarRequest?.abort();
    calendarRequest = new AbortController();
    const version = ++requestVersion;
    workspace.setAttribute('aria-busy', 'true');
    if (status) status.textContent = 'Checking availability…';

    try {
        const response = await fetch(url, { signal: calendarRequest.signal, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        if (!response.ok) throw new Error('Availability could not be loaded.');
        const page = new DOMParser().parseFromString(await response.text(), 'text/html');
        const replacement = page.querySelector('#booking-workspace');
        if (!replacement) throw new Error('Calendar unavailable.');
        if (version !== requestVersion) return;
        workspace.replaceWith(replacement);
        document.querySelector('#booking-errors')?.remove();
        window.history.replaceState(null, '', url);
        const focusTarget = url.hash === '#time-heading'
            ? document.querySelector('#time-heading')
            : document.querySelector('.service-option[aria-current]');
        focusTarget?.focus({ preventScroll: true });
        if (url.hash === '#time-heading' && window.matchMedia('(max-width: 800px)').matches) {
            focusTarget?.scrollIntoView({ behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth', block: 'start' });
        }
        if (status) status.textContent = 'Availability updated. Choose a time for your appointment.';
        updateSelectedTime();
    } catch (error) {
        if (error.name === 'AbortError') return;
        workspace.removeAttribute('aria-busy');
        if (status) {
            status.classList.remove('sr-only');
            status.textContent = 'We couldn’t refresh the calendar. Please try your selection again.';
        }
    } finally {
        if (version === requestVersion) document.querySelector('#booking-workspace')?.removeAttribute('aria-busy');
    }
});

document.addEventListener('submit', (event) => {
    if (event.target.id !== 'appointment-form') return;
    const button = event.target.querySelector('button[type="submit"]');
    if (button) { button.dataset.submitting = 'true'; button.disabled = true; button.textContent = 'Reserving your chair…'; }
});

window.addEventListener('pageshow', () => {
    const button = document.querySelector('#appointment-form button[type="submit"]');
    if (button && button.dataset.submitting === 'true') {
        button.disabled = false;
        button.textContent = 'Continue to secure payment ↗';
        delete button.dataset.submitting;
    }
});

document.querySelector('#booking-errors, #confirmation')?.focus({ preventScroll: false });
