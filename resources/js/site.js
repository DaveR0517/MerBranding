/*
 * MEBRANDING website: kleine interactie bovenop werkende HTML.
 * Alles werkt ook zonder JavaScript; dit voegt beweging, het menu en de stappen van het creatorformulier toe.
 * Animaties volgen het gekozen homepage-ontwerp en stoppen bij prefers-reduced-motion.
 */
const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const touch = window.matchMedia('(hover: none)').matches;
const small = () => innerWidth < 761 || innerHeight < 561;
const $ = (s, el = document) => el.querySelector(s);
const $$ = (s, el = document) => [...el.querySelectorAll(s)];

/* ---------- navigatie ---------- */
const nav = $('#nav');
const mbtn = $('#mbtn');
if (nav && mbtn) {
    const zet = (open) => {
        nav.classList.toggle('open', open);
        mbtn.setAttribute('aria-expanded', String(open));
        mbtn.setAttribute('aria-label', open ? 'Menu sluiten' : 'Menu openen');
    };
    mbtn.addEventListener('click', () => zet(!nav.classList.contains('open')));
    $$('.mmenu a').forEach((a) => a.addEventListener('click', () => zet(false)));
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && nav.classList.contains('open')) {
            zet(false);
            mbtn.focus();
        }
    });
    const scroll = () => nav.classList.toggle('sc', scrollY > 40);
    addEventListener('scroll', scroll, { passive: true });
    scroll();
}

/* ---------- aanraakschermen: andere hints, video's spelen af in beeld ---------- */
if (touch) {
    document.body.classList.add('touch');
    $$('.hint[data-touch]').forEach((h) => (h.textContent = h.dataset.touch));
    const vio = new IntersectionObserver((es) => es.forEach((e) => e.target.classList.toggle('inview', e.isIntersecting)), { threshold: 0.6 });
    $$('.vid.hv').forEach((v) => vio.observe(v));
}

/* ---------- onthullen bij scrollen ---------- */
const io = new IntersectionObserver(
    (es) => es.forEach((e) => {
        if (e.isIntersecting) {
            e.target.classList.add('in');
            io.unobserve(e.target);
        }
    }),
    { threshold: 0.15 },
);
$$('.rv').forEach((el) => io.observe(el));

/* ---------- tellers ---------- */
function tel(el, delay) {
    const eind = Number(el.dataset.tel);
    if (reduce || !eind) return;
    const duur = eind > 100 ? 1800 : 1200;
    let start = null;
    el.textContent = '0';
    const stap = (t) => {
        if (!start) start = t + delay;
        const p = Math.max(0, Math.min(1, (t - start) / duur));
        el.textContent = Math.round(eind * (1 - Math.pow(1 - p, 3))).toLocaleString('nl-NL');
        if (p < 1) requestAnimationFrame(stap);
    };
    requestAnimationFrame(stap);
}
const cnt = $('#cnt');
if (cnt) tel(cnt, 1200);
const big = $('#bigcnt');
if (big) {
    const ob = new IntersectionObserver((es) => {
        if (es[0].isIntersecting) {
            ob.disconnect();
            tel(big, 0);
        }
    }, { threshold: 0.4 });
    ob.observe(big);
}

