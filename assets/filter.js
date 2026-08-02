jQuery(document).ready(function($) {
    var $container = $('.oswald-filter-container').first();

    if (!$container.length) {
        return;
    }

    var $button = $('#filter-toggle-btn');
    var $panel = $('#oswald-filter-panel');
    var $form = $('#oswald-filter-form');
    var $slider = $('#oswald-price-slider');
    var $minPrice = $('#oswald-min-price');
    var $maxPrice = $('#oswald-max-price');
    var $minLabel = $('#oswald-price-min-label');
    var $maxLabel = $('#oswald-price-max-label');
    var $activeCount = $('#filter-active-count');
    var currencySymbol = (window.oswald_filter_params && oswald_filter_params.currency_symbol) ? oswald_filter_params.currency_symbol : '$';
    var priceDecimals = (window.oswald_filter_params && typeof oswald_filter_params.price_decimals !== 'undefined') ? parseInt(oswald_filter_params.price_decimals, 10) : 0;
    var defaultMin = parseFloat($container.data('price-min')) || 0;
    var defaultMax = parseFloat($container.data('price-max')) || 1000;

    function storeScrollPosition() {
        sessionStorage.setItem('filter_scroll_position', String(window.pageYOffset || 0));
    }

    function formatPrice(value) {
        var number = Number(value || 0);
        return currencySymbol + number.toFixed(priceDecimals);
    }

    function setPriceLabels(minValue, maxValue) {
        $minLabel.text(formatPrice(minValue));
        $maxLabel.text(formatPrice(maxValue));
        $minPrice.val(minValue);
        $maxPrice.val(maxValue);
    }

    function setPanelState(isOpen) {
        $panel.toggleClass('visible', isOpen);
        $button.attr('aria-expanded', isOpen ? 'true' : 'false');
    }

    function countCheckedCategories() {
        return $form.find('input[name^="wpf_filter_cat_"]:checked').length;
    }

    function countActiveFilters() {
        var count = 0;
        var searchValue = $('#oswald-search').val();
        var stockValue = $('#oswald-stock').val();
        var minValue = parseFloat($minPrice.val());
        var maxValue = parseFloat($maxPrice.val());
        var categoryCount = countCheckedCategories();

        if (searchValue && searchValue.trim() !== '') {
            count += 1;
        }

        if (stockValue && stockValue !== 'all') {
            count += 1;
        }

        if (!isNaN(minValue) && !isNaN(maxValue) && (minValue !== defaultMin || maxValue !== defaultMax)) {
            count += 1;
        }

        if (categoryCount > 0) {
            count += categoryCount;
        }

        $activeCount.text(count + ' active');
    }

    function closeOnOutsideClick(event) {
        if (!$panel.hasClass('visible')) {
            return;
        }

        if ($(event.target).closest('.oswald-filter-container').length === 0) {
            setPanelState(false);
        }
    }

    $button.on('click', function(event) {
        event.preventDefault();
        event.stopPropagation();
        setPanelState(!$panel.hasClass('visible'));
    });

    $('.close-filter').on('click', function() {
        setPanelState(false);
    });

    $(document).on('click', closeOnOutsideClick);

    $(document).on('keydown', function(event) {
        if (event.key === 'Escape') {
            setPanelState(false);
        }
    });

    $(document).on('click', '.toggle-sub', function(event) {
        event.preventDefault();
        event.stopPropagation();

        var $button = $(this);
        var $category = $button.closest('.oswald-category');
        var $subcategories = $category.find('> .subcategories');

        $subcategories.slideToggle(180);

        if ($button.attr('data-state') === 'closed') {
            $button.text('-').attr('data-state', 'open');
        } else {
            $button.text('+').attr('data-state', 'closed');
        }
    });

    $(document).on('click', '.cat-header .checkbox-container', function(event) {
        event.stopPropagation();
    });

    if ($slider.length && $.fn.slider) {
        var currentMin = parseFloat($minPrice.val());
        var currentMax = parseFloat($maxPrice.val());

        if (isNaN(currentMin)) {
            currentMin = defaultMin;
        }

        if (isNaN(currentMax)) {
            currentMax = defaultMax;
        }

        $slider.slider({
            range: true,
            min: defaultMin,
            max: defaultMax,
            values: [currentMin, currentMax],
            slide: function(event, ui) {
                setPriceLabels(ui.values[0], ui.values[1]);
                countActiveFilters();
            },
            change: function(event, ui) {
                setPriceLabels(ui.values[0], ui.values[1]);
                countActiveFilters();
            }
        });

        setPriceLabels(currentMin, currentMax);
    }

    $('#oswald-search, #oswald-stock').on('input change', function() {
        countActiveFilters();
    });

    $form.on('change', 'input[type="checkbox"]', function() {
        countActiveFilters();
    });

    $('#reset-filter-btn').on('click', function(event) {
        event.preventDefault();
        storeScrollPosition();
        window.location.href = $form.attr('action');
    });

    $form.on('submit', function() {
        storeScrollPosition();
        return true;
    });

    $(window).on('load', function() {
        var savedPosition = sessionStorage.getItem('filter_scroll_position');
        if (savedPosition !== null) {
            window.scrollTo(0, parseInt(savedPosition, 10));
            sessionStorage.removeItem('filter_scroll_position');
        }
    });

    countActiveFilters();
});
