/**
 * @file
 * Attaches the behaviors for the Field UI module.
 */

(function ($, Drupal) {

    'use strict';

    /**
     * Attaches the checkoutPaneOverview behavior.
     *
     * @type {Drupal~behavior}
     *
     * @prop {Drupal~behaviorAttach} attach
     *   Attaches the checkoutPaneOverview behavior.
     *
     * @see Drupal.checkoutPaneOverview.attach
     */
    Drupal.behaviors.checkoutPaneOverview = {
        attach: function (context, settings) {
            $(context).find('table#checkout-pane-overview').once('checkout-pane-overview').each(function () {
                Drupal.checkoutPaneOverview.attach(this);
            });
        }
    };

    /**
     * Namespace for the checkout pane overview.
     *
     * @namespace
     */
    Drupal.checkoutPaneOverview = {

        /**
         * Attaches the checkoutPaneOverview behavior.
         *
         * @param {HTMLTableElement} table
         *   The table element for the overview.
         */
        attach: function (table) {
            var tableDrag = Drupal.tableDrag[table.id];

            // Add custom tabledrag callbacks.
            tableDrag.row.prototype.onSwap = this.onSwap;
        },

        /**
         * Refreshes placeholder rows in empty regions while a row is being dragged.
         *
         * Copied from block.js.
         *
         * @param {HTMLElement} draggedRow
         *   The tableDrag rowObject for the row being dragged.
         */
        onSwap: function (draggedRow) {
            var rowObject = this;
            $(rowObject.table).find('tr.region-message').each(function () {
                var $this = $(this);
                // If the dragged row is in this region, but above the message row, swap
                // it down one space.
                if ($this.prev('tr').get(0) === rowObject.group[rowObject.group.length - 1]) {
                    // Prevent a recursion problem when using the keyboard to move rows
                    // up.
                    if ((rowObject.method !== 'keyboard' || rowObject.direction === 'down')) {
                        rowObject.swap('after', this);
                    }
                }
                // This region has become empty.
                if ($this.next('tr').is(':not(.draggable)') || $this.next('tr').length === 0) {
                    $this.removeClass('region-populated').addClass('region-empty');
                }
                // This region has become populated.
                else if ($this.is('.region-empty')) {
                    $this.removeClass('region-empty').addClass('region-populated');
                }
            });
        }
    };

})(jQuery, Drupal);