/* ---------- hero: diepte bij muisbeweging, zwermende iconen, meldingen ---------- */
const hero = $('.hero');
if (hero && !reduce) {
    let raf = null;
    let mx = 0.5;
    let my = 0.5;
    hero.addEventListener('mousemove', (e) => {
        const r = hero.getBoundingClientRect();
        mx = (e.clientX - r.left) / r.width;
        my = (e.clientY - r.top) / r.height;
        if (raf) return;
        raf = requestAnimationFrame(() => {
            raf = null;
            $$('[data-d]', hero).forEach((el) => {
                const d = Number(el.dataset.d);
                el.style.translate = `${(mx - 0.5) * -d}px ${(my - 0.5) * -d}px`;
            });
        });
    });
    hero.addEventListener('mouseleave', () => $$('[data-d]', hero).forEach((el) => (el.style.translate = '')));

    const sw = $('#swarm');
    if (sw) {
        const icons = [
            '<svg viewBox="0 0 24 24" fill="#FF8A93"><path d="M12 21s-7-4.5-9.5-9C.8 8.6 3 5 6.5 5c2 0 3.5 1.2 5.5 3 2-1.8 3.5-3 5.5-3C21 5 23.2 8.6 21.5 12 19 16.5 12 21 12 21z"/></svg>',
            '<svg viewBox="0 0 24 24" fill="#fff"><path d="M8 5l11 7-11 7z"/></svg>',
            '<svg viewBox="0 0 24 24" fill="#C9C2FF"><path d="M4 4h16v12H8l-4 4z"/></svg>',
            '<svg viewBox="0 0 24 24" fill="#fff"><path d="M14 5l7 7-7 7v-4c-5 0-8 1.5-10 5 1-5 4-9 10-10z"/></svg>',
            '<svg viewBox="0 0 24 24" fill="#FFD3A8"><path d="M9 3v11.5a3.5 3.5 0 1 0 2 3.2V8h6V3z"/></svg>',
            '<svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2"><rect x="3" y="7" width="18" height="13" rx="3"/><circle cx="12" cy="13.5" r="3.5"/><path d="M8 7l1.5-3h5L16 7"/></svg>',
            '<svg viewBox="0 0 24 24" fill="#9C8CFF"><path d="M12 2l2.6 6.3L21 9l-5 4.4L17.5 20 12 16.6 6.5 20 8 13.4 3 9l6.4-.7z"/></svg>',
            '<svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 21c1-4 4-6 8-6s7 2 8 6"/><path d="M19 3v4M17 5h4"/></svg>',
        ];
        const bugs = [];
        for (let i = 0, n = small() ? 9 : 22; i < n; i++) {
            const d = document.createElement('span');
            d.className = 'ic';
            d.style.setProperty('--s', `${small() ? 20 + Math.random() * 14 : 24 + Math.random() * 26}px`);
            d.innerHTML = icons[i % icons.length];
            sw.appendChild(d);
            bugs.push({ el: d, x: Math.random(), y: Math.random(), vx: (Math.random() - 0.5) * 0.0009, vy: -(0.0003 + Math.random() * 0.0008), r: Math.random() * 360, vr: (Math.random() - 0.5) * 0.3, ph: Math.random() * 6.28 });
        }
        const fly = () => {
            requestAnimationFrame(fly);
            const W = sw.clientWidth;
            const H = sw.clientHeight;
            bugs.forEach((b) => {
                b.ph += 0.01;
                b.x += b.vx + Math.sin(b.ph) * 0.0006;
                b.y += b.vy;
                b.r += b.vr;
                const dx = b.x - mx;
                const dy = b.y - my;
                if (dx * dx + dy * dy < 0.02) {
                    b.x += dx * 0.03;
                    b.y += dy * 0.03;
                }
                if (b.y < -0.08) {
                    b.y = 1.08;
                    b.x = Math.random();
                }
                if (b.x < -0.05) b.x = 1.05;
                if (b.x > 1.05) b.x = -0.05;
                b.el.style.transform = `translate(${b.x * W}px,${b.y * H}px) rotate(${b.r}deg)`;
            });
        };
        fly();
    }

    const notes = $('#notes');
    if (notes) {
        const msgs = [
            ['♥', '#FBE3E5', '#D9303E', 'Iemand vindt je video leuk', 'via TikTok'],
            ['+', '#E6E1FF', '#614ED9', '+12 nieuwe volgers', 'uit Leeuwarden, schatting'],
            ['▶', '#DFF3E6', '#1E6B3A', '1.000 views bereikt', 'video van @creator'],
            ['💬', '#FFF1D6', '#7A4A00', '"Morgen ga ik daar heen!"', 'reactie op TikTok'],
        ];
        const pos = [[58, 6], [2, 40], [60, 58], [4, 74]];
        const posM = [[30, 3], [2, 30], [34, 56], [2, 78]];
        let ni = 0;
        const note = () => {
            if (document.hidden) return;
            const m = msgs[ni % msgs.length];
            const p = (small() ? posM : pos)[ni % pos.length];
            ni++;
            const n = document.createElement('div');
            n.className = 'note';
            n.style.left = `${p[0]}%`;
            n.style.top = `${p[1]}%`;
            const av = document.createElement('span');
            av.className = 'av';
            av.style.cssText = `background:${m[1]};color:${m[2]};font-weight:900`;
            av.textContent = m[0];
            const t = document.createElement('span');
            const b = document.createElement('b');
            b.textContent = m[3];
            const s = document.createElement('small');
            s.textContent = m[4];
            t.append(b, s);
            n.append(av, t);
            notes.appendChild(n);
            requestAnimationFrame(() => n.classList.add('show'));
            setTimeout(() => {
                n.classList.remove('show');
                setTimeout(() => n.remove(), 500);
            }, 3200);
        };
        setInterval(note, 2400);
        setTimeout(note, 1800);
    }
}

