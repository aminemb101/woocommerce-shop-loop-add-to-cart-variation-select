
<?php
/**
 * Plugin Name: Loop Variable Product Select + AJAX Add to Cart
 * Description: Replaces variable product loop add-to-cart links with a variation <select> and side-by-side AJAX Add to Cart button.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_filter( 'woocommerce_loop_add_to_cart_link', 'loop_variable_product_select', 10, 3 );

function loop_variable_product_select( $html, $product, $args = [] ) {
    if ( ! $product instanceof WC_Product || ! $product->is_type( 'variable' ) ) {
        return $html;
    }

    if ( function_exists( 'wp_enqueue_script' ) ) {
        wp_enqueue_script( 'wc-add-to-cart' );
    }

    $variations = $product->get_available_variations();
    if ( empty( $variations ) ) {
        return $html;
    }

    $options_html = '';
    $first_vid    = 0;
    $first_attrs  = [];

    foreach ( $variations as $var_data ) {
        $variation_id = (int) ( $var_data['variation_id'] ?? 0 );
        $attributes   = is_array( $var_data['attributes'] ?? null ) ? $var_data['attributes'] : [];

        // Skip if variation ID is missing or if no attributes are defined
        if ( ! $variation_id || empty( $attributes ) ) {
            continue;
        }

        // Check if any attribute value is empty ("Any <Attribute>" or unconfigured)
        $has_empty_attribute = false;
        $parts               = [];

        foreach ( $attributes as $attr_key => $attr_val ) {
            $attr_val = trim( (string) $attr_val );
            if ( '' === $attr_val ) {
                $has_empty_attribute = true;
                break;
            }
            $parts[] = $attr_val;
        }

        // Skip invalid/incomplete variations
        if ( $has_empty_attribute || empty( $parts ) ) {
            continue;
        }

        $is_in_stock = ! empty( $var_data['is_in_stock'] );
        $label       = implode( ' / ', $parts );

        if ( ! empty( $var_data['price_html'] ) ) {
            $plain_price = wp_strip_all_tags( html_entity_decode( $var_data['price_html'], ENT_QUOTES, 'UTF-8' ) );
            if ( '' !== trim( $plain_price ) ) {
                $label .= ' — ' . trim( $plain_price );
            }
        }

        if ( ! $is_in_stock ) {
            $label .= ' (' . __( 'Out of stock', 'woocommerce' ) . ')';
        }

        if ( 0 === $first_vid && $is_in_stock ) {
            $first_vid   = $variation_id;
            $first_attrs = $attributes;
        }

        $options_html .= sprintf(
                '<option value="%d" data-attributes="%s" %s>%s</option>',
                $variation_id,
                esc_attr( wp_json_encode( $attributes ) ),
                disabled( ! $is_in_stock, true, false ),
                esc_html( $label )
        );
    }

    if ( '' === $options_html ) {
        return $html;
    }

    $select_id     = 'loop-var-select-' . $product->get_id() . '-' . wp_unique_id();
    $initial_attrs = esc_attr( wp_json_encode( $first_attrs ) );
    ob_start();
    ?>
    <div class="wc-loop-var-atc" data-product-id="<?php echo esc_attr( $product->get_id() ); ?>" style="display:flex;gap:8px;align-items:center;">
        <label for="<?php echo esc_attr( $select_id ); ?>" class="screen-reader-text">
            <?php esc_html_e( 'Choose a variation', 'woocommerce' ); ?>
        </label>
        <select id="<?php echo esc_attr( $select_id ); ?>" class="wc-loop-var-select">
            <?php echo $options_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </select>

        <button
                type="button"
                class="button wc-loop-var-atc-btn"
                data-product_id="<?php echo esc_attr( $product->get_id() ); ?>"
                data-variation_id="<?php echo esc_attr( $first_vid ); ?>"
                data-attributes="<?php echo $initial_attrs; ?>"
                <?php disabled( 0 === $first_vid ); ?>
        >
            <svg class="shopping-bag" width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                <path fill-rule="evenodd" clip-rule="evenodd" d="M16.5285 6C16.5098 5.9193 16.4904 5.83842 16.4701 5.75746C16.2061 4.70138 15.7904 3.55383 15.1125 2.65C14.4135 1.71802 13.3929 1 12 1C10.6071 1 9.58648 1.71802 8.88749 2.65C8.20962 3.55383 7.79387 4.70138 7.52985 5.75747C7.50961 5.83842 7.49016 5.9193 7.47145 6H5.8711C4.29171 6 2.98281 7.22455 2.87775 8.80044L2.14441 19.8004C2.02898 21.532 3.40238 23 5.13777 23H18.8622C20.5976 23 21.971 21.532 21.8556 19.8004L21.1222 8.80044C21.0172 7.22455 19.7083 6 18.1289 6H16.5285ZM8 11C8.57298 11 8.99806 10.5684 9.00001 9.99817C9.00016 9.97438 9.00044 9.9506 9.00084 9.92682C9.00172 9.87413 9.00351 9.79455 9.00718 9.69194C9.01451 9.48652 9.0293 9.18999 9.05905 8.83304C9.08015 8.57976 9.10858 8.29862 9.14674 8H14.8533C14.8914 8.29862 14.9198 8.57976 14.941 8.83305C14.9707 9.18999 14.9855 9.48652 14.9928 9.69194C14.9965 9.79455 14.9983 9.87413 14.9992 9.92682C14.9996 9.95134 14.9999 9.97587 15 10.0004C15 10.0004 15 11 16 11C17 11 17 9.99866 17 9.99866C16.9999 9.9636 16.9995 9.92854 16.9989 9.89349C16.9978 9.829 16.9957 9.7367 16.9915 9.62056C16.9833 9.38848 16.9668 9.06001 16.934 8.66695C16.917 8.46202 16.8953 8.23812 16.8679 8H18.1289C18.6554 8 19.0917 8.40818 19.1267 8.93348L19.86 19.9335C19.8985 20.5107 19.4407 21 18.8622 21H5.13777C4.55931 21 4.10151 20.5107 4.13998 19.9335L4.87332 8.93348C4.90834 8.40818 5.34464 8 5.8711 8H7.13208C7.10465 8.23812 7.08303 8.46202 7.06595 8.66696C7.0332 9.06001 7.01674 9.38848 7.00845 9.62056C7.0043 9.7367 7.00219 9.829 7.00112 9.89349C7.00054 9.92785 7.00011 9.96221 7 9.99658C6.99924 10.5672 7.42833 11 8 11ZM9.53352 6H14.4665C14.2353 5.15322 13.921 4.39466 13.5125 3.85C13.0865 3.28198 12.6071 3 12 3C11.3929 3 10.9135 3.28198 10.4875 3.85C10.079 4.39466 9.76472 5.15322 9.53352 6Z" fill="#FFFFFF"></path>
            </svg>
            <svg class="spinner" width="16" height="16" stroke="#fff" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><style>.spinner_V8m1{transform-origin:center;animation:spinner_zKoa 2s linear infinite}.spinner_V8m1 circle{stroke-linecap:round;animation:spinner_YpZS 1.5s ease-in-out infinite}@keyframes spinner_zKoa{100%{transform:rotate(360deg)}}@keyframes spinner_YpZS{0%{stroke-dasharray:0 150;stroke-dashoffset:0}47.5%{stroke-dasharray:42 150;stroke-dashoffset:-16}95%,100%{stroke-dasharray:42 150;stroke-dashoffset:-59}}</style><g class="spinner_V8m1"><circle cx="12" cy="12" r="9.5" fill="none" stroke-width="3"></circle></g></svg>
        </button>
        <span class="wc-loop-var-feedback" aria-live="polite" style="font-size:0.875em;"></span>
    </div>
    <?php
    return ob_get_clean();
}

add_action( 'wp_footer', 'loop_variable_product_select_js', 30 );
function loop_variable_product_select_js()
{
        if ( is_admin() || ! function_exists( 'is_woocommerce' ) ) {
            return;
        }
        ?>
        <script>
            jQuery(function($) {
                $(document.body).on('click', '.wc-loop-var-atc-btn', function(e) {
                    const btn     = $(this);
                    const wrapper = btn.closest('.wc-loop-var-atc');
                    const select  = wrapper.find('select');
                    btn.addClass('loading').prop('disabled', true);
                    const data = {
                        product_id: select.val(),
                        quantity: 1,
                    };
                    $.ajax({
                        type: 'POST',
                        url: wc_add_to_cart_params.wc_ajax_url.toString().replace( '%%endpoint%%', 'add_to_cart' ),
                        data: data,
                        success: function(response) {
                            btn.removeClass('loading').prop('disabled', false);
                            $(document.body).trigger('added_to_cart', [response.fragments, response.cart_hash, btn]);
                        },
                        error: function (error) {
                            btn.removeClass('loading').prop('disabled', false);
                            console.error(error);
                        }
                    });
                });
            });
        </script>
        <?php

}

