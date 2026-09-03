/*
 * Phase S3 — the entire public-site script budget (plan §6.1: <= 15 KB).
 * Progressive enhancement only: every page works with this file absent.
 * Mobile nav toggle. Nothing else needs JS — FAQ accordions are native
 * <details>, forms are plain POSTs.
 */
(function () {
    'use strict';
    var toggle = document.querySelector('.nav-toggle');
    var nav = document.getElementById('primary-nav');
    if (!toggle || !nav) {
        return;
    }
    toggle.addEventListener('click', function () {
        var open = toggle.getAttribute('aria-expanded') === 'true';
        toggle.setAttribute('aria-expanded', String(!open));
        nav.classList.toggle('is-open', !open);
    });
    nav.addEventListener('click', function (event) {
        if (event.target instanceof HTMLElement && event.target.closest('a')) {
            toggle.setAttribute('aria-expanded', 'false');
            nav.classList.remove('is-open');
        }
    });
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && nav.classList.contains('is-open')) {
            toggle.setAttribute('aria-expanded', 'false');
            nav.classList.remove('is-open');
            toggle.focus();
        }
    });
}());
