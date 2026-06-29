/**
 * BrainStation23
 *
 * @category    BrainStation23
 * @package     BrainStation23_Dubors
 * @copyright   Copyright (c) 2026 BrainStation23
 */

define([
    'uiComponent',
    'Magento_Customer/js/customer-data',
    'ko'
], function (Component, customerData, ko) {
    'use strict';

    return Component.extend({
        /** @inheritdoc */
        initialize: function () {
            this._super();
            this.duborsOffer = customerData.get('dubors_personalized_offer');
        },

        /**
         * Check if offer is available
         * @returns {Boolean}
         */
        hasOffer: function () {
            return this.duborsOffer() && this.duborsOffer().has_offer;
        },

        /**
         * Get coupon code
         * @returns {String}
         */
        getCouponCode: function () {
            return this.duborsOffer() ? this.duborsOffer().coupon_code : '';
        },

        /**
         * Get discount percentage
         * @returns {Number}
         */
        getDiscountPercent: function () {
            return this.duborsOffer() ? this.duborsOffer().discount_percent : 0;
        },

        /**
         * Get expiry message
         * @returns {String}
         */
        getExpiryMessage: function () {
            if (!this.duborsOffer() || !this.duborsOffer().expires_at) {
                return '';
            }
            return 'Expires on: ' + this.duborsOffer().expires_at;
        }
    });
});
