<?php
defined('ABSPATH') || exit;

$price_bounds = function_exists('oswald_get_price_bounds') ? oswald_get_price_bounds() : array(
    'min' => 0,
    'max' => 1000,
);

$current_min = isset($_GET['wpf_min_price']) ? floatval(wp_unslash($_GET['wpf_min_price'])) : $price_bounds['min'];
$current_max = isset($_GET['wpf_max_price']) ? floatval(wp_unslash($_GET['wpf_max_price'])) : $price_bounds['max'];
$current_search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
$current_stock = isset($_GET['pr_stock']) ? sanitize_text_field(wp_unslash($_GET['pr_stock'])) : '';

// Setup categories
$categories = get_terms([
    'taxonomy' => 'product_cat',
    'hide_empty' => true,
    'parent' => 0,
]);

// FIXED FUNCTION: Properly render categories with checkboxes (not radio)
function render_category_tree($cats, $level = 0) {
    $level_num = $level + 1; // WooBeeWoo starts category levels at 1, not 0
    
    foreach ($cats as $cat) {
        // Get children categories
        $children = get_terms([
            'taxonomy' => 'product_cat',
            'hide_empty' => true,
            'parent' => $cat->term_id
        ]);
        
        // FIXED: Param name is based on category level, but value is term_id
        $param_name = "wpf_filter_cat_{$level_num}";
        $is_checked = isset($_GET[$param_name]) && 
            ((is_array($_GET[$param_name]) && in_array($cat->term_id, $_GET[$param_name])) || 
            $_GET[$param_name] == $cat->term_id);
            
        $has_children = !empty($children);
        
        // Check if children are expanded
        $is_expanded = $is_checked || check_if_children_selected($children, $level_num + 1);
        
        echo '<div class="oswald-category level-' . $level . '">';
        echo '<div class="cat-header">';
        
        // FIXED: Use actual checkboxes for multi-select
        echo '<label class="checkbox-container">' . esc_html($cat->name) . ' <span class="count">(' . $cat->count . ')</span>';
        echo '<input type="checkbox" name="' . $param_name . '" value="' . $cat->term_id . '" ' . ($is_checked ? 'checked' : '') . '>';
        echo '<span class="checkmark"></span>';
        echo '</label>';
        
        // Toggle button stays on right (not moved)
        if ($has_children) {
            echo '<span class="toggle-sub" data-state="' . ($is_expanded ? 'open' : 'closed') . '">' . 
                 ($is_expanded ? '-' : '+') . '</span>';
        }
        
        echo '</div>'; // .cat-header
        
        // Render children with auto-expand if parent is selected or child is selected
        if ($has_children) {
            echo '<div class="subcategories" style="' . ($is_expanded ? 'display:block;' : 'display:none;') . '">';
            render_category_tree($children, $level_num);
            echo '</div>';
        }
        
        echo '</div>'; // .oswald-category
    }
}

// Helper function to check if any child category is selected
function check_if_children_selected($categories, $level) {
    if (empty($categories)) {
        return false;
    }
    
    $param_name = "wpf_filter_cat_{$level}";
    
    foreach ($categories as $cat) {
        if (isset($_GET[$param_name]) && 
            ((is_array($_GET[$param_name]) && in_array($cat->term_id, $_GET[$param_name])) || 
            $_GET[$param_name] == $cat->term_id)) {
            return true;
        }
        
        $children = get_terms([
            'taxonomy' => 'product_cat',
            'hide_empty' => true,
            'parent' => $cat->term_id
        ]);
        
        if (check_if_children_selected($children, $level + 1)) {
            return true;
        }
    }
    
    return false;
}
?>

