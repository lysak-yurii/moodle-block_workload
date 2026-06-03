// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Manage page AMD module for block_workload.
 *
 * Exports one init function per manage-page view:
 *   initList       – cohort list (modal triggers, toggle-switch forms)
 *   initMembers    – members page (panel toggle, user-search autocomplete,
 *                    submit-button state)
 *   initCourses    – courses page (submit-button state)
 *   initActivation – activation page (period-field visibility)
 *
 * @module    block_workload/manage
 * @copyright 2026 Yurii Lysak
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([
    'core/ajax',
    'core/modal_factory',
    'core/modal_events',
    'jquery'
], function(Ajax, ModalFactory, ModalEvents, $) {

    /**
     * Enable/disable a form's submit button based on whether any named
     * checkbox in that form is currently checked.
     *
     * @param {string} formId    id of the <form> element
     * @param {string} inputName name attribute of the checkboxes to watch
     */
    function bindSubmitToggle(formId, inputName) {
        var form = document.getElementById(formId);
        if (!form) {
            return;
        }
        var btn = form.querySelector('button[type="submit"]');
        if (!btn) {
            return;
        }

        /**
         * Update the button's disabled state.
         */
        var update = function() {
            var checked = Array.prototype.some.call(
                form.querySelectorAll('input[name="' + inputName + '"]'),
                function(cb) {
                    return cb.checked;
                }
            );
            btn.disabled = !checked;
        };

        // Change catches individual toggles; click + setTimeout catches
        // bulk-select via core/checkbox-toggleall which fires clicks, not changes.
        form.addEventListener('change', update);
        form.addEventListener('click', function() {
            setTimeout(update, 0);
        });
        update();
    }

    return {

        // -----------------------------------------------------------------
        // Cohort list page
        // -----------------------------------------------------------------

        /**
         * Wire up [data-modal-url] buttons to open iframe modals, and submit
         * the toggle-switch forms when their checkbox changes.
         */
        initList: function() {
            // Toggle-switch: submit the containing form on checkbox change.
            document.querySelectorAll('.wl-toggle-form input[type="checkbox"]').forEach(
                function(cb) {
                    cb.addEventListener('change', function() {
                        this.closest('form').submit();
                    });
                }
            );

            // Modal triggers.
            $(document).on('click', '[data-modal-url]', function(e) {
                e.preventDefault();
                var url = $(this).data('modal-url');
                var title = $(this).data('modal-title') || '';
                var large = !!($(this).data('modal-large'));
                var maxw = $(this).data('modal-maxwidth') || null;

                ModalFactory.create({
                    type: ModalFactory.types.DEFAULT,
                    title: title,
                    body: '<iframe src="' + url
                          + '" style="width:100%;min-height:160px;border:none;display:block;"></iframe>',
                    large: large,
                }).then(function(modal) {
                    modal.show();
                    if (maxw) {
                        modal.getModal().css('max-width', maxw + 'px');
                    }

                    // Resize iframe to its content height after load.
                    modal.getRoot().find('iframe').on('load', function() {
                        try {
                            var h = this.contentDocument.documentElement.scrollHeight;
                            if (h > 80) {
                                this.style.minHeight = (h + 16) + 'px';
                            }
                        } catch (ex) {
                            // Cross-origin guard.
                        }
                    });

                    // Close + reload when the inner form posts wl_modal_done.
                    var msgHandler = function(ev) {
                        if (ev.data !== 'wl_modal_done') {
                            return;
                        }
                        window.removeEventListener('message', msgHandler);
                        modal.destroy();
                        location.reload();
                    };
                    window.addEventListener('message', msgHandler);

                    modal.getRoot().on(ModalEvents.hidden, function() {
                        window.removeEventListener('message', msgHandler);
                        modal.destroy();
                    });

                    return modal;
                }).catch(function(err) {
                    window.console.error('WL modal error:', err);
                });
            });
        },

        // -----------------------------------------------------------------
        // Manage-members page
        // -----------------------------------------------------------------

        /**
         * Add-panel toggle, user-search autocomplete, and submit-button
         * state management for the three forms on the members page.
         */
        initMembers: function() {

            // Add-panel toggle.
            var panel = $('#workload-addpanel');
            var btn = $('#workload-addpanel-toggle');
            var openLbl = btn.data('label-open');
            var closeLbl = btn.data('label-close');

            btn.on('click', function(e) {
                e.preventDefault();
                if (panel.is(':visible')) {
                    panel.hide();
                    btn.html(openLbl);
                } else {
                    panel.show();
                    btn.html(closeLbl);
                    $('#wl-search').trigger('focus');
                }
            });

            // User-search autocomplete.
            var input = $('#wl-search');
            var filterForm = $('#workload-filter-form');
            var dropdown = null;
            var timer;
            var activeIdx = -1;

            /**
             * Update dropdown width to match input width.
             */
            var setWidth = function() {
                dropdown.css('width', input.outerWidth() + 'px');
            };

            /**
             * Hide and clear the autocomplete dropdown.
             */
            var hideDrop = function() {
                dropdown.hide().empty();
                activeIdx = -1;
            };

            /**
             * Select an autocomplete suggestion and submit the filter form.
             *
             * @param {jQuery} li The selected list item element.
             */
            var selectItem = function(li) {
                input.val(li.data('name'));
                hideDrop();
                filterForm.trigger('submit');
            };

            if (input.length) {
                input.parent().css('position', 'relative');
                dropdown = $('<ul class="workload-suggest"></ul>').appendTo(input.parent());

                input.on('input', function() {
                    clearTimeout(timer);
                    hideDrop();
                    var q = $.trim(input.val());
                    if (q.length < 2) {
                        return;
                    }
                    timer = setTimeout(function() {
                        Ajax.call([{
                            methodname: 'block_workload_search_users',
                            args: {query: q},
                            done: function(data) {
                                hideDrop();
                                if (!data.users || !data.users.length) {
                                    return;
                                }
                                $.each(data.users, function(_, u) {
                                    var dept = u.department ? ' — ' + u.department : '';
                                    $('<li></li>')
                                        .text(u.fullname + ' (' + u.email + ')' + dept)
                                        .data('name', u.fullname)
                                        .appendTo(dropdown);
                                });
                                setWidth();
                                dropdown.show();
                                activeIdx = -1;
                            },
                            fail: function() {
                                hideDrop();
                            }
                        }]);
                    }, 300);
                });

                input.on('keydown', function(e) {
                    var items = dropdown.find('li');
                    if (!dropdown.is(':visible') || !items.length) {
                        return;
                    }
                    if (e.key === 'ArrowDown') {
                        e.preventDefault();
                        activeIdx = Math.min(activeIdx + 1, items.length - 1);
                        items.removeClass('active').eq(activeIdx).addClass('active');
                    } else if (e.key === 'ArrowUp') {
                        e.preventDefault();
                        activeIdx = Math.max(activeIdx - 1, 0);
                        items.removeClass('active').eq(activeIdx).addClass('active');
                    } else if (e.key === 'Enter' && activeIdx >= 0) {
                        e.preventDefault();
                        selectItem($(items.get(activeIdx)));
                    } else if (e.key === 'Escape') {
                        hideDrop();
                    }
                });

                dropdown.on('mousedown', 'li', function(e) {
                    e.preventDefault();
                    selectItem($(this));
                });

                $(document).on('click.wlsuggest', function(e) {
                    if (!$(e.target).closest('#wl-search, .workload-suggest').length) {
                        hideDrop();
                    }
                });
            }

            // Submit-button state management.
            bindSubmitToggle('wl-mcimport-form', 'addusers[]');
            bindSubmitToggle('wl-members-form', 'removeusers[]');
        },

        // -----------------------------------------------------------------
        // Manage-courses page
        // -----------------------------------------------------------------

        /**
         * Enable/disable submit buttons based on checkbox selection.
         */
        initCourses: function() {
            bindSubmitToggle('wl-addcourses-form', 'addcourseids[]');
            bindSubmitToggle('wl-removecourses-form', 'removecourseids[]');
        },

        // -----------------------------------------------------------------
        // Activation settings page
        // -----------------------------------------------------------------

        /**
         * Show/hide the date-range fields when the "period" radio is selected.
         */
        initActivation: function() {
            document.querySelectorAll('input[name="activation_mode"]').forEach(function(radio) {
                radio.addEventListener('change', function() {
                    document.getElementById('wl-period-fields').style.display =
                        (this.value === 'period') ? '' : 'none';
                });
            });
        },
    };
});
