/* WP Portfolio Catalogue. GPL-2.0-or-later. No external runtime dependencies. */
(function () {
    'use strict';

    var reducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    function initCatalogue(root) {
        if (root.dataset.wppcReady) {
            return;
        }
        var config;
        try {
            config = JSON.parse(root.dataset.wppcCatalogue);
        } catch (error) {
            return;
        }
        var grid = root.querySelector('.wppc-grid');
        var form = root.querySelector('.wppc-filters');
        var more = root.querySelector('.wppc-more');
        var previous = root.querySelector('.wppc-previous-page');
        var count = root.querySelector('.wppc-result-count');
        var status = root.querySelector('.wppc-request-status');
        var empty = root.querySelector('.wppc-empty');
        if (!grid || !more || !window.fetch || !window.URL) {
            return;
        }
        root.dataset.wppcReady = 'true';

        var state = {
            page: config.page,
            pages: config.pages,
            sequence: 0,
            controller: null,
            timer: null,
            failed: false,
            loading: false,
            signature: ''
        };

        function activeFilters() {
            var values = {
                s: config.options.s || '',
                filter_category: config.options.filter_category || '',
                filter_tag: config.options.filter_tag || ''
            };
            if (form) {
                var search = form.querySelector('[data-wppc-search]');
                var category = form.querySelector('[data-wppc-filter="category"]');
                var tag = form.querySelector('[data-wppc-filter="tag"]');
                if (search) { values.s = search.value.trim(); }
                if (category) { values.filter_category = category.value; }
                if (tag) { values.filter_tag = tag.value; }
            }
            return values;
        }

        function signature(filters) {
            return JSON.stringify([filters.s, filters.filter_category, filters.filter_tag]);
        }

        function pageUrl(page, filters) {
            var url = new URL(window.location.href);
            [['s', filters.s], ['category', filters.filter_category], ['tag', filters.filter_tag]].forEach(function (entry) {
                if (entry[1]) {
                    url.searchParams.set(config.prefix + entry[0], entry[1]);
                } else {
                    url.searchParams.delete(config.prefix + entry[0]);
                }
            });
            if (page > 1) {
                url.searchParams.set(config.prefix + 'page', String(page));
            } else {
                url.searchParams.delete(config.prefix + 'page');
            }
            url.hash = root.id;
            return url.toString();
        }

        function updatePageLinks(filters) {
            more.href = pageUrl(state.page + 1, filters);
            more.hidden = state.page >= state.pages;
            previous.href = pageUrl(Math.max(1, state.page - 1), filters);
            previous.hidden = state.page <= 1;
        }

        function setLoading(loading) {
            state.loading = loading;
            root.setAttribute('aria-busy', loading ? 'true' : 'false');
            root.classList.toggle('wppc-is-loading', loading);
            more.setAttribute('aria-disabled', loading ? 'true' : 'false');
            if (loading) {
                status.textContent = config.loading;
                status.classList.remove('wppc-error');
            }
        }

        function request(page, append) {
            window.clearTimeout(state.timer);
            var filters = activeFilters();
            var currentSignature = signature(filters);
            if (append && state.signature !== currentSignature) {
                append = false;
                page = 1;
            }
            var sequence = ++state.sequence;
            if (state.controller) {
                state.controller.abort();
            }
            state.controller = window.AbortController ? new AbortController() : null;
            state.failed = false;
            setLoading(true);
            var url = new URL(config.endpoint, window.location.href);
            Object.keys(config.options).forEach(function (key) {
                url.searchParams.set(key, String(config.options[key]));
            });
            Object.keys(filters).forEach(function (key) {
                url.searchParams.set(key, filters[key]);
            });
            url.searchParams.set('page', String(page));
            var fetchOptions = { credentials: 'same-origin', headers: { Accept: 'application/json' } };
            if (state.controller) {
                fetchOptions.signal = state.controller.signal;
            }

            window.fetch(url.toString(), fetchOptions).then(function (response) {
                if (!response.ok) {
                    throw new Error('Catalogue response failed');
                }
                return response.json();
            }).then(function (data) {
                if (sequence !== state.sequence || !root.isConnected) {
                    return;
                }
                if (!data || typeof data.html !== 'string' || !Number.isFinite(data.total) || !Number.isFinite(data.pages) || !Number.isFinite(data.page)) {
                    throw new Error('Invalid catalogue response');
                }
                var template = document.createElement('template');
                // The plugin's own same-origin endpoint escapes every card field on the server.
                template.innerHTML = data.html;
                if (!append) {
                    grid.replaceChildren();
                }
                var firstNew = template.content.firstElementChild;
                // Do not duplicate a project if content changed between paginated requests.
                Array.prototype.slice.call(template.content.children).forEach(function (card) {
                    if (!card.classList.contains('wppc-card')) {
                        card.remove();
                        return;
                    }
                    if (append && Array.prototype.some.call(grid.children, function (existing) { return existing.dataset.projectId === card.dataset.projectId; })) {
                        card.remove();
                    }
                });
                firstNew = template.content.firstElementChild;
                grid.appendChild(template.content);
                state.page = data.page;
                state.pages = data.pages;
                state.signature = currentSignature;
                var visible = grid.querySelectorAll('.wppc-card').length;
                empty.hidden = visible > 0;
                count.textContent = config.count.replace('%1$s', visible.toLocaleString()).replace('%2$s', data.total.toLocaleString());
                status.textContent = '';
                updatePageLinks(filters);
                if (append && firstNew) {
                    var link = firstNew.querySelector('.wppc-card-title a');
                    if (link) { link.focus({ preventScroll: true }); }
                } else if (!append && document.activeElement === more) {
                    var first = grid.querySelector('.wppc-card-title a');
                    if (first) { first.focus({ preventScroll: true }); }
                }
            }).catch(function (error) {
                if (sequence !== state.sequence || error.name === 'AbortError' || !root.isConnected) {
                    return;
                }
                state.failed = true;
                status.textContent = config.error;
                status.classList.add('wppc-error');
                // Keep genuine navigation links available if REST is blocked by the host.
                more.href = pageUrl(page, filters);
                previous.href = pageUrl(Math.max(1, page - 1), filters);
            }).finally(function () {
                if (sequence === state.sequence && root.isConnected) {
                    setLoading(false);
                }
            });
        }

        state.signature = signature(activeFilters());

        more.addEventListener('click', function (event) {
            if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || state.failed) {
                return;
            }
            event.preventDefault();
            if (!state.loading) {
                request(state.page + 1, true);
            }
        });
        previous.addEventListener('click', function (event) {
            if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || state.failed) {
                return;
            }
            event.preventDefault();
            request(Math.max(1, state.page - 1), false);
        });

        if (form) {
            form.addEventListener('submit', function (event) {
                if (state.failed) {
                    return;
                }
                event.preventDefault();
                request(1, false);
            });
            form.querySelectorAll('[data-wppc-filter]').forEach(function (select) {
                select.addEventListener('change', function () { request(1, false); });
            });
            var search = form.querySelector('[data-wppc-search]');
            if (search) {
                search.addEventListener('input', function () {
                    window.clearTimeout(state.timer);
                    // Invalidate immediately, so an old response cannot replace newer input.
                    ++state.sequence;
                    if (state.controller) { state.controller.abort(); }
                    setLoading(false);
                    state.timer = window.setTimeout(function () { request(1, false); }, 320);
                });
            }
            var reset = form.querySelector('.wppc-reset');
            if (reset) {
                reset.addEventListener('click', function (event) {
                    if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || state.failed) {
                        return;
                    }
                    event.preventDefault();
                    form.querySelectorAll('[data-wppc-search], [data-wppc-filter]').forEach(function (input) { input.value = ''; });
                    config.options.s = '';
                    config.options.filter_category = '';
                    config.options.filter_tag = '';
                    request(1, false);
                });
            }
        }
    }

    function initGallery(root) {
        if (root.dataset.wppcReady) {
            return;
        }
        var viewport = root.querySelector('.wppc-gallery-viewport');
        var slides = Array.prototype.slice.call(root.querySelectorAll('.wppc-gallery-slide'));
        if (!viewport || !slides.length) {
            return;
        }
        root.dataset.wppcReady = 'true';
        var previous = root.querySelector('.wppc-gallery-prev');
        var next = root.querySelector('.wppc-gallery-next');
        var position = root.querySelector('.wppc-gallery-position');
        var thumbs = root.querySelectorAll('[data-wppc-image]');
        var current = 0;
        var scrollFrame = null;
        var dialog = null;
        var dialogImage = null;
        var dialogCaption = null;
        var dialogPosition = null;
        var dialogOriginal = null;
        var dialogPrevious = null;
        var dialogNext = null;
        var dialogIndex = 0;
        var opener = null;

        function sync() {
            var left = viewport.getBoundingClientRect().left;
            var closest = Infinity;
            slides.forEach(function (slide, index) {
                var distance = Math.abs(slide.getBoundingClientRect().left - left);
                if (distance < closest) {
                    closest = distance;
                    current = index;
                }
            });
            if (previous) { previous.disabled = current === 0; }
            if (next) { next.disabled = current === slides.length - 1; }
            if (position) { position.textContent = String(current + 1) + ' / ' + slides.length; }
            thumbs.forEach(function (thumb, index) { thumb.setAttribute('aria-current', index === current ? 'true' : 'false'); });
        }

        function go(index) {
            index = Math.max(0, Math.min(slides.length - 1, index));
            var target = slides[index].getBoundingClientRect();
            var bounds = viewport.getBoundingClientRect();
            // Scroll only this gallery. scrollIntoView can also move theme/builder ancestors.
            viewport.scrollTo({ left: viewport.scrollLeft + target.left - bounds.left, behavior: reducedMotion ? 'auto' : 'smooth' });
        }

        if (previous) { previous.addEventListener('click', function () { go(current - 1); }); }
        if (next) { next.addEventListener('click', function () { go(current + 1); }); }
        thumbs.forEach(function (thumb) {
            thumb.addEventListener('click', function () { go(parseInt(thumb.dataset.wppcImage, 10)); });
        });
        viewport.addEventListener('scroll', function () {
            if (scrollFrame) { window.cancelAnimationFrame(scrollFrame); }
            scrollFrame = window.requestAnimationFrame(sync);
        }, { passive: true });
        viewport.addEventListener('keydown', function (event) {
            if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') {
                event.preventDefault();
                var rtl = window.getComputedStyle(viewport).direction === 'rtl';
                var direction = event.key === 'ArrowRight' ? 1 : -1;
                go(current + (rtl ? -direction : direction));
            }
        });

        function updateDialog(index) {
            dialogIndex = Math.max(0, Math.min(slides.length - 1, index));
            var link = slides[dialogIndex].querySelector('.wppc-gallery-open');
            var img = link.querySelector('img');
            dialogImage.src = link.href;
            dialogImage.alt = img ? img.alt : '';
            dialogCaption.textContent = link.dataset.wppcCaption || '';
            dialogPosition.textContent = String(dialogIndex + 1) + ' / ' + slides.length;
            dialogOriginal.href = link.href;
            dialogPrevious.disabled = dialogIndex === 0;
            dialogNext.disabled = dialogIndex === slides.length - 1;
        }

        function createButton(className, label, symbol) {
            var button = document.createElement('button');
            button.type = 'button';
            button.className = className;
            button.setAttribute('aria-label', label);
            var span = document.createElement('span');
            span.setAttribute('aria-hidden', 'true');
            span.textContent = symbol;
            button.appendChild(span);
            return button;
        }

        function buildDialog() {
            dialog = document.createElement('dialog');
            if (typeof dialog.showModal !== 'function') {
                dialog = null;
                return false;
            }
            dialog.className = 'wppc-lightbox';
            dialog.setAttribute('aria-label', root.dataset.dialogLabel);
            var close = createButton('wppc-lightbox-close', root.dataset.closeLabel, '×');
            close.autofocus = true;
            var figure = document.createElement('figure');
            dialogImage = document.createElement('img');
            dialogImage.className = 'wppc-lightbox-image';
            dialogCaption = document.createElement('figcaption');
            figure.appendChild(dialogImage);
            figure.appendChild(dialogCaption);
            var bar = document.createElement('div');
            bar.className = 'wppc-lightbox-bar';
            dialogPrevious = createButton('wppc-lightbox-previous', root.dataset.prevLabel, '←');
            dialogNext = createButton('wppc-lightbox-next', root.dataset.nextLabel, '→');
            dialogPosition = document.createElement('span');
            dialogPosition.setAttribute('aria-live', 'polite');
            dialogOriginal = document.createElement('a');
            dialogOriginal.target = '_blank';
            dialogOriginal.rel = 'noopener noreferrer';
            dialogOriginal.textContent = root.dataset.originalLabel;
            bar.appendChild(dialogPrevious);
            bar.appendChild(dialogPosition);
            bar.appendChild(dialogNext);
            bar.appendChild(dialogOriginal);
            dialog.appendChild(close);
            dialog.appendChild(figure);
            dialog.appendChild(bar);
            document.body.appendChild(dialog);
            close.addEventListener('click', function () { dialog.close(); });
            dialogPrevious.addEventListener('click', function () { updateDialog(dialogIndex - 1); });
            dialogNext.addEventListener('click', function () { updateDialog(dialogIndex + 1); });
            dialog.addEventListener('click', function (event) {
                if (event.target === dialog) {
                    var bounds = dialog.getBoundingClientRect();
                    if (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom) { dialog.close(); }
                }
            });
            dialog.addEventListener('keydown', function (event) {
                if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') {
                    event.preventDefault();
                    updateDialog(dialogIndex + (event.key === 'ArrowRight' ? 1 : -1));
                }
            });
            dialog.addEventListener('close', function () {
                if (opener && opener.isConnected) { opener.focus({ preventScroll: true }); }
            });
            return true;
        }

        slides.forEach(function (slide, index) {
            var link = slide.querySelector('.wppc-gallery-open');
            if (!link) { return; }
            link.addEventListener('click', function (event) {
                if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) { return; }
                if (!dialog && !buildDialog()) { return; }
                event.preventDefault();
                opener = link;
                updateDialog(index);
                dialog.showModal();
            });
        });
        sync();
    }

    function initialize(scope) {
        if (scope.nodeType !== 1 && scope.nodeType !== 9) { return; }
        if (scope.matches && scope.matches('[data-wppc-catalogue]')) { initCatalogue(scope); }
        if (scope.matches && scope.matches('[data-wppc-gallery]')) { initGallery(scope); }
        scope.querySelectorAll('[data-wppc-catalogue]').forEach(initCatalogue);
        scope.querySelectorAll('[data-wppc-gallery]').forEach(initGallery);
    }

    function ready() {
        initialize(document);
        // Elementor popups, editor previews and other builders may insert content later.
        if (window.MutationObserver && document.body) {
            var observer = new MutationObserver(function (mutations) {
                mutations.forEach(function (mutation) {
                    mutation.addedNodes.forEach(function (node) {
                        if (node.nodeType === 1 && (node.matches('[data-wppc-catalogue], [data-wppc-gallery]') || node.querySelector('[data-wppc-catalogue], [data-wppc-gallery]'))) {
                            initialize(node);
                        }
                    });
                });
            });
            observer.observe(document.body, { childList: true, subtree: true });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', ready, { once: true });
    } else {
        ready();
    }
}());
