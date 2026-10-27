/*
 * Landing page behaviour (resources/views/home.blade.php): draws the capsule illustrations and moves
 * the pinned one as the page scrolls. A separate file because the CSP allows no inline script; it
 * only sets SVG attributes, never inline styles, which the CSP would block.
 */
(() => {
    'use strict';

    const clamp = (t) => Math.min(1, Math.max(0, t));
    const ease = (t) => t * t * (3 - 2 * t);
    const easeOut = (t) => 1 - Math.pow(1 - t, 3);
    const noise = (n) => {
        const x = Math.sin(n * 12.9898) * 43758.5453;
        return x - Math.floor(x);
    };

    // The capsule at progress 0 (closed, tilted) to 1 (granules sorted into the week organiser),
    // ported from the design's Capsule component.
    function frame(progress) {
        const p = clamp(progress);
        const seg = (from, to) => clamp((p - from) / (to - from));
        const secondPull = ease(seg(0.55, 0.75));
        const lift = ease(seg(0.75, 1));
        const spill = easeOut(seg(0.42, 0.75));
        const settle = ease(seg(0.75, 1));

        const granules = [];
        for (let i = 0; i < 21; i++) {
            const slot = i % 3;
            const dayCentre = 30 + Math.floor(i / 3) * 61 + 27;
            const sortedX = [dayCentre - 11, dayCentre + 11, dayCentre][slot];
            const sortedY = [346, 346, 372][slot];
            const startX = 240 + (noise(i + 7) - 0.5) * 24;
            const startY = 240 + (noise(i + 13) - 0.5) * 24;
            const spillX = 240 + (noise(i + 1) - 0.5) * 320;
            const spillY = 292 + noise(i + 31) * 130;
            const x = startX + (spillX - startX) * spill;
            const y = startY + (spillY - startY) * spill;
            granules.push([x + (sortedX - x) * settle, y + (sortedY - y) * settle]);
        }

        let label = 'Capsule closed, tilted';
        if (p >= 0.2) label = 'Capsule closed, lying level';
        if (p >= 0.32) label = 'Capsule halves pulling apart';
        if (p >= 0.6) label = 'Capsule open, granules spilling out';
        if (p >= 0.85) label = 'Granules sorted into a Monday to Sunday organiser';

        return {
            label,
            angle: -28 * (1 - ease(seg(0, 0.22))),
            apart: 60 * ease(seg(0.3, 0.55)) + 50 * secondPull,
            tilt: 14 * secondPull,
            rim: seg(0.34, 0.42),
            lift,
            shadow: 1 - seg(0.6, 0.72),
            organiser: ease(seg(0.72, 0.9)),
            granuleOpacity: seg(0.42, 0.5),
            granules,
        };
    }

    function partsOf(svg) {
        const one = (name) => svg.querySelector(`[data-part="${name}"]`);
        return {
            shadow: one('shadow'),
            organiser: one('organiser'),
            granules: svg.querySelectorAll('[data-part="granule"]'),
            pill: one('pill'),
            body: one('body'),
            cap: one('cap'),
            rims: svg.querySelectorAll('[data-part="rim"]'),
        };
    }

    function draw(svg, parts, progress) {
        const f = frame(progress);

        svg.setAttribute('aria-label', f.label);
        parts.shadow.setAttribute('opacity', f.shadow.toFixed(2));
        parts.organiser.setAttribute('opacity', f.organiser.toFixed(2));
        parts.organiser.setAttribute('transform', `translate(0 ${(20 * (1 - f.organiser)).toFixed(1)})`);
        parts.granules.forEach((granule, i) => {
            granule.setAttribute('cx', f.granules[i][0].toFixed(1));
            granule.setAttribute('cy', f.granules[i][1].toFixed(1));
            granule.setAttribute('opacity', f.granuleOpacity.toFixed(2));
        });
        parts.pill.setAttribute('transform', `translate(240 ${(240 - 120 * f.lift).toFixed(1)}) rotate(${f.angle.toFixed(2)}) scale(${(1 - 0.4 * f.lift).toFixed(3)})`);
        parts.body.setAttribute('transform', `translate(${(-f.apart).toFixed(1)} 0) rotate(${f.tilt.toFixed(2)} 40 0)`);
        parts.cap.setAttribute('transform', `translate(${f.apart.toFixed(1)} 0) rotate(${(-f.tilt).toFixed(2)})`);
        parts.rims.forEach((rim) => rim.setAttribute('opacity', f.rim.toFixed(2)));
        svg.classList.add('is-drawn');
    }

    // Each [data-capsule-step] block holds the progress the pinned capsule reaches when the block's
    // middle crosses the middle of the window. Between two blocks it moves smoothly, or, when the
    // visitor asked for reduced motion, stays at the last one passed.
    function scrollProgress(steps, smooth) {
        const middle = window.innerHeight / 2;
        let previous = null;

        for (const step of steps) {
            const box = step.getBoundingClientRect();
            const point = { below: box.top + box.height / 2 - middle, progress: Number(step.dataset.capsuleStep) };

            if (point.below > 0) {
                if (previous === null) return point.progress;
                if (!smooth) return previous.progress;
                const t = previous.below / (previous.below - point.below);
                return previous.progress + (point.progress - previous.progress) * t;
            }
            previous = point;
        }

        return previous === null ? 0 : previous.progress;
    }

    const steps = Array.from(document.querySelectorAll('[data-capsule-step]'));
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

    document.querySelectorAll('[data-capsule]').forEach((svg) => {
        const parts = partsOf(svg);

        if (svg.dataset.progress !== undefined) {
            draw(svg, parts, Number(svg.dataset.progress));
            return;
        }

        let queued = false;
        const update = () => {
            queued = false;
            if (svg.getClientRects().length === 0) return; // hidden below the wide-screen breakpoint
            draw(svg, parts, scrollProgress(steps, !reducedMotion.matches));
        };
        const queue = () => {
            if (!queued) {
                queued = true;
                requestAnimationFrame(update);
            }
        };

        window.addEventListener('scroll', queue, { passive: true });
        window.addEventListener('resize', queue);
        document.fonts.ready.then(queue); // the web font changes where the blocks sit
        update();
    });

    // Close the phone menu once one of its links is followed.
    document.querySelectorAll('.menu a').forEach((link) => {
        link.addEventListener('click', () => {
            link.closest('details').open = false;
        });
    });

    // The header's menus (native <details>) close on a click outside them, or on Escape.
    const menus = () => document.querySelectorAll('details.menu[open], details.account-menu[open]');
    document.addEventListener('click', (event) => {
        menus().forEach((menu) => {
            if (!menu.contains(event.target)) {
                menu.open = false;
            }
        });
    });
    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') {
            return;
        }
        menus().forEach((menu) => {
            menu.open = false;
            menu.querySelector('summary')?.focus();
        });
    });
})();
