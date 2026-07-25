document.addEventListener('DOMContentLoaded', () => {
    const copyTextToClipboard = async (text) => {
        if (navigator.clipboard && window.isSecureContext) {
            await navigator.clipboard.writeText(text);
            return true;
        }

        const temporary = document.createElement('textarea');
        temporary.value = text;
        temporary.setAttribute('readonly', '');
        temporary.style.position = 'fixed';
        temporary.style.left = '-9999px';
        temporary.style.top = '0';
        document.body.appendChild(temporary);
        temporary.focus();
        temporary.select();
        temporary.setSelectionRange(0, temporary.value.length);
        const copied = document.execCommand('copy');
        temporary.remove();
        return copied;
    };

    const toggle = document.querySelector('.nav-toggle');
    const navigation = document.querySelector('#main-navigation');

    if (toggle && navigation) {
        toggle.addEventListener('click', () => {
            const open = toggle.getAttribute('aria-expanded') === 'true';
            toggle.setAttribute('aria-expanded', String(!open));
            navigation.classList.toggle('is-open', !open);
        });

        navigation.addEventListener('click', (event) => {
            if (event.target.closest('a')) {
                toggle.setAttribute('aria-expanded', 'false');
                navigation.classList.remove('is-open');
            }
        });
    }

    document.querySelectorAll('[data-auto-submit]').forEach((control) => {
        control.addEventListener('change', () => control.form?.submit());
    });

    document.querySelectorAll('.color-input input[type="color"]').forEach((control) => {
        const value = control.closest('.color-input')?.querySelector('[data-color-value]');
        control.addEventListener('input', () => {
            if (value) value.textContent = control.value.toUpperCase();
        });
    });

    document.querySelectorAll('[data-calendar-viewer]').forEach((calendar) => {
        const buttons = Array.from(calendar.querySelectorAll('[data-calendar-view]'));
        const panels = Array.from(calendar.querySelectorAll('[data-calendar-panel]'));
        let preferredView = 'calendar';
        try {
            const storedView = sessionStorage.getItem('salud-familiar-calendar-view');
            if (storedView === 'calendar' || storedView === 'list') preferredView = storedView;
        } catch (_) {
            preferredView = 'calendar';
        }

        const showView = (view, remember = false) => {
            buttons.forEach((button) => {
                const active = button.dataset.calendarView === view;
                button.classList.toggle('is-active', active);
                button.setAttribute('aria-pressed', String(active));
            });
            panels.forEach((panel) => {
                panel.hidden = panel.dataset.calendarPanel !== view;
            });
            if (remember) {
                try { sessionStorage.setItem('salud-familiar-calendar-view', view); } catch (_) {}
            }
        };

        buttons.forEach((button) => {
            button.addEventListener('click', () => showView(button.dataset.calendarView, true));
        });
        showView(preferredView);
    });

    document.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-appointment-modal]');
        if (!trigger) return;
        const dialog = document.getElementById(trigger.dataset.appointmentModal);
        if (!dialog || typeof dialog.showModal !== 'function') return;
        event.preventDefault();
        const currentDialog = trigger.closest('dialog[open]');
        if (currentDialog && currentDialog !== dialog) currentDialog.close();
        dialog.showModal();
    });

    document.querySelectorAll('.appointment-modal').forEach((dialog) => {
        dialog.addEventListener('click', (event) => {
            if (event.target === dialog) dialog.close();
        });
    });

    document.querySelectorAll('[data-close-dialog]').forEach((button) => {
        button.addEventListener('click', () => button.closest('dialog')?.close());
    });

    document.querySelectorAll('dialog[data-auto-open-modal]').forEach((dialog) => {
        if (typeof dialog.showModal === 'function' && !dialog.open) dialog.showModal();
    });

    document.querySelectorAll('[data-copy-invitation]').forEach((button) => {
        button.addEventListener('click', async () => {
            const input = document.getElementById(button.dataset.copyInvitation);
            const feedback = document.querySelector('[data-copy-feedback]');
            if (!input) return;
            let copied = false;
            try {
                copied = await copyTextToClipboard(input.value);
            } catch (_) {
                input.focus();
                input.select();
            }
            if (feedback) feedback.textContent = copied ? 'Enlace copiado. Ya puedes compartirlo.' : 'Selecciona el enlace y cópialo manualmente.';
            button.textContent = copied ? 'Copiado' : 'Copiar enlace';
        });
    });

    document.querySelectorAll('[data-copy-text]').forEach((button) => {
        button.addEventListener('click', async () => {
            const input = document.getElementById(button.dataset.copyText);
            const feedback = button.closest('dialog')?.querySelector('[data-copy-text-feedback]');
            if (!input) return;
            let copied = false;
            try {
                copied = await copyTextToClipboard(input.value);
            } catch (_) {
                input.focus();
                input.select();
            }
            if (feedback) feedback.textContent = copied ? 'Texto copiado. Ya puedes pegarlo en tu IA.' : 'Selecciona el texto y cópialo manualmente.';
            button.textContent = copied ? 'Copiado' : 'Copiar texto';
        });
    });
});
