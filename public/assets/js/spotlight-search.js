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
        maxHistoryItems: 5,

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
                if (!$(this).hasClass('spotlight-expandable') || self.expandedIndex < 0) {
                    self.selectedIndex = parseInt($(this).data('index'));
                    self.selectedActionIndex = -1;
                    self.updateSelection(false);
                }
            });

            // Hover on action (no scroll, just highlight)
            $(document).on('mouseenter', '.spotlight-action-item', function() {
                self.selectedActionIndex = parseInt($(this).data('action-index'));
                self.updateActionSelection(false);
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
            $('#spotlightModal').fadeIn(150);
            $('#spotlightInput').val('').focus();
            this.showDefaultResults();
            $('body').addClass('spotlight-open');
        },

        close: function() {
            this.isOpen = false;
            this.expandedIndex = -1;
            this.selectedActionIndex = -1;
            $('#spotlightModal').fadeOut(150);
            $('#spotlightInput').val('');
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

            this.filteredResults = this.searchData.filter(function(item) {
                return queryWords.every(function(word) {
                    return item.searchText.indexOf(word) !== -1;
                });
            });

            var firstWord = queryWords[0] || '';
            this.filteredResults.sort(function(a, b) {
                var aTitle = a.title.toLowerCase();
                var bTitle = b.title.toLowerCase();
                var aModule = (a.module || '').toLowerCase();
                var bModule = (b.module || '').toLowerCase();

                // Priority 1: Module matches search term (vl items should come first when searching "vl")
                var aModuleMatch = queryWords.some(function(w) { return aModule.indexOf(w) !== -1; });
                var bModuleMatch = queryWords.some(function(w) { return bModule.indexOf(w) !== -1; });
                if (aModuleMatch !== bModuleMatch) return bModuleMatch - aModuleMatch;

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

            var sortedCategories = Object.keys(grouped).sort(function(a, b) {
                if (a === 'Recent') return -1;
                if (b === 'Recent') return 1;
                return a.localeCompare(b);
            });

            sortedCategories.forEach(function(category) {
                html += '<div class="spotlight-category">';
                html += '<div class="spotlight-category-title">' + self.escapeHtml(category) + '</div>';

                grouped[category].forEach(function(item) {
                    var isSelected = item._globalIndex === self.selectedIndex;
                    var isExpanded = item._globalIndex === self.expandedIndex;
                    var isExpandable = item.isExpandable && item.actions && item.actions.length > 0;

                    var itemClass = 'spotlight-result-item';
                    if (isSelected) itemClass += ' selected';
                    if (isExpanded) itemClass += ' expanded';
                    if (isExpandable) itemClass += ' spotlight-expandable';

                    html += '<div class="' + itemClass + '" ' +
                            'data-url="' + self.escapeHtml(item.url) + '" ' +
                            'data-index="' + item._globalIndex + '" ' +
                            'data-item-id="' + self.escapeHtml(item.id) + '">';
                    html += '<div class="spotlight-item-icon"><i class="' + self.escapeHtml(item.icon || 'fa-solid fa-file') + '"></i></div>';
                    html += '<div class="spotlight-item-content">';
                    html += '<span class="spotlight-item-title">' + self.escapeHtml(item.title) + '</span>';
                    if (item.subcategory) {
                        html += '<span class="spotlight-item-path">' + self.escapeHtml(item.subcategory) + '</span>';
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
                            html += '<div class="spotlight-action-item' + (isActionSelected ? ' selected' : '') + '" ' +
                                    'data-url="' + self.escapeHtml(action.url) + '" ' +
                                    'data-action-index="' + actionIdx + '">';
                            html += '<i class="' + self.escapeHtml(action.icon || 'fa-solid fa-arrow-right') + ' spotlight-action-icon"></i>';
                            html += '<span class="spotlight-action-label">' + self.escapeHtml(action.label) + '</span>';
                            html += '</div>';
                        });
                        html += '</div>';
                    }
                });

                html += '</div>';
            });

            $container.html(html);
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
            $items.removeClass('selected');
            var $selected = $items.filter('[data-index="' + this.selectedIndex + '"]');
            $selected.addClass('selected');

            if (scroll !== false && $selected.length) {
                this.scrollIntoView($selected);
            }
        },

        updateActionSelection: function(scroll) {
            var $actions = $('.spotlight-action-item');
            $actions.removeClass('selected');
            var $selected = $actions.filter('[data-action-index="' + this.selectedActionIndex + '"]');
            $selected.addClass('selected');

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
