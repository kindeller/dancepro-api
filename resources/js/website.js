// Progressive enhancement: content remains visible without JS or reduced motion.
const motion = window.matchMedia('(prefers-reduced-motion: reduce)');
const sections = [...document.querySelectorAll('[data-reveal]')];
let observer;
function configureReveals() {
    observer?.disconnect();
    sections.forEach(section => section.classList.remove('reveal-waiting'));
    if (motion.matches || !('IntersectionObserver' in window)) return;
    observer = new IntersectionObserver(entries => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.remove('reveal-waiting');
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.05, rootMargin: '0px 0px 45px 0px' });
    sections.forEach(section => {
        if (section.getBoundingClientRect().top > window.innerHeight) {
            section.classList.add('reveal-waiting');
            observer.observe(section);
        }
    });
}
configureReveals();
motion.addEventListener('change', configureReveals);
window.addEventListener('beforeprint', () => sections.forEach(section => section.classList.remove('reveal-waiting')));
// Keyboard focus and fragment links must never land on visually hidden content.
document.addEventListener('focusin', event => event.target.closest('[data-reveal]')?.classList.remove('reveal-waiting'));
window.addEventListener('hashchange', () => document.getElementById(location.hash.slice(1))?.classList.remove('reveal-waiting'));
