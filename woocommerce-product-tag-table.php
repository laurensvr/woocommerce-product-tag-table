<?php
/**
 * Plugin Name: WooCommerce Product Tag Table
 * Description: Toont producten met een specifieke product tag in tabelvorm (Naam, Prijs, Regio, Voorraad).
 * Version: 1.0.0
 * Author: Laurens van Riel
 * Author URI: mailto:laurens@vanriel.eu
 * License: GPL2
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Shortcode: [product_tag_table tag="wijn" columns="name,price,region,stock"]
add_shortcode( 'product_tag_table', 'wptt_render_product_tag_table' );

/**
 * Render the product table for a given tag.
 *
 * @param array $atts Shortcode attributes.
 *
 * @return string
 */
function wptt_render_product_tag_table( $atts ) {
    if ( ! class_exists( 'WooCommerce' ) ) {
        return '<p>WooCommerce is niet actief.</p>';
    }

    $atts = shortcode_atts(
        array(
            'tag'                     => '',
            'columns'                 => 'name,price,region,stock',
            'stock_display'           => 'quantity', // quantity|status|both
            'no_stock_management_text'=> 'Voorraadbeheer niet gevolgd',
            'backorder_text_no'       => 'Geen backorders toegestaan',
            'backorder_text_notify'   => 'Backorders toegestaan (klant geïnformeerd)',
            'backorder_text_yes'      => 'Backorders toegestaan',
        ),
        $atts,
        'product_tag_table'
    );

    if ( empty( $atts['tag'] ) ) {
        return '<p>Geen product tag opgegeven.</p>';
    }

    $columns          = wptt_normalize_columns( $atts['columns'] );
    $column_definitions = wptt_get_column_definitions();

    if ( empty( $columns ) ) {
        $columns = array( 'name', 'price', 'region', 'stock' );
    }

    $args = array(
        'post_type'      => 'product',
        'posts_per_page' => -1,
        'tax_query'      => array(
            array(
                'taxonomy' => 'product_tag',
                'field'    => 'slug',
                'terms'    => $atts['tag'],
            ),
        ),
    );

    $loop = new WP_Query( $args );

    if ( ! $loop->have_posts() ) {
        return '<p>Geen producten gevonden voor tag: ' . esc_html( $atts['tag'] ) . '</p>';
    }

    ob_start();
    ?>
    <table class="woocommerce-product-tag-table" style="width:100%; border-collapse: collapse;">
        <thead>
            <tr style="border-bottom: 2px solid #ccc;">
                <?php foreach ( $columns as $column ) :
                    if ( isset( $column_definitions[ $column ] ) ) :
                        ?>
                        <th style="text-align:left; padding:8px;">
                            <?php echo esc_html( $column_definitions[ $column ]['label'] ); ?>
                        </th>
                        <?php
                    endif;
                endforeach; ?>
            </tr>
        </thead>
        <tbody>
        <?php
        while ( $loop->have_posts() ) :
            $loop->the_post();

            $product = wc_get_product( get_the_ID() );

            if ( ! $product ) {
                continue;
            }
            ?>
            <tr style="border-bottom: 1px solid #eee;">
                <?php foreach ( $columns as $column ) :
                    if ( ! isset( $column_definitions[ $column ] ) ) {
                        continue;
                    }

                    $cell_value = wptt_get_column_value( $column, $product, $atts );
                    ?>
                    <td style="padding:8px;">
                        <?php
                        if ( 'name' === $column ) {
                            ?>
                            <a href="<?php the_permalink(); ?>"><?php echo esc_html( $cell_value ); ?></a>
                            <?php
                        } elseif ( 'price' === $column ) {
                            echo wp_kses_post( $cell_value );
                        } else {
                            echo esc_html( $cell_value );
                        }
                        ?>
                    </td>
                    <?php
                endforeach; ?>
            </tr>
            <?php
        endwhile;
        ?>
        </tbody>
    </table>
    <?php

    wp_reset_postdata();

    return ob_get_clean();
}

/**
 * Normalize the requested columns attribute.
 *
 * @param string $columns_attribute Columns attribute.
 *
 * @return array
 */
