/**
 * BrainStation23 DUBORS Tracker
 * Dynamic User Based Offer Recommendation System
 * 
 * @copyright Copyright (c) 2026 BrainStation23
 */

(function(window) {
    'use strict';
    
    /**
     * DUBORS Tracker - Frontend event tracking library
     */
    var DuborsTracker = function(config) {
        config = config || {};
        
        this.trackingUrl = config.trackingUrl || '/dubors/track/event';
        this.enabled = config.enabled !== false;
        this.debug = config.debug || false;
        this.queue = [];
        this.processing = false;
        
        this._init();
    };
    
    /**
     * Initialize tracker
     */
    DuborsTracker.prototype._init = function() {
        if (!this.enabled) {
            return;
        }
        
        // Process queued events on page ready
        var self = this;
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function() {
                self._processQueue();
            });
        } else {
            this._processQueue();
        }
        
        // Bind to cart events if available
        this._bindCartEvents();
    };
    
    /**
     * Process queued events
     */
    DuborsTracker.prototype._processQueue = function() {
        if (this.processing || this.queue.length === 0) {
            return;
        }
        
        this.processing = true;
        var self = this;
        var event = this.queue.shift();
        
        this.send(event, function() {
            self.processing = false;
            if (self.queue.length > 0) {
                setTimeout(function() {
                    self._processQueue();
                }, 100);
            }
        });
    };
    
    /**
     * Track an event
     */
    DuborsTracker.prototype.track = function(eventType, data) {
        if (!this.enabled) {
            return;
        }
        
        data = data || {};
        
        var event = {
            event_type: eventType,
            product_id: data.product_id || null,
            category_id: data.category_id || null,
            cart_value: data.cart_value || 0
        };
        
        this.queue.push(event);
        
        if (!this.processing) {
            this._processQueue();
        }
        
        this._log('Event queued: ' + eventType, event);
    };
    
    /**
     * Send event to tracking endpoint
     */
    DuborsTracker.prototype.send = function(event, callback) {
        callback = callback || function() {};
        
        var self = this;
        var formData = new FormData();
        
        formData.append('event_type', event.event_type);
        if (event.product_id) {
            formData.append('product_id', event.product_id);
        }
        if (event.category_id) {
            formData.append('category_id', event.category_id);
        }
        if (event.cart_value) {
            formData.append('cart_value', event.cart_value);
        }
        
        fetch(this.trackingUrl, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(function(response) {
            return response.json();
        })
        .then(function(data) {
            self._log('Event sent: ' + event.event_type, data);
            callback(data);
        })
        .catch(function(error) {
            self._log('Error sending event: ' + event.event_type, error, true);
            callback(null, error);
        });
    };
    
    /**
     * Track product view
     */
    DuborsTracker.prototype.trackProductView = function(productId) {
        this.track('product_view', {
            product_id: productId
        });
    };
    
    /**
     * Track add to cart
     */
    DuborsTracker.prototype.trackAddToCart = function(productId, cartValue) {
        this.track('add_to_cart', {
            product_id: productId,
            cart_value: cartValue
        });
    };
    
    /**
     * Track purchase
     */
    DuborsTracker.prototype.trackPurchase = function(orderTotal) {
        this.track('purchase', {
            cart_value: orderTotal
        });
    };
    
    /**
     * Track wishlist add
     */
    DuborsTracker.prototype.trackWishlistAdd = function(productId) {
        this.track('wishlist_add', {
            product_id: productId
        });
    };
    
    /**
     * Track search
     */
    DuborsTracker.prototype.trackSearch = function(query) {
        this.track('search', {
            product_id: null
        });
    };
    
    /**
     * Bind to common cart events
     */
    DuborsTracker.prototype._bindCartEvents = function() {
        var self = this;
        
        // Listen for add to cart button clicks
        document.addEventListener('click', function(e) {
            if (e.target.classList && e.target.classList.contains('tocart')) {
                var productId = e.target.getAttribute('data-product-id');
                if (productId) {
                    setTimeout(function() {
                        self.trackAddToCart(productId);
                    }, 100);
                }
            }
        });
    };
    
    /**
     * Enable debug logging
     */
    DuborsTracker.prototype.enableDebug = function() {
        this.debug = true;
    };
    
    /**
     * Disable debug logging
     */
    DuborsTracker.prototype.disableDebug = function() {
        this.debug = false;
    };
    
    /**
     * Internal logging
     */
    DuborsTracker.prototype._log = function(message, data, isError) {
        if (!this.debug) {
            return;
        }
        
        if (window.console) {
            var logFn = isError ? console.error : console.log;
            logFn('[DUBORS]', message, data);
        }
    };
    
    // Export to global scope
    window.DuborsTracker = DuborsTracker;
    
    // Initialize default instance if configuration is provided
    if (window.duborsConfig) {
        window.duborsTracker = new DuborsTracker(window.duborsConfig);
    }
    
})(window);
