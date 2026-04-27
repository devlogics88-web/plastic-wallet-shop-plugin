<?php
/**
 * Custom Category Page Template
 * Replaces WooCommerce shop grid for mapped categories
 */

if (!defined('ABSPATH')) exit;

get_header('shop');

// Get current category
$category = get_queried_object();
$category_slug = $category ? $category->slug : '';
$category_name = $category ? $category->name : '';

// Get PWS instance
$pws = PWS_Pricing_System::get_instance();

?>

<div id="primary" class="content-area">
    <main id="main" class="site-main">
        <div class="pws-category-template">
            <?php echo $pws->render_category_page(array('category' => $category_slug)); ?>
        </div>
    </main>
</div>

<?php

get_footer('shop');
