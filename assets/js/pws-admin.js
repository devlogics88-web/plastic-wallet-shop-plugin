/**
 * PWS Admin Scripts v10.0
 */
(function($) {
    'use strict';
    $(document).ready(function() {
        
        // ========================================
        // PRODUCT SIZES (NEW in v10)
        // ========================================
        
        // Add New Product Size
        $('#pws-add-product-size-form').on('submit', function(e) {
            e.preventDefault();
            var $msg = $('#pws-add-product-message');
            $msg.html('<span style="color: #0073aa;">Adding product...</span>');
            
            $.ajax({
                url: pws_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'pws_add_product_size',
                    nonce: pws_admin.nonce,
                    product_name: $('#pws-new-product-name').val(),
                    width: $('#pws-new-width').val(),
                    height: $('#pws-new-height').val(),
                    wc_product_id: $('#pws-new-wc-product').val()
                },
                success: function(r) {
                    if (r.success) {
                        $msg.html('<span style="color: #46b450;">✓ ' + r.data.message + '</span>');
                        setTimeout(function() { location.reload(); }, 1000);
                    } else {
                        $msg.html('<span style="color: #dc3232;">✗ ' + (r.data.message || 'Error') + '</span>');
                    }
                },
                error: function() {
                    $msg.html('<span style="color: #dc3232;">✗ Error adding product</span>');
                }
            });
        });
        
        // Save All Product Sizes
        $('#pws-product-sizes-form').on('submit', function(e) {
            e.preventDefault();
            var $msg = $('#pws-save-products-message');
            $msg.html('<span style="color: #0073aa;">💾 Saving all products...</span>');
            
            var formData = $(this).serializeArray();
            var data = {
                action: 'pws_save_product_sizes',
                nonce: pws_admin.nonce,
                products: {}
            };
            
            // Parse form data into products object
            formData.forEach(function(item) {
                var match = item.name.match(/products\[([^\]]+)\]\[([^\]]+)\]/);
                if (match) {
                    var slug = match[1];
                    var field = match[2];
                    if (!data.products[slug]) data.products[slug] = {};
                    data.products[slug][field] = item.value;
                }
            });
            
            $.ajax({
                url: pws_admin.ajax_url,
                type: 'POST',
                data: data,
                success: function(r) {
                    if (r.success) {
                        $msg.html('<span style="color: #46b450;">✓ ' + r.data.message + '</span>');
                        setTimeout(function() { $msg.text(''); }, 3000);
                    } else {
                        $msg.html('<span style="color: #dc3232;">✗ ' + (r.data.message || 'Error') + '</span>');
                    }
                },
                error: function() {
                    $msg.html('<span style="color: #dc3232;">✗ Error saving</span>');
                }
            });
        });
        
        // Delete Product Size
        $(document).on('click', '.pws-delete-product-size', function() {
            var $btn = $(this);
            var slug = $btn.data('slug');
            var $row = $btn.closest('tr');
            var productName = $row.find('input[name*="[name]"]').val();
            
            if (!confirm('Delete "' + productName + '"?')) return;
            
            $btn.text('Deleting...');
            
            $.ajax({
                url: pws_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'pws_delete_product_size',
                    nonce: pws_admin.nonce,
                    slug: slug
                },
                success: function(r) {
                    if (r.success) {
                        $row.fadeOut(300, function() { $(this).remove(); });
                    } else {
                        $btn.text('Error');
                        setTimeout(function() { $btn.text('🗑️ Delete'); }, 2000);
                    }
                },
                error: function() {
                    $btn.text('Error');
                    setTimeout(function() { $btn.text('🗑️ Delete'); }, 2000);
                }
            });
        });
        
        // Make product sizes table sortable
        if ($('#pws-product-sizes-tbody').length) {
            $('#pws-product-sizes-tbody').sortable({
                handle: '.pws-drag-handle',
                placeholder: 'ui-state-highlight',
                update: function(event, ui) {
                    // Order has changed - will save when form submitted
                }
            });
        }
        
        // ========================================
        // CATEGORY MAPPINGS (v11 - Drag & Drop Ordering)
        // ========================================
        
        // Update selected count
        function updateSelectedCount() {
            var count = $('#pws-selected-products .pws-product-item').length;
            $('#pws-selected-count').text(count);
            
            // Show/hide empty message
            if (count > 0) {
                $('#pws-selected-products .pws-empty-msg').hide();
            } else {
                $('#pws-selected-products .pws-empty-msg').show();
            }
            
            // Update hidden input with ordered product IDs
            var productIds = [];
            $('#pws-selected-products .pws-product-item').each(function() {
                productIds.push($(this).data('id'));
            });
            $('#pws-product-ids-input').val(productIds.join(','));
        }
        
        // Add product to selected list
        $(document).on('click', '.pws-add-product', function() {
            var $item = $(this).closest('.pws-product-item');
            var $clone = $item.clone();
            
            // Change button from add to remove
            $clone.find('.pws-add-product')
                .removeClass('pws-add-product')
                .addClass('pws-remove-product')
                .html('×')
                .attr('title', 'Remove');
            
            // Add order number
            var order = $('#pws-selected-products .pws-product-item').length + 1;
            $clone.prepend('<span class="pws-order-num" style="background: #0073aa; color: #fff; padding: 2px 8px; border-radius: 3px; margin-right: 8px; font-weight: bold;">' + order + '</span>');
            
            // Move to selected
            $('#pws-selected-products').append($clone);
            $item.hide();
            
            updateSelectedCount();
            updateOrderNumbers();
        });
        
        // Remove product from selected list
        $(document).on('click', '.pws-remove-product', function() {
            var $item = $(this).closest('.pws-product-item');
            var productId = $item.data('id');
            
            // Show back in available list
            $('#pws-available-products .pws-product-item[data-id="' + productId + '"]').show();
            
            // Remove from selected
            $item.remove();
            
            updateSelectedCount();
            updateOrderNumbers();
        });
        
        // Update order numbers after drag
        function updateOrderNumbers() {
            $('#pws-selected-products .pws-product-item').each(function(index) {
                var $orderNum = $(this).find('.pws-order-num');
                if ($orderNum.length) {
                    $orderNum.text(index + 1);
                }
            });
        }
        
        // Make selected products sortable (drag to reorder)
        if ($('#pws-selected-products').length) {
            $('#pws-selected-products').sortable({
                placeholder: 'ui-state-highlight',
                cursor: 'move',
                update: function(event, ui) {
                    updateOrderNumbers();
                    updateSelectedCount();
                }
            });
        }
        
        // Category Mapping Form (v11 - ordered version)
        $('#pws-category-mapping-form').on('submit', function(e) {
            e.preventDefault();
            var $form = $(this);
            var $status = $form.find('.pws-save-status').html('<span style="color: #0073aa;">💾 Saving...</span>');
            
            var productIds = [];
            $('#pws-selected-products .pws-product-item').each(function() {
                productIds.push($(this).data('id'));
            });
            
            var categorySlug = $('#pws-cat-slug').val();
            
            if (!categorySlug) {
                $status.html('<span style="color: #dc3232;">✗ Please select a category</span>');
                return;
            }
            
            if (productIds.length === 0) {
                $status.html('<span style="color: #dc3232;">✗ Please add at least one product</span>');
                return;
            }
            
            var data = {
                action: 'pws_save_category_mapping',
                nonce: pws_admin.nonce,
                category_slug: categorySlug,
                product_ids: productIds
            };
            
            $.ajax({
                url: pws_admin.ajax_url,
                type: 'POST',
                data: data,
                success: function(r) {
                    if (r.success) {
                        $status.html('<span style="color: #46b450;">✓ ' + r.data.message + '</span>');
                        setTimeout(function() { location.reload(); }, 1000);
                    } else {
                        $status.html('<span style="color: #dc3232;">✗ ' + (r.data.message || 'Error') + '</span>');
                    }
                },
                error: function() {
                    $status.html('<span style="color: #dc3232;">✗ Error saving</span>');
                }
            });
        });

        // Edit Category Mapping (v11 - load into drag/drop UI)
        $(document).on('click', '.pws-edit-mapping', function() {
            var slug = $(this).data('slug');
            var products = $(this).data('products').toString().split(',');
            
            // Set category dropdown
            $('#pws-cat-slug').val(slug);
            
            // Clear selected products
            $('#pws-selected-products .pws-product-item').each(function() {
                var productId = $(this).data('id');
                $('#pws-available-products .pws-product-item[data-id="' + productId + '"]').show();
            });
            $('#pws-selected-products .pws-product-item').remove();
            
            // Add products in order
            products.forEach(function(pid, index) {
                if (pid) {
                    var $item = $('#pws-available-products .pws-product-item[data-id="' + pid + '"]');
                    if ($item.length) {
                        var $clone = $item.clone();
                        $clone.find('.pws-add-product')
                            .removeClass('pws-add-product')
                            .addClass('pws-remove-product')
                            .html('×')
                            .attr('title', 'Remove');
                        $clone.prepend('<span class="pws-order-num" style="background: #0073aa; color: #fff; padding: 2px 8px; border-radius: 3px; margin-right: 8px; font-weight: bold;">' + (index + 1) + '</span>');
                        $('#pws-selected-products').append($clone);
                        $item.hide();
                    }
                }
            });
            
            updateSelectedCount();
            
            $('html, body').animate({ scrollTop: $('#pws-category-mapping-form').offset().top - 50 }, 300);
        });

        // Delete Category Mapping
        $(document).on('click', '.pws-delete-mapping', function() {
            if (!confirm('Delete this category mapping?')) return;
            
            var $btn = $(this);
            var slug = $btn.data('slug');
            var $row = $btn.closest('tr');
            
            $.ajax({
                url: pws_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'pws_delete_category_mapping',
                    nonce: pws_admin.nonce,
                    category_slug: slug
                },
                success: function(r) {
                    if (r.success) {
                        $row.fadeOut(300, function() { $(this).remove(); });
                    } else {
                        alert(r.data.message || 'Error deleting mapping');
                    }
                }
            });
        });

        // ========================================
        // FEATURED PRODUCTS
        // ========================================
        
        $('#pws-featured-form').on('submit', function(e) {
            e.preventDefault();
            var products = [];
            $('select[name="featured_products[]"]').each(function() { products.push($(this).val()); });
            var $status = $('.pws-save-status').text('Saving...').removeClass('error');
            $.ajax({
                url: pws_admin.ajax_url, type: 'POST',
                data: { action: 'pws_save_featured_products', nonce: pws_admin.nonce, products: products },
                success: function(r) { if (r.success) { $status.text('✓ Saved!'); setTimeout(function() { $status.text(''); }, 3000); } else { $status.text('Error').addClass('error'); } }
            });
        });

        // ========================================
        // PRICING TABLE (Legacy)
        // ========================================
        
        $('#pws-add-new-pricing').on('click', function() {
            $('#pws-form-title').text('Add New Pricing');
            $('#pws-pricing-form')[0].reset();
            $('#pricing_id').val('');
            $('#pws-pricing-form-container').slideDown();
        });

        $('#pws-cancel-form').on('click', function() { $('#pws-pricing-form-container').slideUp(); });

        $(document).on('click', '.pws-edit-pricing', function() {
            var id = $(this).data('id'), data = null;
            if (typeof pwsPricingData !== 'undefined') {
                for (var i = 0; i < pwsPricingData.length; i++) { if (pwsPricingData[i].id == id) { data = pwsPricingData[i]; break; } }
            }
            if (!data) { alert('Not found'); return; }
            $('#pricing_id').val(data.id);
            $('#product_name').val(data.product_name);
            $('#product_slug').val(data.product_slug);
            $('#size').val(data.size);
            $('#main_category').val(data.main_category);
            $('#sub_category').val(data.sub_category);
            $('#thumbcut_cost').val(data.thumbcut_cost);
            $('#holepunch_cost').val(data.holepunch_cost);
            [10,20,25,30,50,100,200,400,500,1000,2000].forEach(function(q) { $('#qty_' + q + '_unit').val(data['qty_' + q + '_unit']); });
            $('#pws-form-title').text('Edit: ' + data.product_name);
            $('#pws-pricing-form-container').slideDown();
            $('html, body').animate({ scrollTop: $('#pws-pricing-form-container').offset().top - 50 }, 300);
        });

        $('#pws-pricing-form').on('submit', function(e) {
            e.preventDefault();
            var $form = $(this), $status = $form.find('.pws-save-status');
            var data = { action: 'pws_save_pricing', nonce: pws_admin.nonce };
            $form.serializeArray().forEach(function(i) { data[i.name] = i.value; });
            $status.text('Saving...').removeClass('error');
            $.ajax({
                url: pws_admin.ajax_url, type: 'POST', data: data,
                success: function(r) { if (r.success) { $status.text('✓ Saved'); setTimeout(function() { location.reload(); }, 1000); } else { $status.text('Error').addClass('error'); } }
            });
        });

        $(document).on('click', '.pws-delete-pricing', function() {
            if (!confirm('Delete this pricing?')) return;
            var $btn = $(this), id = $btn.data('id'), $row = $btn.closest('tr');
            $.ajax({
                url: pws_admin.ajax_url, type: 'POST',
                data: { action: 'pws_delete_pricing', nonce: pws_admin.nonce, pricing_id: id },
                success: function(r) { if (r.success) $row.fadeOut(300, function() { $(this).remove(); }); }
            });
        });

        $('#product_name').on('blur', function() {
            var $slug = $('#product_slug');
            if ($slug.val() === '') { $slug.val($(this).val().toLowerCase().replace(/[^a-z0-9\s-]/g, '').replace(/\s+/g, '-')); }
        });

        // ========================================
        // IMPORT/EXPORT
        // ========================================
        
        $('#pws-import-form').on('submit', function(e) {
            e.preventDefault();
            var formData = new FormData(this);
            formData.append('action', 'pws_import_csv');
            formData.append('nonce', pws_admin.nonce);
            var $status = $('.pws-import-status').text('Importing...');
            $.ajax({
                url: pws_admin.ajax_url, type: 'POST', data: formData, processData: false, contentType: false,
                success: function(r) { if (r.success) { $status.text('✓ ' + r.data.message); setTimeout(function() { location.reload(); }, 2000); } else { $status.text('Error'); } }
            });
        });

        // ========================================
        // A SIZES CONFIGURATION
        // ========================================
        
        // Save Pricing Formula
        $('#pws-pricing-params-form').on('submit', function(e) {
            e.preventDefault();
            var $msg = $('#pws-pricing-params-message');
            $msg.html('<span style="color: #0073aa;">💾 Saving...</span>');
            
            $.ajax({
                url: pws_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'pws_save_a_sizes_config',
                    nonce: pws_admin.nonce,
                    multiplier: $('#pws-multiplier').val(),
                    base_cost: $('#pws-base-cost').val()
                },
                success: function(r) {
                    if (r.success) {
                        $msg.html('<span style="color: #46b450;">✓ ' + r.data.message + '</span>');
                        setTimeout(function() { location.reload(); }, 1000);
                    } else {
                        $msg.html('<span style="color: #dc3232;">✗ ' + (r.data.message || 'Error') + '</span>');
                    }
                },
                error: function() {
                    $msg.html('<span style="color: #dc3232;">✗ Error saving</span>');
                }
            });
        });
        
        // Reset Pricing to Defaults
        $('#pws-reset-pricing-defaults').on('click', function(e) {
            e.preventDefault();
            if (!confirm('Reset pricing formula to defaults?\n\nMultiplier: 0.00072\nBase Cost: £13.50\n\nThis will fix any incorrect pricing.')) {
                return;
            }
            
            var $msg = $('#pws-pricing-params-message');
            $msg.html('<span style="color: #0073aa;">🔄 Resetting to defaults...</span>');
            
            $.ajax({
                url: pws_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'pws_save_a_sizes_config',
                    nonce: pws_admin.nonce,
                    force_reset_defaults: 'yes'
                },
                success: function(r) {
                    if (r.success) {
                        $msg.html('<span style="color: #46b450;">✓ ' + r.data.message + ' Reloading...</span>');
                        setTimeout(function() { location.reload(); }, 1500);
                    } else {
                        $msg.html('<span style="color: #dc3232;">✗ ' + (r.data.message || 'Error') + '</span>');
                    }
                },
                error: function() {
                    $msg.html('<span style="color: #dc3232;">✗ Error resetting</span>');
                }
            });
        });
        
        // Add New A Size
        $('#pws-add-a-size-form').on('submit', function(e) {
            e.preventDefault();
            var $msg = $('#pws-add-size-message');
            $msg.html('<span style="color: #0073aa;">Adding...</span>');
            
            $.ajax({
                url: pws_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'pws_save_a_sizes_config',
                    nonce: pws_admin.nonce,
                    action_type: 'add_size',
                    size_name: $('#new-size-name').val(),
                    width: $('#new-size-width').val(),
                    height: $('#new-size-height').val()
                },
                success: function(r) {
                    if (r.success) {
                        $msg.html('<span style="color: #46b450;">✓ ' + r.data.message + '</span>');
                        setTimeout(function() { location.reload(); }, 1000);
                    } else {
                        $msg.html('<span style="color: #dc3232;">✗ ' + (r.data.message || 'Error') + '</span>');
                    }
                },
                error: function() {
                    $msg.html('<span style="color: #dc3232;">✗ Error adding size</span>');
                }
            });
        });
        
        // Save A Size (update dimensions)
        $(document).on('click', '.pws-save-size', function() {
            var $btn = $(this);
            var $row = $btn.closest('tr');
            var sizeName = $btn.data('size');
            var width = $row.find('.pws-size-width').val();
            var height = $row.find('.pws-size-height').val();
            
            $btn.text('Saving...');
            
            $.ajax({
                url: pws_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'pws_save_a_sizes_config',
                    nonce: pws_admin.nonce,
                    action_type: 'update_size',
                    size_name: sizeName,
                    width: width,
                    height: height
                },
                success: function(r) {
                    if (r.success) {
                        $btn.text('✓ Saved!');
                        $row.find('.pws-dimensions').text(width + ' x ' + height);
                        setTimeout(function() { $btn.text('💾 Save'); location.reload(); }, 1000);
                    } else {
                        $btn.text('Error');
                        setTimeout(function() { $btn.text('💾 Save'); }, 2000);
                    }
                },
                error: function() {
                    $btn.text('Error');
                    setTimeout(function() { $btn.text('💾 Save'); }, 2000);
                }
            });
        });
        
        // Delete A Size
        $(document).on('click', '.pws-delete-size', function() {
            var $btn = $(this);
            var sizeName = $btn.data('size');
            
            if (!confirm('Are you sure you want to delete size "' + sizeName + '"?')) {
                return;
            }
            
            $btn.text('Deleting...');
            
            $.ajax({
                url: pws_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'pws_save_a_sizes_config',
                    nonce: pws_admin.nonce,
                    action_type: 'delete_size',
                    size_name: sizeName
                },
                success: function(r) {
                    if (r.success) {
                        $btn.closest('tr').fadeOut(300, function() { $(this).remove(); });
                    } else {
                        $btn.text('Error');
                        setTimeout(function() { $btn.text('🗑️ Delete'); }, 2000);
                    }
                },
                error: function() {
                    $btn.text('Error');
                    setTimeout(function() { $btn.text('🗑️ Delete'); }, 2000);
                }
            });
        });

        // Reset A Sizes to Defaults
        $('#pws-reset-a-sizes').on('click', function() {
            if (confirm('Are you sure you want to reset all A Sizes pricing to default values?')) {
                location.reload();
            }
        });
    });
})(jQuery);
