/**
 * Plastic Wallet Shop - Frontend Scripts
 * Optimized & Clean Version
 */
(function($) {
    'use strict';

    // Global state
    var PWS = {
        currentProductId: 0,
        currentPricingSlug: '',
        currentPrice: { total: 0, unit: 0 },
        customPrice: { total: 0, unit: 0 },
        aPrice: { total: 0, unit: 0 }
    };

    // Initialize
    $(document).ready(function() {
        // DOM data attributes are the source of truth - read them FIRST
        var $options = $('#pws-options');
        if ($options.length) {
            var domProductId = $options.data('product-id');
            var domPricingSlug = $options.data('pricing-slug');
            if (domProductId) PWS.currentProductId = domProductId;
            if (domPricingSlug) PWS.currentPricingSlug = domPricingSlug;
        }
        
        // Only use pws_data as fallback if DOM values not set
        if (typeof pws_data !== 'undefined') {
            if (!PWS.currentProductId) PWS.currentProductId = pws_data.current_product_id || 0;
            if (!PWS.currentPricingSlug) PWS.currentPricingSlug = pws_data.current_pricing_slug || '';
        }

        bindEvents();
        initializePricing();
        
        // Init basket badge with server-rendered cart count
        if (typeof window.pwsInjectBadge === 'function' && typeof pws_data !== 'undefined') {
            window.pwsInjectBadge(parseInt(pws_data.cart_count) || 0);
        }
    });

    function bindEvents() {
        // Product selector clicks
        $(document).on('click', '.pws-selector-item, .pws-product-card, .pws-single-checkbox', handleProductClick);
        
        // Product options changes
        $(document).on('change', '#pws-size, #pws-thumbcut, #pws-holepunch, #pws-openside, #pws-quantity', calculatePrice);
        $(document).on('click', '#pws-bulk-suggestions a', function(e) {
            e.preventDefault();
            $('#pws-quantity').val($(this).data('qty')).trigger('change');
        });
        $(document).on('click', '#pws-add-to-cart', addToCart);

        // Custom sizes events (select dropdowns)
        $(document).on('change', '#pws-custom-width, #pws-custom-height, #pws-custom-thumbcut, #pws-custom-holepunch, #pws-custom-openside, #pws-custom-quantity', calculateCustomPrice);
        $(document).on('click', '#pws-custom-bulk-suggestions a', function(e) {
            e.preventDefault();
            $('#pws-custom-quantity').val($(this).data('qty')).trigger('change');
        });
        $(document).on('click', '#pws-custom-add-to-cart', addCustomToCart);
        
        // A Sizes events
        $(document).on('change', '#pws-a-size, #pws-a-thumbcut, #pws-a-holepunch, #pws-a-openside, #pws-a-quantity', calculateAPrice);
        $(document).on('click', '#pws-a-bulk-suggestions a', function(e) {
            e.preventDefault();
            $('#pws-a-quantity').val($(this).data('qty')).trigger('change');
        });
        $(document).on('click', '#pws-a-add-to-cart', addAToCart);

        // Cart page events
        $(document).on('click', '.pws-remove-item', removeCartItem);
        $(document).on('change', '.pws-qty-input', updateCartQty);
        $(document).on('click', '.pws-empty-cart-link', emptyCart);
        $(document).on('click', '.pws-update-cart-link', updateCart);
    }

    function initializePricing() {
        // Regular product options - ALWAYS read from DOM data attributes (source of truth)
        if ($('#pws-options').length) {
            var $options = $('#pws-options');
            // Force read from DOM - these are the correct values for this specific form
            var domSlug = $options.data('pricing-slug');
            var domProductId = $options.data('product-id');
            
            if (domSlug) {
                PWS.currentPricingSlug = domSlug;
            }
            if (domProductId) {
                PWS.currentProductId = domProductId;
            }
            
            // Trigger price calculation
            calculatePrice();
        }

        // Custom sizes - auto-calculate on load
        if ($('#pws-custom-sizes').length) {
            calculateCustomPrice();
        }
        
        // A Sizes - auto-calculate on load
        if ($('#pws-a-sizes').length) {
            calculateAPrice();
        }
    }

    // ========================================
    // PRODUCT SELECTION
    // ========================================
    function handleProductClick(e) {
        e.preventDefault();
        var $item = $(this);
        var productId = $item.data('product-id');
        if (!productId) return;

        // Update active states
        $('.pws-selector-item, .pws-product-card, .pws-single-checkbox').removeClass('active');
        $('.pws-checkbox').removeClass('checked');
        
        // Mark all matching items as active
        $('[data-product-id="' + productId + '"]').addClass('active').find('.pws-checkbox').addClass('checked');

        loadProduct(productId);
    }

    function loadProduct(productId) {
        $.ajax({
            url: pws_data.ajax_url,
            type: 'POST',
            data: { action: 'pws_get_product_data', nonce: pws_data.nonce, product_id: productId },
            beforeSend: function() { $('#pws-options').css('opacity', '0.5'); },
            success: function(response) {
                if (!response.success) return;
                
                var data = response.data;
                PWS.currentProductId = data.id;
                PWS.currentPricingSlug = data.pricing_slug;

                // Update title
                $('#pws-product-name').text(data.name);
                $('#pws-options').attr('data-product-id', data.id).attr('data-pricing-slug', data.pricing_slug);

                // Update sizes dropdown
                var $sizeSelect = $('#pws-size').empty();
                if (data.sizes && data.sizes.length) {
                    data.sizes.forEach(function(size) {
                        $sizeSelect.append('<option value="' + size + '">' + size + '</option>');
                    });
                }

                // Update product image - target WooCommerce/Elementor product image widget
                if (data.image) {
                    updateProductImage(data.image);
                }

                calculatePrice();

                // Update URL
                if (data.permalink && window.history.pushState) {
                    window.history.pushState({}, '', data.permalink);
                }
            },
            complete: function() { $('#pws-options').css('opacity', '1'); }
        });
    }

    // ========================================
    // UPDATE PRODUCT IMAGE (WooCommerce/Elementor)
    // ========================================
    function updateProductImage(imageUrl) {
        // Target WooCommerce Product Image widget from Elementor
        // These are the actual selectors Elementor uses for product images
        
        // 1. Elementor WooCommerce Product Images widget
        var $elementorProductImg = $('.elementor-widget-woocommerce-product-images img');
        if ($elementorProductImg.length) {
            $elementorProductImg.each(function() {
                $(this).attr('src', imageUrl).attr('srcset', '').removeAttr('data-src');
            });
        }
        
        // 2. WooCommerce gallery main image
        var $wcGalleryImg = $('.woocommerce-product-gallery__image img, .flex-active-slide img');
        if ($wcGalleryImg.length) {
            $wcGalleryImg.each(function() {
                $(this).attr('src', imageUrl).attr('srcset', '').removeAttr('data-src');
            });
        }
        
        // 3. Standard WooCommerce product image
        var $wcProductImg = $('.woocommerce-product-gallery .wp-post-image, .product-image img, .attachment-woocommerce_single');
        if ($wcProductImg.length) {
            $wcProductImg.each(function() {
                $(this).attr('src', imageUrl).attr('srcset', '').removeAttr('data-src');
            });
        }
        
        // 4. Our shortcode image - BOTH possible IDs
        var $pwsMainImg = $('#pws-main-image, #pws-main-product-image');
        if ($pwsMainImg.length) {
            $pwsMainImg.attr('src', imageUrl);
        }
        
        // 5. Any wp-post-image on the page (common class for featured images)
        var $wpPostImg = $('.wp-post-image').first();
        if ($wpPostImg.length) {
            $wpPostImg.attr('src', imageUrl).attr('srcset', '').removeAttr('data-src');
        }
        
        // 6. Category page main image class
        var $categoryMainImg = $('.pws-main-image');
        if ($categoryMainImg.length) {
            $categoryMainImg.attr('src', imageUrl);
        }
        
        // Log for debugging
        console.log('PWS: Updated product image to', imageUrl);
    }

    // ========================================
    // REGULAR PRODUCT PRICING
    // ========================================
    function calculatePrice() {
        // ALWAYS read from DOM first (source of truth), then fallback to PWS state
        var $options = $('#pws-options');
        var pricingSlug = $options.data('pricing-slug') || PWS.currentPricingSlug;
        var productId = $options.data('product-id') || PWS.currentProductId;
        var size = $('#pws-size').val();
        var quantity = $('#pws-quantity').val();
        if (!pricingSlug || !size) return;

        $.ajax({
            url: pws_data.ajax_url,
            type: 'POST',
            data: {
                action: 'pws_calculate_price',
                nonce: pws_data.nonce,
                pricing_slug: pricingSlug,
                product_id: productId,
                size: size,
                quantity: quantity,
                thumbcut: $('#pws-thumbcut').val(),
                holepunch: $('#pws-holepunch').val()
            },
            success: function(response) {
                if (!response.success) return;
                var data = response.data;
                $('#pws-total-price').text('£' + data.total);
                $('#pws-unit-price').text('(£' + data.unit_price + ' per unit)');
                PWS.currentPrice = { total: data.raw_total, unit: data.raw_unit };
                updateBulkSuggestions(data.bulk_suggestions, '#pws-bulk-suggestions');
            }
        });
    }

    function addToCart() {
        var $btn = $(this);
        if ($btn.hasClass('loading') || PWS.currentPrice.total <= 0) return;

        $btn.addClass('loading').text('Adding...');
        
        // Always read product ID from DOM first
        var $options = $('#pws-options');
        var productId = $options.data('product-id') || PWS.currentProductId;

        $.ajax({
            url: pws_data.ajax_url,
            type: 'POST',
            data: {
                action: 'pws_add_to_cart',
                nonce: pws_data.nonce,
                product_id: productId,
                product_name: $('#pws-product-name').text() || $options.data('product-name') || '',
                size: $('#pws-size').val(),
                quantity: $('#pws-quantity').val(),
                thumbcut: $('#pws-thumbcut').val(),
                holepunch: $('#pws-holepunch').val(),
                openside: $('#pws-openside').val(),
                total_price: PWS.currentPrice.total,
                unit_price: PWS.currentPrice.unit
            },
            success: function(response) {
                if (response.success) {
                    showToast('Added to basket! <a href="' + pws_data.cart_url + '">View Basket</a>', 'success');
                    updateCartCount(response.data.cart_count);
                    $(document.body).trigger('wc_fragment_refresh');
                    $btn.removeClass('loading').addClass('pws-btn-added').text('✓ Added!');
                    setTimeout(function() { $btn.removeClass('pws-btn-added').text('Add to basket'); }, 2500);
                } else {
                    showToast(response.data.message || 'Error', 'error');
                    $btn.removeClass('loading').text('Add to basket');
                }
            },
            error: function() { showToast('Error adding to cart', 'error'); $btn.removeClass('loading').text('Add to basket'); },
            complete: function() { /* handled in success/error */ }
        });
    }

    // ========================================
    // CUSTOM SIZES (Custom Orders)
    // ========================================
    function calculateCustomPrice() {
        var width = parseInt($('#pws-custom-width').val()) || 50;
        var height = parseInt($('#pws-custom-height').val()) || 50;

        $.ajax({
            url: pws_data.ajax_url,
            type: 'POST',
            data: {
                action: 'pws_calculate_custom_price',
                nonce: pws_data.nonce,
                width: width,
                height: height,
                quantity: $('#pws-custom-quantity').val(),
                thumbcut: $('#pws-custom-thumbcut').val(),
                holepunch: $('#pws-custom-holepunch').val(),
                size_type: 'Custom'
            },
            success: function(response) {
                if (!response.success) return;
                var data = response.data;
                $('#pws-custom-total-price').text('£' + data.total);
                $('#pws-custom-unit-price').text('(£' + data.unit_price + ' per unit)');
                PWS.customPrice = { total: data.raw_total, unit: data.raw_unit };
                updateBulkSuggestions(data.bulk_suggestions, '#pws-custom-bulk-suggestions');
            }
        });
    }

    function addCustomToCart() {
        var $btn = $(this);
        var width = parseInt($('#pws-custom-width').val()) || 50;
        var height = parseInt($('#pws-custom-height').val()) || 50;
        
        if ($btn.hasClass('loading') || PWS.customPrice.total <= 0) {
            if (PWS.customPrice.total <= 0) showToast('Please wait for price calculation', 'error');
            return;
        }

        $btn.addClass('loading').text('Adding...');

        $.ajax({
            url: pws_data.ajax_url,
            type: 'POST',
            data: {
                action: 'pws_add_custom_to_cart',
                nonce: pws_data.nonce,
                product_id: $('#pws-custom-sizes').data('product-id'),
                product_name: $('#pws-custom-sizes .pws-product-title').text(),
                width: width,
                height: height,
                size_type: 'Custom',
                quantity: $('#pws-custom-quantity').val(),
                thumbcut: $('#pws-custom-thumbcut').val(),
                holepunch: $('#pws-custom-holepunch').val(),
                openside: $('#pws-custom-openside').val(),
                total_price: PWS.customPrice.total,
                unit_price: PWS.customPrice.unit
            },
            success: function(response) {
                if (response.success) {
                    showToast('Added to basket! <a href="' + pws_data.cart_url + '">View Basket</a>', 'success');
                    updateCartCount(response.data.cart_count);
                    $(document.body).trigger('wc_fragment_refresh');
                    $btn.removeClass('loading').addClass('pws-btn-added').text('✓ Added!');
                    setTimeout(function() { $btn.removeClass('pws-btn-added').text('Add to basket'); }, 2500);
                } else {
                    showToast(response.data.message || 'Error', 'error');
                    $btn.removeClass('loading').text('Add to basket');
                }
            },
            error: function() { showToast('Error adding to cart', 'error'); $btn.removeClass('loading').text('Add to basket'); },
            complete: function() { /* handled in success/error */ }
        });
    }
    
    // ========================================
    // A SIZES
    // ========================================
    function calculateAPrice() {
        var size = $('#pws-a-size').val();
        
        if (!size) return;

        $.ajax({
            url: pws_data.ajax_url,
            type: 'POST',
            data: {
                action: 'pws_calculate_a_size_price',
                nonce: pws_data.nonce,
                size: size,
                quantity: $('#pws-a-quantity').val(),
                thumbcut: $('#pws-a-thumbcut').val(),
                holepunch: $('#pws-a-holepunch').val()
            },
            success: function(response) {
                if (!response.success) return;
                var data = response.data;
                $('#pws-a-total-price').text('£' + data.total);
                $('#pws-a-unit-price').text('(£' + data.unit_price + ' per unit)');
                PWS.aPrice = { total: data.raw_total, unit: data.raw_unit };
                updateBulkSuggestions(data.bulk_suggestions, '#pws-a-bulk-suggestions');
            }
        });
    }

    /**
     * Add A Size to Cart
     */
    function addAToCart() {
        var $btn = $(this);
        var size = $('#pws-a-size').val();
        
        if (!size) {
            showToast('Please select a size', 'error');
            return;
        }
        
        if ($btn.hasClass('loading') || !PWS.aPrice || PWS.aPrice.total <= 0) {
            if (!PWS.aPrice || PWS.aPrice.total <= 0) showToast('Please wait for price calculation', 'error');
            return;
        }

        $btn.addClass('loading').text('Adding...');

        $.ajax({
            url: pws_data.ajax_url,
            type: 'POST',
            data: {
                action: 'pws_add_a_size_to_cart',
                nonce: pws_data.nonce,
                product_id: $('#pws-a-sizes').data('product-id'),
                product_name: $('#pws-a-sizes .pws-product-title').text(),
                size: size,
                quantity: $('#pws-a-quantity').val(),
                thumbcut: $('#pws-a-thumbcut').val(),
                holepunch: $('#pws-a-holepunch').val(),
                openside: $('#pws-a-openside').val(),
                total_price: PWS.aPrice.total,
                unit_price: PWS.aPrice.unit
            },
            success: function(response) {
                if (response.success) {
                    showToast('Added to basket! <a href="' + pws_data.cart_url + '">View Basket</a>', 'success');
                    updateCartCount(response.data.cart_count);
                    $(document.body).trigger('wc_fragment_refresh');
                    $btn.removeClass('loading').addClass('pws-btn-added').text('✓ Added!');
                    setTimeout(function() { $btn.removeClass('pws-btn-added').text('Add to basket'); }, 2500);
                } else {
                    showToast(response.data.message || 'Error', 'error');
                    $btn.removeClass('loading').text('Add to basket');
                }
            },
            error: function() { showToast('Error adding to cart', 'error'); $btn.removeClass('loading').text('Add to basket'); },
            complete: function() { /* handled in success/error */ }
        });
    }

    // ========================================
    // CART PAGE
    // ========================================
    function removeCartItem(e) {
        e.preventDefault();
        var $row = $(this).closest('tr');
        var cartKey = $row.data('cart-key');

        $.ajax({
            url: pws_data.ajax_url,
            type: 'POST',
            data: { action: 'pws_remove_cart_item', nonce: pws_data.nonce, cart_key: cartKey },
            success: function(response) {
                if (response.success) {
                    $row.fadeOut(300, function() {
                        $(this).remove();
                        updateCartTotals(response.data);
                        if ($('.pws-cart-table tbody tr').length === 0) {
                            location.reload();
                        }
                    });
                }
            }
        });
    }

    function updateCartQty() {
        var $input = $(this);
        var $row = $input.closest('tr');
        var cartKey = $row.data('cart-key');
        var newQty = parseInt($input.val()) || 1;
        if (newQty < 1) { newQty = 1; $input.val(1); }

        $row.css('opacity', '0.5');
        $.ajax({
            url: pws_data.ajax_url,
            type: 'POST',
            data: { action: 'pws_update_cart_qty', nonce: pws_data.nonce, cart_key: cartKey, quantity: newQty },
            success: function(response) {
                if (response.success) {
                    $row.find('.pws-item-total').text('£' + response.data.item_total);
                    updateCartTotals(response.data);
                }
            },
            complete: function() { $row.css('opacity', '1'); }
        });
    }

    function emptyCart(e) {
        e.preventDefault();
        if (!confirm('Are you sure you want to empty your basket?')) return;

        $.ajax({
            url: pws_data.ajax_url,
            type: 'POST',
            data: { action: 'pws_empty_cart', nonce: pws_data.nonce },
            success: function(response) {
                if (response.success) location.reload();
            }
        });
    }

    function updateCart(e) {
        e.preventDefault();
        $.ajax({
            url: pws_data.ajax_url,
            type: 'POST',
            data: { action: 'pws_update_cart', nonce: pws_data.nonce },
            success: function(response) {
                if (response.success) location.reload();
            }
        });
    }

    function updateCartTotals(data) {
        if (data.total) $('#pws-cart-total').text('£' + data.total);
        if (data.per_unit) {
            $('#pws-cart-subtotal').text('(£' + data.per_unit + ' per unit)');
        } else if (data.subtotal) {
            $('#pws-cart-subtotal').text('£' + data.subtotal);
        }
        if (data.cart_count !== undefined) updateCartCount(data.cart_count);
    }

    // ========================================
    // UTILITIES
    // ========================================
    function updateBulkSuggestions(suggestions, selector) {
        var $container = $(selector).empty();
        if (!suggestions || !suggestions.length) return;
        
        suggestions.forEach(function(s) {
            $container.append('<a href="#" data-qty="' + s.qty + '">Buy ' + s.qty + ' only £' + s.total + ' (£' + s.unit + ' each)</a>');
        });
    }

    function updateCartCount(count) {
        // Update WC native count spans (fallback)
        $('.cart-contents-count').text(count);
        // Update badge via global injector (icon links only)
        if (typeof window.pwsInjectBadge === 'function') {
            window.pwsInjectBadge(parseInt(count) || 0);
        }
        // Update hidden WC fragment span so fragment refresh stays in sync
        $('span.pws-wc-cart-count').text(count);
    }

    function showToast(message, type) {
        var $toast = $('<div class="pws-toast pws-toast-' + (type || 'info') + '">' + message + '</div>');
        $('body').append($toast);
        setTimeout(function() { $toast.addClass('show'); }, 10);
        setTimeout(function() { $toast.removeClass('show'); setTimeout(function() { $toast.remove(); }, 300); }, 4000);
    }

})(jQuery);
