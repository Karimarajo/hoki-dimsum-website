/**
 * session.js — Hoki POS Session Guard & Global Number Formatter
 * Runs after page load on every page (except index.html).
 * - Syncs logout across browser tabs via storage events
 * - Guards against session being cleared externally
 * - Automatically formats type="number" inputs with thousands separators
 */

(function () {
    const LOGIN_PAGE = 'index.html';

    function isLoginPage() {
        return window.location.pathname.endsWith(LOGIN_PAGE) ||
               window.location.pathname.endsWith('/');
    }

    if (isLoginPage()) return;

    /* Redirect to login if session is gone, or enforce Investor route locks */
    function guardSession() {
        const uStr = localStorage.getItem('currentUser');
        if (!uStr) {
            window.location.href = LOGIN_PAGE;
            return;
        }
        
        try {
            const u = JSON.parse(uStr);
            if (u && u.role === 'Investor') {
                const path = window.location.pathname;
                if (!path.endsWith('investor_omset.html') && !path.endsWith('investor_profit.html')) {
                    window.location.href = 'investor_omset.html';
                }
            }
        } catch (e) {
            // Ignore parse errors, let normal app flow handle invalid session data
        }
    }

    /* Sync logout across tabs — if another tab clears currentUser, redirect here too */
    window.addEventListener('storage', function (e) {
        if (e.key === 'currentUser' && !e.newValue) {
            window.location.href = LOGIN_PAGE;
        }
    });

    /* Run guard once on load */
    guardSession();

    // ── GLOBAL NUMBER FORMATTER ──
    function formatRibuan(val) {
        if (val === undefined || val === null || val === '') return '';
        let clean = String(val).replace(/\D/g, '');
        if (clean === '') return '';
        return parseInt(clean, 10).toLocaleString('id-ID');
    }

    // Override HTMLInputElement value property descriptor to return clean number
    const originalValueDescriptor = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value');
    Object.defineProperty(HTMLInputElement.prototype, 'value', {
        get: function () {
            const val = originalValueDescriptor.get.call(this);
            if (this.dataset && this.dataset.isNumericFormatted) {
                return val.replace(/\D/g, '');
            }
            return val;
        },
        set: function (val) {
            if (this.dataset && this.dataset.isNumericFormatted) {
                const formatted = formatRibuan(val);
                originalValueDescriptor.set.call(this, formatted);
            } else {
                originalValueDescriptor.set.call(this, val);
            }
        }
    });

    // Transform type="number" to text with thousands separators
    function processInput(input) {
        if (input.dataset.isNumericFormatted) return;

        // Skip input type="number" if it has decimal step (e.g. qty in HPP)
        const step = input.getAttribute('step');
        if (step && step.includes('.')) return;

        // Mark it as processed
        input.dataset.isNumericFormatted = "true";
        input.type = 'text';

        // Format initial value
        const initialVal = originalValueDescriptor.get.call(input);
        if (initialVal) {
            originalValueDescriptor.set.call(input, formatRibuan(initialVal));
        }

        // Add listener to auto format during typing
        input.addEventListener('input', function (e) {
            const cursorPosition = e.target.selectionStart;
            const originalLength = e.target.value.length;
            
            const rawValue = originalValueDescriptor.get.call(e.target);
            const formatted = formatRibuan(rawValue);
            originalValueDescriptor.set.call(e.target, formatted);
            
            const newLength = formatted.length;
            const newPosition = cursorPosition + (newLength - originalLength);
            e.target.setSelectionStart(newPosition);
            e.target.setSelectionEnd(newPosition);
        });
    }

    function scanAndProcess() {
        const inputs = document.querySelectorAll('input[type="number"]');
        inputs.forEach(processInput);
    }

    scanAndProcess();
    document.addEventListener('DOMContentLoaded', scanAndProcess);
    window.addEventListener('load', scanAndProcess);

    // Watch for dynamically added inputs
    const observer = new MutationObserver(function (mutations) {
        mutations.forEach(function (mutation) {
            if (mutation.addedNodes) {
                mutation.addedNodes.forEach(function (node) {
                    if (node.nodeType === Node.ELEMENT_NODE) {
                        if (node.tagName === 'INPUT' && node.type === 'number') {
                            processInput(node);
                        } else {
                            const childInputs = node.querySelectorAll('input[type="number"]');
                            childInputs.forEach(processInput);
                        }
                    }
                });
            }
        });
    });
    observer.observe(document.documentElement, { childList: true, subtree: true });
})();