/* ---------- hoe het werkt: stappen met telefoon ---------- */
const sts = $$('.st');
if (sts.length) {
    const svs = $$('.phone .vid');
    const plbl = $('#plbl');
    let cur = 0;
    let timer;
    const go = (i, auto) => {
        cur = i;
        sts.forEach((s) => {
            const on = Number(s.dataset.i) === i;
            s.classList.remove('on');
            s.setAttribute('aria-pressed', String(on));
            if (on) {
                void s.offsetWidth;
                s.classList.add('on');
            }
        });
        svs.forEach((v) => v.classList.toggle('on', Number(v.dataset.i) === i));
        if (plbl) plbl.textContent = `Stap ${i + 1}`;
        clearTimeout(timer);
        if (!reduce && auto) timer = setTimeout(() => go((cur + 1) % sts.length, true), 4500);
    };
    sts.forEach((s) => s.addEventListener('click', () => go(Number(s.dataset.i), false)));
    const io2 = new IntersectionObserver((es) => {
        if (es[0].isIntersecting) {
            io2.disconnect();
            go(0, true);
        }
    }, { threshold: 0.4 });
    io2.observe($('.story'));
}

/* ---------- voorbeelden per branche: tabs ---------- */
const tabs = $$('.tab[role="tab"]');
if (tabs.length) {
    const kies = (b, focus) => {
        tabs.forEach((t, i) => {
            t.setAttribute('aria-selected', String(i === b));
            t.tabIndex = i === b ? 0 : -1;
        });
        $$('.bgroup').forEach((g, i) => g.toggleAttribute('data-hidden', i !== b));
        $('#bgrid').scrollLeft = 0;
        if (focus) tabs[b].focus();
    };
    tabs.forEach((t, i) => {
        t.addEventListener('click', () => kies(i, false));
        t.addEventListener('keydown', (e) => {
            const d = { ArrowRight: 1, ArrowLeft: -1 }[e.key];
            if (d) {
                e.preventDefault();
                kies((i + d + tabs.length) % tabs.length, true);
            }
        });
    });
}

