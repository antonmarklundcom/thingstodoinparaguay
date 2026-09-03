/*!
 * Admin panel behaviour for thingstodoinparaguay.com (plan §5.2).
 *
 * Written for this project and vendored here on purpose: no CDN, no build step,
 * no framework, nothing to keep up to date. Every feature degrades — with
 * JavaScript off the editor is still a plain form that saves and scores on the
 * server, which is where the SEO score is authoritative anyway.
 *
 * Contents: mobile nav · character counters · slug from title · Markdown
 * toolbar and preview · repeatable rows · live SEO score · cover preview.
 */
(function () {
    'use strict';

    var $  = function (sel, root) { return (root || document).querySelector(sel); };
    var $$ = function (sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); };

    function debounce(fn, wait) {
        var timer = null;
        return function () {
            var args = arguments, self = this;
            clearTimeout(timer);
            timer = setTimeout(function () { fn.apply(self, args); }, wait);
        };
    }

    function csrfToken() {
        var field = $('input[name="_csrf"]');
        return field ? field.value : '';
    }

    // -- Mobile navigation ---------------------------------------------------
    (function nav() {
        var toggle = $('[data-nav-toggle]');
        var side   = $('#admin-nav');
        if (!toggle || !side) { return; }

        var narrow = window.matchMedia('(max-width: 59.99rem)');
        function apply() { side.hidden = narrow.matches; toggle.setAttribute('aria-expanded', 'false'); }
        apply();
        (narrow.addEventListener ? narrow.addEventListener('change', apply) : narrow.addListener(apply));

        toggle.addEventListener('click', function () {
            var open = side.hidden;
            side.hidden = !open;
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    })();

    // -- Character counters --------------------------------------------------
    $$('[data-count-for]').forEach(function (field) {
        var out = document.getElementById(field.getAttribute('data-count-for'));
        if (!out) { return; }
        var update = function () { out.textContent = String(field.value.trim().length); };
        field.addEventListener('input', update);
        update();
    });

    // -- Slug from title (only while the slug has not been touched) ----------
    (function slug() {
        var title  = $('[data-slug-source]');
        var target = $('[data-slug-target]');
        if (!title || !target) { return; }

        // A published page's slug is deliberately sticky: renaming it costs a redirect.
        var locked = target.getAttribute('data-locked') === '1' || target.value.trim() !== '';
        target.addEventListener('input', function () { locked = true; });

        title.addEventListener('input', function () {
            if (locked) { return; }
            target.value = title.value
                .toLowerCase()
                .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
                .replace(/[^a-z0-9]+/g, '-')
                .replace(/^-+|-+$/g, '')
                .slice(0, 80);
        });
    })();

    // -- Markdown toolbar + preview -----------------------------------------
    function surround(field, before, after) {
        var start = field.selectionStart, end = field.selectionEnd;
        var selected = field.value.slice(start, end);
        field.value = field.value.slice(0, start) + before + selected + after + field.value.slice(end);
        field.focus();
        field.selectionStart = start + before.length;
        field.selectionEnd   = start + before.length + selected.length;
        field.dispatchEvent(new Event('input', { bubbles: true }));
    }

    function prefixLines(field, prefix) {
        var start = field.selectionStart, end = field.selectionEnd;
        var lineStart = field.value.lastIndexOf('\n', start - 1) + 1;
        var block = field.value.slice(lineStart, end) || '';
        var updated = block.split('\n').map(function (line) {
            return line.indexOf(prefix) === 0 ? line.slice(prefix.length) : prefix + line;
        }).join('\n');
        field.value = field.value.slice(0, lineStart) + updated + field.value.slice(end);
        field.focus();
        field.selectionStart = lineStart;
        field.selectionEnd   = lineStart + updated.length;
        field.dispatchEvent(new Event('input', { bubbles: true }));
    }

    $$('[data-md-bar]').forEach(function (bar) {
        var field = document.getElementById(bar.getAttribute('data-md-bar'));
        if (!field) { return; }
        var preview = document.getElementById(field.id + '-preview');
        var form    = field.form;

        bar.addEventListener('click', function (event) {
            var button = event.target.closest('[data-md-action]');
            if (!button) { return; }
            var action = button.getAttribute('data-md-action');
            var value  = button.getAttribute('data-md-value') || '';

            if (action === 'wrap')   { surround(field, value, value); return; }
            if (action === 'prefix') { prefixLines(field, value); return; }
            if (action === 'link') {
                var href = window.prompt('Link to which address?', '/');
                if (href) { surround(field, '[', '](' + href + ')'); }
                return;
            }
            if (action === 'preview' && preview && form) {
                var showing = button.getAttribute('aria-pressed') === 'true';
                if (showing) {
                    preview.hidden = true;
                    field.hidden = false;
                    button.setAttribute('aria-pressed', 'false');
                    return;
                }
                var body = new URLSearchParams();
                body.set('_csrf', csrfToken());
                body.set('body_md', field.value);
                fetch(form.getAttribute('data-preview-url'), {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': csrfToken() },
                    body: body.toString()
                }).then(function (r) { return r.json(); }).then(function (data) {
                    // The server renders Markdown in safe mode, so this is the
                    // same HTML the public page would show.
                    preview.innerHTML = data.html || '';
                    preview.hidden = false;
                    field.hidden = true;
                    button.setAttribute('aria-pressed', 'true');
                }).catch(function () {
                    preview.textContent = 'The preview could not be loaded. Save and use the Preview link instead.';
                    preview.hidden = false;
                });
            }
        });
    });

    // -- Repeatable rows -----------------------------------------------------
    $$('[data-repeater]').forEach(function (section) {
        var name = section.getAttribute('data-repeater');
        var rows = $('[data-repeater-rows]', section);
        if (!rows) { return; }

        function renumber() {
            $$('[data-repeater-row]', rows).forEach(function (row, index) {
                $$('input, textarea', row).forEach(function (field) {
                    field.name = field.name.replace(
                        new RegExp('^' + name + '\\[\\d*\\]'),
                        name + '[' + index + ']'
                    );
                });
            });
        }

        var addButton = $('[data-repeater-add]', section);
        if (addButton) {
            addButton.addEventListener('click', function () {
                var all = $$('[data-repeater-row]', rows);
                var copy = all[all.length - 1].cloneNode(true);
                $$('input, textarea', copy).forEach(function (field) { field.value = ''; });
                rows.appendChild(copy);
                renumber();
                var first = $('input, textarea', copy);
                if (first) { first.focus(); }
            });
        }

        rows.addEventListener('click', function (event) {
            if (!event.target.closest('[data-repeater-remove]')) { return; }
            var all = $$('[data-repeater-row]', rows);
            var row = event.target.closest('[data-repeater-row]');
            if (all.length === 1) {
                $$('input, textarea', row).forEach(function (field) { field.value = ''; });
            } else {
                row.remove();
            }
            renumber();
        });
    });

    // -- Live SEO score ------------------------------------------------------
    (function score() {
        var form  = $('#editor');
        var panel = $('#seo-panel');
        if (!form || !panel) { return; }

        var url   = form.getAttribute('data-score-url');
        var value = $('[data-score-value]', panel);
        var grade = $('[data-score-grade]', panel);
        var list  = $('[data-score-list]', panel);

        var refresh = debounce(function () {
            var body = new URLSearchParams(new FormData(form));
            fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': csrfToken() },
                body: body.toString()
            }).then(function (r) { return r.ok ? r.json() : null; }).then(function (data) {
                if (!data || typeof data.score !== 'number') { return; }
                value.textContent = String(data.score);
                grade.textContent = data.grade + ' · ' + data.word_count + ' words';

                list.textContent = '';
                (data.checks || []).forEach(function (check) {
                    var li = document.createElement('li');
                    li.className = check.passed ? 'pass' : 'fail';
                    var label = document.createElement('strong');
                    label.textContent = check.label;
                    li.appendChild(label);
                    if (!check.passed && check.advice) {
                        var advice = document.createElement('span');
                        advice.textContent = check.advice;
                        li.appendChild(advice);
                    }
                    list.appendChild(li);
                });
            }).catch(function () { /* the score on save is what counts */ });
        }, 600);

        form.addEventListener('input', refresh);
        form.addEventListener('change', refresh);
    })();

    // -- Cover image preview -------------------------------------------------
    (function cover() {
        var select  = $('[data-cover-select]');
        var preview = $('[data-cover-preview]');
        if (!select || !preview) { return; }

        select.addEventListener('change', function () {
            var option = select.options[select.selectedIndex];
            var path   = option ? option.getAttribute('data-path') : '';
            preview.textContent = '';
            if (!path) { return; }
            var img = document.createElement('img');
            img.src = path;
            img.alt = '';
            img.width = 200;
            preview.appendChild(img);
        });
    })();
})();
