import { gsap } from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';

gsap.registerPlugin(ScrollTrigger);

const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const isDesktop = window.matchMedia('(min-width: 981px)').matches;
const nav = document.querySelector('[data-nav]');

const updateNav = () => nav?.classList.toggle('scrolled', window.scrollY > 24);
updateNav();
window.addEventListener('scroll', updateNav, { passive: true });

const moduleExplorer = document.querySelector('[data-module-explorer]');
const moduleTabs = [...(moduleExplorer?.querySelectorAll('[data-module-tab]') || [])];

const activateModule = (tab) => {
    if (!moduleExplorer) return;

    const revealTab = () => tab.scrollIntoView({
        behavior: reducedMotion ? 'auto' : 'smooth',
        block: 'nearest',
        inline: 'center',
    });

    if (tab.getAttribute('aria-selected') === 'true') {
        revealTab();
        return;
    }

    const currentTab = moduleTabs.find((item) => item.getAttribute('aria-selected') === 'true');
    const currentPanel = currentTab ? document.getElementById(currentTab.getAttribute('aria-controls')) : null;
    const nextPanel = document.getElementById(tab.getAttribute('aria-controls'));
    if (!nextPanel) return;

    moduleTabs.forEach((item) => {
        const selected = item === tab;
        item.setAttribute('aria-selected', String(selected));
        item.tabIndex = selected ? 0 : -1;
    });
    if (currentPanel) currentPanel.hidden = true;
    nextPanel.hidden = false;
    revealTab();

    if (!reducedMotion) {
        gsap.fromTo(nextPanel.children, { y: 14, opacity: 0.72 }, { y: 0, opacity: 1, duration: 0.38, stagger: 0.05, ease: 'power2.out' });
    }
};

moduleTabs.forEach((tab, index) => {
    tab.addEventListener('click', () => activateModule(tab));
    tab.addEventListener('keydown', (event) => {
        if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
        event.preventDefault();
        const nextIndex = event.key === 'Home' ? 0 : event.key === 'End' ? moduleTabs.length - 1 : (index + (event.key === 'ArrowRight' ? 1 : -1) + moduleTabs.length) % moduleTabs.length;
        moduleTabs[nextIndex].focus();
        activateModule(moduleTabs[nextIndex]);
    });
});

const roleContent = [
    ['Command center', 'See the whole business clearly.', 'Pipeline, performance, risk and revenue—summarised for fast decisions.'],
    ['Sales workspace', 'Turn next actions into momentum.', 'Prioritised leads, calls, visits and follow-ups keep every advisor focused.'],
    ['CRM control', 'Protect every lead and process.', 'Assignments, data quality, SLAs and automations stay visible from one desk.'],
    ['Customer lifecycle', 'Carry confidence beyond booking.', 'Demand schedules, documents and commitments remain organised and accountable.'],
    ['People & finance', 'Connect performance to operations.', 'Attendance, payroll, incentives and expenses become easier to understand.'],
];

const rolePanel = document.querySelector('[data-role-panel]');
rolePanel?.querySelectorAll('[data-role-tab]').forEach((tab) => {
    tab.addEventListener('click', () => {
        const index = Number(tab.dataset.roleTab);
        const [eyebrow, title, copy] = roleContent[index];
        rolePanel.querySelectorAll('[data-role-tab]').forEach((item) => item.setAttribute('aria-selected', String(item === tab)));
        const targets = [rolePanel.querySelector('[data-role-eyebrow]'), rolePanel.querySelector('[data-role-title]'), rolePanel.querySelector('[data-role-copy]')];

        if (reducedMotion) {
            [targets[0].textContent, targets[1].textContent, targets[2].textContent] = [eyebrow, title, copy];
            return;
        }

        gsap.to(targets, {
            opacity: 0,
            y: 7,
            duration: 0.18,
            stagger: 0.03,
            onComplete: () => {
                [targets[0].textContent, targets[1].textContent, targets[2].textContent] = [eyebrow, title, copy];
                gsap.to(targets, { opacity: 1, y: 0, duration: 0.35, stagger: 0.05, ease: 'power2.out' });
            },
        });
    });
});

const spotlightTrack = document.querySelector('[data-spotlight]');
const spotlight = spotlightTrack?.querySelector('.spotlight');
spotlightTrack?.addEventListener('pointermove', (event) => {
    if (!spotlight || reducedMotion) return;
    const bounds = spotlightTrack.getBoundingClientRect();
    gsap.to(spotlight, { x: event.clientX - bounds.left + 30, duration: 0.45, ease: 'power2.out' });
});

if (!reducedMotion) {
    const intro = gsap.timeline({ defaults: { ease: 'power3.out' } });
    intro
        .from('.eyebrow', { opacity: 0, y: 14, duration: 0.65 })
        .from('.headline-line > span', { yPercent: 115, duration: 0.9, stagger: 0.11 }, '-=.35')
        .from('.hero-copy, .hero-actions', { opacity: 0, y: 20, duration: 0.7, stagger: 0.12 }, '-=.5')
        .from('.hero-stage', { opacity: 0, y: 80, rotateX: 8, scale: 0.96, duration: 1.05 }, '-=.45')
        .from('.floating-stat', { opacity: 0, scale: 0.82, duration: 0.45, stagger: 0.12 }, '-=.3');

    gsap.to('.aurora-core', { rotate: 9, scaleX: 1.35, duration: 4.8, repeat: -1, yoyo: true, ease: 'sine.inOut' });
    gsap.to('.art-sheen', { xPercent: 240, duration: 4, repeat: -1, repeatDelay: 2.4, ease: 'power1.inOut' });
    gsap.to('.floating-stat', { y: -9, duration: 2.6, repeat: -1, yoyo: true, stagger: 0.4, ease: 'sine.inOut' });
    gsap.to('.spotlight', { x: () => (spotlightTrack?.clientWidth || 800) + 240, duration: 7, repeat: -1, ease: 'none' });

    document.querySelectorAll('.reveal-group').forEach((group) => {
        gsap.from(group.children, { scrollTrigger: { trigger: group, start: 'top 82%', once: true }, y: 34, duration: 0.7, stagger: 0.1, ease: 'power3.out' });
    });

    document.querySelectorAll('.stagger-grid').forEach((grid) => {
        gsap.from(grid.children, { scrollTrigger: { trigger: grid, start: 'top 82%', once: true }, y: 36, duration: 0.68, stagger: 0.08, ease: 'power3.out' });
    });

    gsap.to('[data-parallax="hero"]', { scrollTrigger: { trigger: '.hero', start: 'top top', end: 'bottom top', scrub: 0.8 }, y: isDesktop ? 85 : 25, scale: isDesktop ? 0.97 : 1 });
    gsap.from('[data-parallax="dashboard"]', { scrollTrigger: { trigger: '.control-section', start: 'top 70%', end: 'center 50%', scrub: 0.7 }, y: 70, rotateX: 7, opacity: 0.5, transformPerspective: 1200 });

    if (isDesktop) {
        ScrollTrigger.create({ trigger: '.control-section', start: 'top top', end: '+=420', pin: '.control-pin', pinSpacing: true, anticipatePin: 1 });
    }

    window.addEventListener('load', () => ScrollTrigger.refresh(), { once: true });
}

const anchoredSection = ['#book-demo', '#advanced-capabilities'].includes(window.location.hash)
    ? document.querySelector(window.location.hash)
    : null;

if (anchoredSection) {
    window.addEventListener('load', () => requestAnimationFrame(() => anchoredSection.scrollIntoView({ block: 'start' })), { once: true });
}
