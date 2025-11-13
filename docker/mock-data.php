<?php
/**
 * Populate WooCommerce with mock data for the WooCommerce Product Tag Table demo.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit( "This script must be run within WordPress.\n" );
}

if ( ! class_exists( 'WooCommerce' ) ) {
    exit( "WooCommerce plugin is required.\n" );
}

require_once ABSPATH . 'wp-admin/includes/taxonomy.php';

wp_set_time_limit( 0 );

function wptt_ensure_term( $taxonomy, $term, $args = array() ) {
    $existing = term_exists( $term, $taxonomy );

    if ( 0 === $existing || null === $existing ) {
        $result = wp_insert_term( $term, $taxonomy, $args );

        if ( is_wp_error( $result ) ) {
            WP_CLI::warning( sprintf( 'Could not create %1$s term %2$s: %3$s', $taxonomy, $term, $result->get_error_message() ) );

            return 0;
        }

        return (int) $result['term_id'];
    }

    return (int) $existing['term_id'];
}

function wptt_create_or_update_product( array $data ) {
    $existing = wc_get_product_id_by_sku( $data['sku'] );

    if ( $existing ) {
        $product = wc_get_product( $existing );
    } else {
        $product = new WC_Product_Simple();
    }

    $product->set_name( $data['name'] );
    $product->set_sku( $data['sku'] );
    $product->set_regular_price( $data['price'] );
    $product->set_manage_stock( $data['manage_stock'] );

    if ( $data['manage_stock'] ) {
        $product->set_stock_quantity( $data['stock_quantity'] );
    }

    $product->set_backorders( $data['backorders'] );
    $product->set_catalog_visibility( 'visible' );
    $product->set_status( 'publish' );

    if ( isset( $data['description'] ) ) {
        $product->set_description( $data['description'] );
    }

    $product_id = $product->save();

    if ( ! $product_id ) {
        WP_CLI::warning( sprintf( 'Could not save product %s.', $data['name'] ) );

        return 0;
    }

    foreach ( $data['taxonomies'] as $taxonomy => $terms ) {
        wp_set_object_terms( $product_id, $terms, $taxonomy );
    }

    wp_set_object_terms( $product_id, $data['product_tags'], 'product_tag' );

    return $product_id;
}

// Ensure demo taxonomies exist.
foreach ( array( 'region', 'country', 'vendors' ) as $taxonomy ) {
    if ( ! taxonomy_exists( $taxonomy ) ) {
        WP_CLI::error( sprintf( 'Required taxonomy %s is missing.', $taxonomy ) );
    }
}

$regions = array(
    'europe' => 'Europa',
    'usa'    => 'Verenigde Staten',
    'africa' => 'Afrika',
);

$countries = array(
    'netherlands' => 'Nederland',
    'france'      => 'Frankrijk',
    'south-africa'=> 'Zuid-Afrika',
    'usa'         => 'Verenigde Staten',
);

$vendors = array(
    'vinifera-imports' => 'Vinifera Imports',
    'beer-brothers'    => 'Beer Brothers',
    'worldwide-cellar' => 'Worldwide Cellar',
);

foreach ( $regions as $slug => $label ) {
    wptt_ensure_term( 'region', $label, array( 'slug' => $slug ) );
}

foreach ( $countries as $slug => $label ) {
    wptt_ensure_term( 'country', $label, array( 'slug' => $slug ) );
}

foreach ( $vendors as $slug => $label ) {
    wptt_ensure_term( 'vendors', $label, array( 'slug' => $slug ) );
}

$products = array(
    array(
        'name'           => 'Classic Chardonnay 2021',
        'sku'            => 'WPTT-WINE-001',
        'price'          => '19.95',
        'manage_stock'   => true,
        'stock_quantity' => 24,
        'backorders'     => 'no',
        'product_tags'   => array( 'wijn' ),
        'taxonomies'     => array(
            'region'  => array( 'Europa' ),
            'country' => array( 'Frankrijk' ),
            'vendors' => array( 'Vinifera Imports' ),
        ),
        'description'    => 'Een frisse Chardonnay uit de Bourgogne.',
    ),
    array(
        'name'           => 'Craft IPA 6-pack',
        'sku'            => 'WPTT-BEER-001',
        'price'          => '14.50',
        'manage_stock'   => true,
        'stock_quantity' => 8,
        'backorders'     => 'notify',
        'product_tags'   => array( 'bier' ),
        'taxonomies'     => array(
            'region'  => array( 'Verenigde Staten' ),
            'country' => array( 'Verenigde Staten' ),
            'vendors' => array( 'Beer Brothers' ),
        ),
        'description'    => 'Een hoppige IPA van kleine brouwers uit Portland.',
    ),
    array(
        'name'           => 'Reserve Pinotage 2019',
        'sku'            => 'WPTT-WINE-002',
        'price'          => '24.00',
        'manage_stock'   => false,
        'stock_quantity' => 0,
        'backorders'     => 'yes',
        'product_tags'   => array( 'wijn' ),
        'taxonomies'     => array(
            'region'  => array( 'Afrika' ),
            'country' => array( 'Zuid-Afrika' ),
            'vendors' => array( 'Worldwide Cellar' ),
        ),
        'description'    => 'Een rijke pinotage met tonen van rijpe kersen.',
    ),
);

foreach ( $products as $product_data ) {
    $product_id = wptt_create_or_update_product( $product_data );

    if ( $product_id ) {
        WP_CLI::success( sprintf( 'Product "%s" klaar met ID %d.', $product_data['name'], $product_id ) );
    }
}

WP_CLI::success( 'Demo data aangemaakt.' );
