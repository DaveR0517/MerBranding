/*
 * MEBRANDING website: de kleine interactie uit het ontwerp.
 * Keuze Ondernemers of Creators op de home, en het menu op telefoon.
 */
const on = 'font-family: Montserrat,Arial,sans-serif; font-weight: 700; font-size: 14px; border: 0; cursor: pointer; min-height: 44px; padding: 0 18px; border-radius: 999px; background: #2E2B48; color: #FCFBFE';
const off = 'font-family: Montserrat,Arial,sans-serif; font-weight: 700; font-size: 14px; border: 0; cursor: pointer; min-height: 44px; padding: 0 18px; border-radius: 999px; background: transparent; color: #2E2B48';

function aud(a) {
    document.querySelectorAll('[data-aud]').forEach((e) => {
        e.hidden = e.getAttribute('data-aud') !== a;
    });
    document.querySelectorAll('[data-set]').forEach((b) => {
        const act = b.getAttribute('data-set') === a;
        b.setAttribute('style', act ? on : off);
        b.setAttribute('aria-pressed', String(act));
    });
}

document.querySelectorAll('[data-set]').forEach((b) => {
    b.addEventListener('click', () => aud(b.getAttribute('data-set')));
});

const menu = document.querySelector('[data-menu]');
document.querySelectorAll('[data-toggle-menu]').forEach((b) => {
    b.addEventListener('click', () => {
        menu.hidden = !menu.hidden;
        b.setAttribute('aria-expanded', String(!menu.hidden));
        b.setAttribute('aria-label', menu.hidden ? 'Menu openen' : 'Menu sluiten');
    });
});

// Na versturen: breng de bedankmelding of de foutsamenvatting in beeld en geef die de focus.
document.querySelector('[data-focus]')?.focus();