function wptt_normalize_columns( $columns_attribute ) {
    if ( empty( $columns_attribute ) ) {
        return array();
    }

    $raw_columns = explode( ',', $columns_attribute );
    $columns     = array();

    foreach ( $raw_columns as $column ) {
        $column = sanitize_key( trim( $column ) );

        if ( ! empty( $column ) ) {
            $columns[] = $column;
        }
    }

    return array_unique( $columns );
}

/**
 * Retrieve the supported column definitions.
 *
 * @return array
 */
function wptt_get_column_definitions() {
    return array(
        'name'    => array(
            'label' => 'Naam',
        ),
        'price'   => array(
            'label' => 'Prijs',
        ),
        'region'  => array(
            'label' => 'Regio',
        ),
        'country' => array(
            'label' => 'Land',
        ),
        'vendors' => array(
            'label' => 'Leveranciers',
        ),
        'stock'   => array(
            'label' => 'Voorraad',
        ),
    );
}

/**
 * Get the display value for a specific column.
 *
 * @param string     $column  Column key.
 * @param WC_Product $product Product instance.
 * @param array      $atts    Shortcode attributes.
 *
 * @return string
 */
function wptt_get_column_value( $column, $product, $atts ) {
    switch ( $column ) {
        case 'price':
            return $product->get_price_html();

        case 'region':
            return wptt_get_taxonomy_terms_list( $product->get_id(), 'region' );

        case 'country':
            return wptt_get_taxonomy_terms_list( $product->get_id(), 'country' );

        case 'vendors':
            return wptt_get_taxonomy_terms_list( $product->get_id(), 'vendors' );

        case 'stock':
            return wptt_get_stock_display_value( $product, $atts );

        case 'name':
        default:
            return $product->get_name();
    }
}

/**
 * Retrieve taxonomy terms as comma separated list.
 *
 * @param int    $product_id Product ID.
 * @param string $taxonomy   Taxonomy slug.
 *
 * @return string
 */
function wptt_get_taxonomy_terms_list( $product_id, $taxonomy ) {
    $terms = get_the_terms( $product_id, $taxonomy );

    if ( empty( $terms ) || is_wp_error( $terms ) ) {
        return '–';
    }

    $names = wp_list_pluck( $terms, 'name' );

    return implode( ', ', $names );
}

/**
 * Format the stock information for display.
 *
 * @param WC_Product $product Product instance.
 * @param array      $atts    Shortcode attributes.
 *
 * @return string
 */
function wptt_get_stock_display_value( $product, $atts ) {
    if ( ! $product->managing_stock() ) {
        return $atts['no_stock_management_text'];
    }

    $parts         = array();
    $stock_status  = $product->get_stock_status();
    $status_lookup = array(
        'instock'     => 'Op voorraad',
        'outofstock'  => 'Niet op voorraad',
        'onbackorder' => 'Beschikbaar via nabestelling',
    );

    $stock_display = strtolower( $atts['stock_display'] );

    if ( in_array( $stock_display, array( 'quantity', 'both' ), true ) ) {
        $quantity = $product->get_stock_quantity();

        if ( null !== $quantity ) {
            $parts[] = sprintf( _n( '%d stuk op voorraad', '%d stuks op voorraad', $quantity, 'woocommerce-product-tag-table' ), $quantity );
        }
    }

    if ( in_array( $stock_display, array( 'status', 'both' ), true ) ) {
        $parts[] = $status_lookup[ $stock_status ] ?? ucfirst( $stock_status );
    }

    $backorder_status = $product->get_backorders();
    $backorder_lookup = array(
        'no'     => $atts['backorder_text_no'],
        'notify' => $atts['backorder_text_notify'],
        'yes'    => $atts['backorder_text_yes'],
    );

    if ( isset( $backorder_lookup[ $backorder_status ] ) ) {
        $parts[] = $backorder_lookup[ $backorder_status ];
    }

    $parts = array_filter( array_map( 'trim', $parts ) );

    if ( empty( $parts ) ) {
        return $status_lookup[ $stock_status ] ?? '';
    }

    return implode( ' | ', $parts );
}
