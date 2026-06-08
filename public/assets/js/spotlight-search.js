/**
 * Spotlight Search for VLSM
 * A macOS Spotlight-like global search for quick menu navigation
 */
(function($) {
    'use strict';

    var SpotlightSearch = {
        isOpen: false,
        selectedIndex: -1,
        expandedIndex: -1,
        selectedActionIndex: -1,
        filteredResults: [],
        currentQueryWords: [],
        maxHistoryItems: 5,
        isMouseMoving: false,
        mouseMovementTimeout: null,

        init: function() {
            this.bindEvents();
            this.createResultsCache();
        },

        getStorageKey: function() {
            var userId = window.spotlightUserId || 'default';
            return 'spotlightHistory_' + userId;
        },

        getHistory: function() {
            try {
                var history = JSON.parse(localStorage.getItem(this.getStorageKey())) || [];
                var validIds = this.searchData.map(function(item) { return item.id; });
                return history.filter(function(h) {
                    return validIds.indexOf(h.id) !== -1;
                });
            } catch (e) {
                return [];
            }
        },

        addToHistory: function(itemId) {
            try {
                var history = this.getHistory();
                history = history.filter(function(h) { return h.id !== itemId; });
                history.unshift({ id: itemId, timestamp: Date.now() });
                history = history.slice(0, this.maxHistoryItems);
                localStorage.setItem(this.getStorageKey(), JSON.stringify(history));
            } catch (e) {
                // localStorage not available or quota exceeded
            }
        },

        getRecentItems: function() {
            var self = this;
            var history = this.getHistory();
            var recentItems = [];

            history.forEach(function(h) {
                var item = self.searchData.find(function(i) { return i.id === h.id; });
                if (item) {
                    recentItems.push(item);
                }
            });

            return recentItems;
        },

        createResultsCache: function() {
            this.searchData = (window.spotlightData || []).map(function(item, index) {
                // For expandable items, only match on title/module/category (not child actions)
                // Child actions are already added as separate searchable items
                return $.extend({}, item, {
                    _originalIndex: index,
                    searchText: [
                        item.title,
                        item.category,
                        item.subcategory || '',
                        item.module || '',
                        (item.keywords || []).join(' ')
                    ].join(' ').toLowerCase()
                });
            });
        },

        bindEvents: function() {
            var self = this;

            // Keyboard shortcut: Ctrl+K or Cmd+K
            $(document).on('keydown', function(e) {
                if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                    e.preventDefault();
                    self.toggle();
                }

                // ESC to close or collapse
                if (e.key === 'Escape' && self.isOpen) {
                    if (self.expandedIndex >= 0) {
                        self.collapseItem();
                    } else {
                        self.close();
                    }
                }
            });

            // Click trigger button
            $(document).on('click', '#spotlightTrigger', function(e) {
                e.preventDefault();
                self.toggle();
            });

            // Click backdrop to close
            $(document).on('click', '.spotlight-backdrop', function() {
                self.close();
            });

            // Input handling
            $(document).on('input', '#spotlightInput', function() {
                self.expandedIndex = -1;
                self.selectedActionIndex = -1;
                self.search($(this).val());
            });

            // Keyboard navigation in results
            $(document).on('keydown', '#spotlightInput', function(e) {
                self.handleKeyNavigation(e);
            });

            // Click on regular result item (non-expandable)
            $(document).on('click', '.spotlight-result-item:not(.spotlight-expandable)', function(e) {
                e.preventDefault();
                var url = $(this).data('url');
                var itemId = $(this).data('item-id');
                if (itemId) {
                    self.addToHistory(itemId);
                }
                if (url) {
                    window.location.href = url;
                }
            });

            // Click on expandable item to toggle
            $(document).on('click', '.spotlight-expandable', function(e) {
                e.preventDefault();
                e.stopPropagation();
                var index = parseInt($(this).data('index'));
                if (self.expandedIndex === index) {
                    self.collapseItem();
                } else {
                    self.expandItem(index);
                }
            });

            // Click on action item
            $(document).on('click', '.spotlight-action-item', function(e) {
                e.preventDefault();
                e.stopPropagation();
                var url = $(this).data('url');
                var parentId = $(this).closest('.spotlight-actions').data('parent-id');
                if (parentId) {
                    self.addToHistory(parentId);
                }
                if (url) {
                    window.location.href = url;
                }
            });

            // Hover on result (no scroll, just highlight)
            $(document).on('mouseenter', '.spotlight-result-item', function() {
                if (!self.isMouseMoving) return;
                if (!$(this).hasClass('spotlight-expandable') || self.expandedIndex < 0) {
                    self.selectedIndex = parseInt($(this).data('index'));
                    self.selectedActionIndex = -1;
                    self.updateSelection(false);
                }
            });

            // Hover on action (no scroll, just highlight)
            $(document).on('mouseenter', '.spotlight-action-item', function() {
                if (!self.isMouseMoving) return;
                self.selectedActionIndex = parseInt($(this).data('action-index'));
                self.updateActionSelection(false);
            });

            // Track mouse movement to distinguish actual hover from static mouse under modal
            $(document).on('mousemove', '#spotlightResults', function() {
                self.isMouseMoving = true;
                $('.spotlight-dialog').addClass('spotlight-mouse-active');
                clearTimeout(self.mouseMovementTimeout);
                self.mouseMovementTimeout = setTimeout(function() {
                    self.isMouseMoving = false;
                    $('.spotlight-dialog').removeClass('spotlight-mouse-active');
                }, 100);
            });
        },

        toggle: function() {
            if (this.isOpen) {
                this.close();
            } else {
                this.open();
            }
        },

        open: function() {
            this.isOpen = true;
            this.selectedIndex = -1;
            this.expandedIndex = -1;
            this.selectedActionIndex = -1;
            this.isMouseMoving = false;
            $('.spotlight-dialog').removeClass('spotlight-mouse-active');
            $('#spotlightModal').fadeIn(150);
            $('#spotlightInput').val('').attr('aria-expanded', 'true').focus();
            this.showDefaultResults();
            $('body').addClass('spotlight-open');
        },

        close: function() {
            this.isOpen = false;
            this.expandedIndex = -1;
            this.selectedActionIndex = -1;
            this.currentQueryWords = [];
            $('#spotlightModal').fadeOut(150);
            $('#spotlightInput').val('').attr('aria-expanded', 'false').attr('aria-activedescendant', '');
            $('#spotlightResults').empty();
            $('body').removeClass('spotlight-open');
        },

        expandItem: function(index) {
            this.expandedIndex = index;
            this.selectedActionIndex = 0;
            this.selectedIndex = index;
            this.renderResults();
        },

        collapseItem: function() {
            this.expandedIndex = -1;
            this.selectedActionIndex = -1;
            this.renderResults();
        },

        showDefaultResults: function() {
            var self = this;
            this.currentQueryWords = [];
            var recentItems = this.getRecentItems();

            if (recentItems.length === 0) {
                this.filteredResults = this.searchData.slice(0, 10);
                this.renderResults();
                return;
            }

            this.filteredResults = [];
            var recentCategory = (window.spotlightTranslations && window.spotlightTranslations.recent) || 'Recent';

            recentItems.forEach(function(item) {
                self.filteredResults.push($.extend({}, item, { category: recentCategory }));
            });

            this.renderResults();
        },

        search: function(query) {
            query = query.toLowerCase().trim();

            if (!query) {
                this.showDefaultResults();
                return;
            }

            var queryWords = query.split(/\s+/).filter(function(w) { return w.length > 0; });
            var queryNormalized = queryWords.join(' ');
            this.currentQueryWords = queryWords;

            this.filteredResults = this.searchData.filter(function(item) {
                return queryWords.every(function(word) {
                    return item.searchText.indexOf(word) !== -1;
                });
            });

            // Typo / fuzzy fallback: only when a strict substring match found nothing
            // and the query is long enough that fuzzy results stay meaningful.
            if (this.filteredResults.length === 0 && query.replace(/\s+/g, '').length >= 3) {
                this.filteredResults = this.fuzzySearch(queryWords);
                if (this.filteredResults.length > 0) {
                    this.selectedIndex = 0;
                    this.renderResults();
                    return;
                }
            }

            var hasExactModuleMatch = this.filteredResults.some(function(item) {
                return (item.module || '').toLowerCase() === queryNormalized;
            });

            var firstWord = queryWords[0] || '';
            this.filteredResults.sort(function(a, b) {
                var aTitle = a.title.toLowerCase();
                var bTitle = b.title.toLowerCase();
                var aModule = (a.module || '').toLowerCase();
                var bModule = (b.module || '').toLowerCase();

                // Priority 1: Exact module match (only when query matches a module key)
                if (hasExactModuleMatch) {
                    var aModuleExact = aModule === queryNormalized;
                    var bModuleExact = bModule === queryNormalized;
                    if (aModuleExact !== bModuleExact) return bModuleExact - aModuleExact;

                    // For exact module match, show module-level (expandable) items first
                    if (aModuleExact && bModuleExact) {
                        var aIsModuleItem = !!a.isExpandable;
                        var bIsModuleItem = !!b.isExpandable;
                        if (aIsModuleItem !== bIsModuleItem) return bIsModuleItem - aIsModuleItem;
                    }
                }

                // Priority 2: Title contains search term
                var aTitleMatch = queryWords.every(function(w) { return aTitle.indexOf(w) !== -1; });
                var bTitleMatch = queryWords.every(function(w) { return bTitle.indexOf(w) !== -1; });
                if (aTitleMatch !== bTitleMatch) return bTitleMatch - aTitleMatch;

                // Priority 3: Title starts with first word
                var aStarts = aTitle.indexOf(firstWord) === 0;
                var bStarts = bTitle.indexOf(firstWord) === 0;
                if (aStarts !== bStarts) return bStarts - aStarts;

                // Priority 4: Use menu sort_order
                var sortOrderDiff = (a.sortOrder || 0) - (b.sortOrder || 0);
                if (sortOrderDiff !== 0) return sortOrderDiff;

                // Priority 5: Preserve original array order (menu hierarchy order)
                return (a._originalIndex || 0) - (b._originalIndex || 0);
            });

            this.selectedIndex = this.filteredResults.length > 0 ? 0 : -1;
            this.renderResults();
        },

        // Typo-tolerant fallback. Each query word must match some token of an item
        // by prefix, subsequence, or small edit distance. Lower score = better match.
        fuzzySearch: function(queryWords) {
            var self = this;
            var scored = [];

            this.searchData.forEach(function(item) {
                var tokens = item.searchText.split(/[^a-z0-9]+/).filter(Boolean);
                var total = 0;
                var matchedAll = queryWords.every(function(word) {
                    var best = self.bestWordScore(word, tokens, item.searchText);
                    if (best === null) return false;
                    total += best;
                    return true;
                });
                if (matchedAll) {
                    scored.push({ item: item, score: total });
                }
            });

            scored.sort(function(a, b) {
                if (a.score !== b.score) return a.score - b.score;
                return (a.item._originalIndex || 0) - (b.item._originalIndex || 0);
            });

            return scored.slice(0, 20).map(function(s) { return s.item; });
        },

        bestWordScore: function(word, tokens, searchText) {
            var best = null;
            var maxDist = word.length <= 4 ? 1 : 2;

            for (var i = 0; i < tokens.length; i++) {
                var token = tokens[i];
                var score = null;

                if (token === word) {
                    score = 0;
                } else if (token.indexOf(word) === 0) {
                    score = 1;
                } else {
                    var dist = this.levenshtein(word, token, maxDist);
                    if (dist <= maxDist) {
                        score = 3 + dist;
                    }
                }

                if (score !== null && (best === null || score < best)) {
                    best = score;
                    if (best === 0) break;
                }
            }

            // Subsequence over the whole searchText catches dropped letters across tokens
            if (best === null && this.isSubsequence(word, searchText)) {
                best = 6;
            }

            return best;
        },

        isSubsequence: function(needle, haystack) {
            var j = 0;
            for (var i = 0; i < haystack.length && j < needle.length; i++) {
                if (haystack[i] === needle[j]) j++;
            }
            return j === needle.length;
        },

        // Bounded Levenshtein — bails out early once the best possible distance
        // on a row exceeds maxDist, keeping it cheap for the no-match case.
        levenshtein: function(a, b, maxDist) {
            if (Math.abs(a.length - b.length) > maxDist) return maxDist + 1;
            var prev = [];
            for (var j = 0; j <= b.length; j++) prev[j] = j;

            for (var i = 1; i <= a.length; i++) {
                var curr = [i];
                var rowMin = i;
                for (var k = 1; k <= b.length; k++) {
                    var cost = a.charAt(i - 1) === b.charAt(k - 1) ? 0 : 1;
                    curr[k] = Math.min(prev[k] + 1, curr[k - 1] + 1, prev[k - 1] + cost);
                    if (curr[k] < rowMin) rowMin = curr[k];
                }
                if (rowMin > maxDist) return maxDist + 1;
                prev = curr;
            }
            return prev[b.length];
        },

        // Escape text, then wrap any literal query-word occurrences in <mark>.
        highlight: function(text, queryWords) {
            if (!text) return '';
            if (!queryWords || !queryWords.length) return this.escapeHtml(text);

            var patterns = queryWords
                .map(function(w) { return w.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'); })
                .filter(Boolean);
            if (!patterns.length) return this.escapeHtml(text);

            var re = new RegExp('(' + patterns.join('|') + ')', 'gi');
            var result = '';
            var lastIndex = 0;
            var m;
            while ((m = re.exec(text)) !== null) {
                result += this.escapeHtml(text.slice(lastIndex, m.index));
                result += '<mark class="spotlight-highlight">' + this.escapeHtml(m[0]) + '</mark>';
                lastIndex = m.index + m[0].length;
                if (m.index === re.lastIndex) re.lastIndex++;
            }
            result += this.escapeHtml(text.slice(lastIndex));
            return result;
        },

        renderResults: function() {
            var self = this;
            var $container = $('#spotlightResults');
            $container.empty();

            if (this.filteredResults.length === 0) {
                $container.html(
                    '<div class="spotlight-no-results">' +
                    '<i class="fa-solid fa-magnifying-glass"></i>' +
                    '<p>' + ((window.spotlightTranslations && window.spotlightTranslations.noResults) || 'No results found') + '</p>' +
                    '</div>'
                );
                return;
            }

            var html = '';
            var globalIndex = 0;

            // Group by category
            var grouped = {};
            this.filteredResults.forEach(function(item, idx) {
                item._globalIndex = idx;
                var cat = item.category || 'Other';
                if (!grouped[cat]) grouped[cat] = [];
                grouped[cat].push(item);
            });

            var categoryOrder = {};
            this.filteredResults.forEach(function(item) {
                var cat = item.category || 'Other';
                if (categoryOrder[cat] === undefined) {
                    categoryOrder[cat] = item._globalIndex;
                }
            });

            var sortedCategories = Object.keys(grouped).sort(function(a, b) {
                if (a === 'Recent') return -1;
                if (b === 'Recent') return 1;
                var aOrder = categoryOrder[a];
                var bOrder = categoryOrder[b];
                if (aOrder !== undefined && bOrder !== undefined && aOrder !== bOrder) {
                    return aOrder - bOrder;
                }
                return a.localeCompare(b);
            });

            sortedCategories.forEach(function(category) {
                html += '<div class="spotlight-category" role="group" aria-label="' + self.escapeHtml(category) + '">';
                html += '<div class="spotlight-category-title" aria-hidden="true">' + self.escapeHtml(category) + '</div>';

                grouped[category].forEach(function(item) {
                    var isSelected = item._globalIndex === self.selectedIndex;
                    var isExpanded = item._globalIndex === self.expandedIndex;
                    var isExpandable = item.isExpandable && item.actions && item.actions.length > 0;

                    var itemClass = 'spotlight-result-item';
                    if (isSelected) itemClass += ' selected';
                    if (isExpanded) itemClass += ' expanded';
                    if (isExpandable) itemClass += ' spotlight-expandable';

                    html += '<div class="' + itemClass + '" role="option" ' +
                            'id="spotlight-opt-' + item._globalIndex + '" ' +
                            'aria-selected="' + (isSelected ? 'true' : 'false') + '" ' +
                            'data-url="' + self.escapeHtml(item.url) + '" ' +
                            'data-index="' + item._globalIndex + '" ' +
                            'data-item-id="' + self.escapeHtml(item.id) + '">';
                    html += '<div class="spotlight-item-icon"><i class="' + self.escapeHtml(item.icon || 'fa-solid fa-file') + '"></i></div>';
                    html += '<div class="spotlight-item-content">';
                    html += '<span class="spotlight-item-title">' + self.highlight(item.title, self.currentQueryWords) + '</span>';
                    if (item.subcategory) {
                        html += '<span class="spotlight-item-path">' + self.highlight(item.subcategory, self.currentQueryWords) + '</span>';
                    }
                    html += '</div>';

                    if (isExpandable) {
                        html += '<i class="fa-solid fa-chevron-' + (isExpanded ? 'down' : 'right') + ' spotlight-item-chevron"></i>';
                    } else {
                        html += '<i class="fa-solid fa-arrow-right spotlight-item-arrow"></i>';
                    }
                    html += '</div>';

                    // Render actions if expanded
                    if (isExpanded && item.actions) {
                        html += '<div class="spotlight-actions" data-parent-id="' + self.escapeHtml(item.id) + '">';
                        item.actions.forEach(function(action, actionIdx) {
                            var isActionSelected = actionIdx === self.selectedActionIndex;
                            html += '<div class="spotlight-action-item' + (isActionSelected ? ' selected' : '') + '" role="option" ' +
                                    'id="spotlight-act-' + actionIdx + '" ' +
                                    'aria-selected="' + (isActionSelected ? 'true' : 'false') + '" ' +
                                    'data-url="' + self.escapeHtml(action.url) + '" ' +
                                    'data-action-index="' + actionIdx + '">';
                            html += '<i class="' + self.escapeHtml(action.icon || 'fa-solid fa-arrow-right') + ' spotlight-action-icon"></i>';
                            html += '<span class="spotlight-action-label">' + self.highlight(action.label, self.currentQueryWords) + '</span>';
                            html += '</div>';
                        });
                        html += '</div>';
                    }
                });

                html += '</div>';
            });

            $container.html(html);
            this.syncAria();
        },

        // Point the combobox's aria-activedescendant at the focused option so
        // screen readers announce the current selection as the user navigates.
        syncAria: function() {
            var activeId = '';
            if (this.expandedIndex >= 0 && this.selectedActionIndex >= 0) {
                activeId = 'spotlight-act-' + this.selectedActionIndex;
            } else if (this.selectedIndex >= 0) {
                activeId = 'spotlight-opt-' + this.selectedIndex;
            }
            $('#spotlightInput').attr('aria-activedescendant', activeId);
        },

        escapeHtml: function(text) {
            if (!text) return '';
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },

        handleKeyNavigation: function(e) {
            var self = this;

            // If expanded, navigate actions
            if (this.expandedIndex >= 0) {
                var item = this.filteredResults[this.expandedIndex];
                var maxActionIndex = item && item.actions ? item.actions.length - 1 : -1;

                switch(e.key) {
                    case 'ArrowDown':
                        e.preventDefault();
                        if (this.selectedActionIndex < maxActionIndex) {
                            this.selectedActionIndex++;
                            this.updateActionSelection();
                        }
                        break;

                    case 'ArrowUp':
                        e.preventDefault();
                        if (this.selectedActionIndex > 0) {
                            this.selectedActionIndex--;
                            this.updateActionSelection();
                        } else {
                            // Collapse when going up from first action
                            this.collapseItem();
                        }
                        break;

                    case 'ArrowLeft':
                        e.preventDefault();
                        this.collapseItem();
                        break;

                    case 'Enter':
                        e.preventDefault();
                        if (this.selectedActionIndex >= 0 && item && item.actions[this.selectedActionIndex]) {
                            this.addToHistory(item.id);
                            window.location.href = item.actions[this.selectedActionIndex].url;
                        }
                        break;
                }
                return;
            }

            // Normal navigation
            var maxIndex = this.filteredResults.length - 1;

            switch(e.key) {
                case 'ArrowDown':
                    e.preventDefault();
                    if (this.selectedIndex < maxIndex) {
                        this.selectedIndex++;
                        this.updateSelection();
                    }
                    break;

                case 'ArrowUp':
                    e.preventDefault();
                    if (this.selectedIndex > 0) {
                        this.selectedIndex--;
                        this.updateSelection();
                    }
                    break;

                case 'ArrowRight':
                    e.preventDefault();
                    // Expand if expandable
                    if (this.selectedIndex >= 0) {
                        var selectedItem = this.filteredResults[this.selectedIndex];
                        if (selectedItem && selectedItem.isExpandable) {
                            this.expandItem(this.selectedIndex);
                        }
                    }
                    break;

                case 'Enter':
                    e.preventDefault();
                    if (this.selectedIndex >= 0 && this.filteredResults[this.selectedIndex]) {
                        var selectedItem = this.filteredResults[this.selectedIndex];
                        // If expandable, expand instead of navigating
                        if (selectedItem.isExpandable) {
                            this.expandItem(this.selectedIndex);
                        } else {
                            if (selectedItem.id) {
                                this.addToHistory(selectedItem.id);
                            }
                            window.location.href = selectedItem.url;
                        }
                    }
                    break;
            }
        },

        updateSelection: function(scroll) {
            var $items = $('.spotlight-result-item');
            $items.removeClass('selected').attr('aria-selected', 'false');
            var $selected = $items.filter('[data-index="' + this.selectedIndex + '"]');
            $selected.addClass('selected').attr('aria-selected', 'true');
            this.syncAria();

            if (scroll !== false && $selected.length) {
                this.scrollIntoView($selected);
            }
        },

        updateActionSelection: function(scroll) {
            var $actions = $('.spotlight-action-item');
            $actions.removeClass('selected').attr('aria-selected', 'false');
            var $selected = $actions.filter('[data-action-index="' + this.selectedActionIndex + '"]');
            $selected.addClass('selected').attr('aria-selected', 'true');
            this.syncAria();

            if (scroll !== false && $selected.length) {
                this.scrollIntoView($selected);
            }
        },

        scrollIntoView: function($element) {
            var $container = $('#spotlightResults');
            var containerTop = $container.scrollTop();
            var containerHeight = $container.height();
            var itemTop = $element.position().top + containerTop;
            var itemHeight = $element.outerHeight();

            if (itemTop < containerTop) {
                $container.scrollTop(itemTop);
            } else if (itemTop + itemHeight > containerTop + containerHeight) {
                $container.scrollTop(itemTop + itemHeight - containerHeight);
            }
        }
    };

    $(document).ready(function() {
        SpotlightSearch.init();
    });

    window.SpotlightSearch = SpotlightSearch;

})(jQuery);
