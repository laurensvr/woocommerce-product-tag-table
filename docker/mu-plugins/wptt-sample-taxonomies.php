<?php
/**
 * Plugin Name: WPTT Sample Taxonomies
 * Description: Registers helper taxonomies used by the WooCommerce Product Tag Table demo environment.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'init', function () {
    $taxonomies = array(
        'region'  => __( 'Regio', 'woocommerce-product-tag-table' ),
        'country' => __( 'Land', 'woocommerce-product-tag-table' ),
        'vendors' => __( 'Leveranciers', 'woocommerce-product-tag-table' ),
    );

    foreach ( $taxonomies as $slug => $label ) {
        if ( taxonomy_exists( $slug ) ) {
            continue;
        }

        register_taxonomy(
            $slug,
            'product',
            array(
                'labels'            => array(
                    'name'          => $label,
                    'singular_name' => $label,
                ),
                'public'            => false,
                'show_ui'           => true,
                'show_admin_column' => true,
                'show_in_rest'      => true,
                'hierarchical'      => false,
                'rewrite'           => false,
            )
        );
    }
} );