<div class="oswald-filter-container" data-price-min="<?php echo esc_attr($price_bounds['min']); ?>" data-price-max="<?php echo esc_attr($price_bounds['max']); ?>">
    <button id="filter-toggle-btn" type="button" aria-expanded="false" aria-controls="oswald-filter-panel">
        <span class="filter-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="4" y1="21" x2="4" y2="14"></line>
                <line x1="4" y1="10" x2="4" y2="3"></line>
                <line x1="12" y1="21" x2="12" y2="12"></line>
                <line x1="12" y1="8" x2="12" y2="3"></line>
                <line x1="20" y1="21" x2="20" y2="16"></line>
                <line x1="20" y1="12" x2="20" y2="3"></line>
                <line x1="1" y1="14" x2="7" y2="14"></line>
                <line x1="9" y1="8" x2="15" y2="8"></line>
                <line x1="17" y1="16" x2="23" y2="16"></line>
            </svg>
        </span>
        <span class="filter-button-text">Filter Products</span>
        <span class="filter-button-count" id="filter-active-count">0 active</span>
    </button>
    
    <div class="oswald-filter-panel" id="oswald-filter-panel">
        <form id="oswald-filter-form" method="get" action="<?php echo esc_url(get_permalink(wc_get_page_id('shop'))); ?>">
            <!-- Preserve existing query parameters that we don't control -->
            <?php
            foreach ($_GET as $key => $value) {
                if (strpos($key, 'wpf_filter_cat_') !== 0 && !in_array($key, array('s', 'pr_stock', 'wpf_min_price', 'wpf_max_price', 'wpf_count', 'wpf_fbv'), true)) {
                    echo '<input type="hidden" name="' . esc_attr($key) . '" value="' . esc_attr($value) . '">';
                }
            }
            ?>
            
            <!-- Always include these parameters -->
            <input type="hidden" name="wpf_count" value="60">
            <input type="hidden" name="wpf_fbv" value="1">
            
            <div class="oswald-filter-header">
                <div class="oswald-filter-header-text">
                    <h3>Filter Products</h3>
                    <p>Search, categories, stock, and price in one compact panel.</p>
                </div>
                <span class="close-filter">&times;</span>
            </div>
            
            <div class="oswald-filter-body">
                <div class="filter-grid">
                    <div class="filter-card filter-search">
                        <label class="filter-label" for="oswald-search">Search</label>
                        <div class="search-shell">
                            <span class="search-icon" aria-hidden="true">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="11" cy="11" r="8"></circle>
                                    <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                                </svg>
                            </span>
                            <input type="search" id="oswald-search" name="s" value="<?php echo esc_attr($current_search); ?>" placeholder="Search products...">
                        </div>
                    </div>

                    <div class="filter-card categories">
                        <div class="filter-card-head">
                            <h4>Categories</h4>
                            <span class="hint">Expand the ones you need</span>
                        </div>
                        <div class="oswald-categories">
                            <?php render_category_tree($categories); ?>
                        </div>
                    </div>

                    <div class="filter-card filter-stock">
                        <div class="filter-card-head">
                            <h4>Stock</h4>
                        </div>
                        <select id="oswald-stock" name="pr_stock">
                            <option value="" <?php selected($current_stock, ''); ?>>All stock</option>
                            <option value="instock" <?php selected($current_stock, 'instock'); ?>>In stock</option>
                            <option value="outofstock" <?php selected($current_stock, 'outofstock'); ?>>Out of stock</option>
                        </select>
                    </div>

                    <div class="filter-card filter-price">
                        <div class="filter-card-head">
                            <h4>Price</h4>
                            <span class="price-readout">
                                <span id="oswald-price-min-label"><?php echo wp_kses_post(wc_price($current_min)); ?></span>
                                <span class="price-divider">-</span>
                                <span id="oswald-price-max-label"><?php echo wp_kses_post(wc_price($current_max)); ?></span>
                            </span>
                        </div>
                        <div id="oswald-price-slider" class="oswald-price-slider"></div>
                        <input type="hidden" name="wpf_min_price" id="oswald-min-price" value="<?php echo esc_attr($current_min); ?>">
                        <input type="hidden" name="wpf_max_price" id="oswald-max-price" value="<?php echo esc_attr($current_max); ?>">
                    </div>
                </div>
            </div>
            
            <div class="oswald-filter-footer">
                <button type="button" class="reset-btn" id="reset-filter-btn">Reset</button>
                <button type="submit" class="apply-btn">Apply Filters</button>
            </div>
        </form>
    </div>
</div>