/* ---------- creatorformulier: stappen ---------- */
const wz = $('#wizard');
if (wz) {
    const steps = $$('.wstep', wz);
    const knoppen = $$('.stepper button', wz);
    const bar = $('.wbar i', wz);
    let huidig = 0;

    const toon = (i, focus = true) => {
        huidig = i;
        steps.forEach((s, n) => s.classList.toggle('on', n === i));
        knoppen.forEach((k, n) => {
            k.toggleAttribute('aria-current', n === i);
            if (n === i) k.setAttribute('aria-current', 'step');
            k.parentElement.classList.toggle('done', n < i);
        });
        if (bar) bar.style.width = `${((i + 1) / steps.length) * 100}%`;
        if (i === steps.length - 1) vulOverzicht();
        if (focus) {
            const kop = $('h3', steps[i]);
            kop.setAttribute('tabindex', '-1');
            kop.focus({ preventScroll: true });
            $('#aanmelden').scrollIntoView({ behavior: reduce ? 'auto' : 'smooth', block: 'start' });
        }
    };
    const geldig = (i) => {
        for (const el of $$('input,select,textarea', steps[i])) {
            if (!el.checkValidity()) {
                el.reportValidity();
                return false;
            }
        }
        return true;
    };
    const naar = (doel) => {
        // Vooruit alleen als de tussenliggende stappen kloppen; terug mag altijd.
        for (let n = huidig; n < doel; n++) {
            if (!geldig(n)) {
                toon(n);
                return;
            }
        }
        toon(doel);
    };
    $$('[data-naar]', wz).forEach((b) => b.addEventListener('click', () => naar(Number(b.dataset.naar))));
    knoppen.forEach((k, n) => k.addEventListener('click', () => naar(n)));

    const tekst = (naam) => {
        const el = wz.querySelector(`[name="${naam}"]`);
        return el ? el.value.trim() : '';
    };
    function vulOverzicht() {
        const soort = wz.querySelector('[name="soort"]:checked');
        const kanalen = ['tiktok', 'instagram', 'youtube', 'facebook', 'snapchat']
            .filter((k) => tekst(`${k}-naam`))
            .map((k) => `${k[0].toUpperCase()}${k.slice(1)} ${tekst(`${k}-naam`)}${tekst(`${k}-volgers`) ? ` (${tekst(`${k}-volgers`)} volgers)` : ''}`);
        const werk = ['showreel', 'ugc-video1', 'ugc-video2', 'ugc-video3', 'post1', 'post2'].filter((n) => tekst(n)).length;
        const niches = $$('[name="niches[]"]:checked', wz).map((c) => c.parentElement.textContent.trim());
        const waarden = {
            soort: soort ? soort.closest('label').querySelector('b').textContent : '',
            contact: [tekst('naam'), tekst('email'), tekst('telefoon')].filter(Boolean).join(' · '),
            plaats: tekst('woonplaats') ? `${tekst('woonplaats')} · ${tekst('straal')} km rond je woonplaats` : '',
            geboortedatum: tekst('geboortedatum') ? new Date(tekst('geboortedatum')).toLocaleDateString('nl-NL') : '',
            kanalen: kanalen.join(' · '),
            werk: werk ? `${werk} ${werk === 1 ? 'link' : 'links'}` : '',
            niches: niches.join(', '),
            certificaat: tekst('certificaat'),
        };
        Object.entries(waarden).forEach(([k, v]) => {
            const dd = wz.querySelector(`[data-sum="${k}"]`);
            if (dd) dd.textContent = v || 'Niet ingevuld';
        });
    }

    // Versturen: verborgen stappen kan de browser niet tonen, dus zelf de eerste fout opzoeken.
    const form = $('form', wz);
    form.noValidate = true;
    form.addEventListener('submit', (e) => {
        const n = steps.findIndex((s) => $$('input,select,textarea', s).some((el) => !el.checkValidity()));
        if (n >= 0) {
            e.preventDefault();
            toon(n, false);
            geldig(n);
        }
    });

    // Na een serverfout: open de eerste stap met een fout, anders stap 1.
    const fout = steps.findIndex((s) => s.querySelector('[aria-invalid="true"], .err'));
    toon(fout >= 0 ? fout : 0, fout >= 0);
}

/* ---------- werkgebied: getal naast de schuif ---------- */
$$('input[type="range"][data-uit]').forEach((r) => {
    const uit = document.getElementById(r.dataset.uit);
    const zet = () => (uit.textContent = `${r.value} km rond je woonplaats`);
    r.addEventListener('input', zet);
    zet();
});

/* ---------- bedankmelding in beeld en voorlezen ---------- */
const bedankt = $('#bedankt');
if (bedankt) {
    bedankt.scrollIntoView({ block: 'center' });
    bedankt.focus({ preventScroll: true });
}
