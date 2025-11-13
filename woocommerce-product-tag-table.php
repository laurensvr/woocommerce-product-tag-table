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

// Shortcode: [product_tag_table tag="wijn" columns="name,price,region,country,stock"]
add_shortcode( 'product_tag_table', 'wptt_render_product_tag_table' );

add_action( 'admin_init', 'wptt_register_settings' );
add_action( 'admin_menu', 'wptt_add_settings_page' );
add_action( 'wp_enqueue_scripts', 'wptt_enqueue_assets' );

/**
 * Return the plugin's default settings.
 *
 * @return array
 */
function wptt_get_default_settings() {
    return array(
        'columns'    => array( 'name', 'price', 'region', 'country', 'stock' ),
        'taxonomies' => array(
            array(
                'slug'  => 'region',
                'label' => 'Regio',
            ),
            array(
                'slug'  => 'country',
                'label' => 'Land',
            ),
            array(
                'slug'  => 'vendors',
                'label' => 'Leveranciers',
            ),
        ),
        'meta_fields' => array(),
        'group_by'    => '',
    );
}

/**
 * Retrieve plugin settings, merged with defaults.
 *
 * @return array
 */
function wptt_get_settings() {
    $defaults = wptt_get_default_settings();
    $options  = get_option( 'wptt_settings', array() );

    if ( ! is_array( $options ) ) {
        $options = array();
    }

    $settings = wp_parse_args( $options, $defaults );

    $settings['columns']    = array_values( array_unique( (array) $settings['columns'] ) );
    $settings['taxonomies'] = array_values( array_filter( (array) $settings['taxonomies'], 'wptt_is_valid_taxonomy_setting' ) );
    $settings['meta_fields'] = array_values( array_filter( (array) $settings['meta_fields'], 'wptt_is_valid_meta_setting' ) );

    return $settings;
}

/**
 * Validate a taxonomy setting row.
 *
 * @param array $taxonomy Taxonomy setting.
 *
 * @return bool
 */
function wptt_is_valid_taxonomy_setting( $taxonomy ) {
    return is_array( $taxonomy ) && ! empty( $taxonomy['slug'] ) && ! empty( $taxonomy['label'] );
}

/**
 * Validate a meta setting row.
 *
 * @param array $meta Meta setting.
 *
 * @return bool
 */
function wptt_is_valid_meta_setting( $meta ) {
    return is_array( $meta ) && ! empty( $meta['key'] ) && ! empty( $meta['label'] );
}

/**
 * Register plugin settings.
 *
 * @return void
 */
function wptt_register_settings() {
    register_setting( 'wptt_settings_group', 'wptt_settings', 'wptt_sanitize_settings' );
}

/**
 * Sanitize settings before saving.
 *
 * @param array $input Raw settings input.
 *
 * @return array
 */
function wptt_sanitize_settings( $input ) {
    $defaults = wptt_get_default_settings();
    $sanitized = array(
        'columns'    => array(),
        'taxonomies' => array(),
        'meta_fields'=> array(),
        'group_by'   => '',
    );

    if ( isset( $input['taxonomies_raw'] ) ) {
        $sanitized['taxonomies'] = wptt_parse_key_label_lines( $input['taxonomies_raw'], 'slug' );
    } else {
        $sanitized['taxonomies'] = $defaults['taxonomies'];
    }

    if ( isset( $input['meta_fields_raw'] ) ) {
        $sanitized['meta_fields'] = wptt_parse_key_label_lines( $input['meta_fields_raw'], 'key' );
    }

    $available_columns = wptt_get_all_column_keys_from_settings( $sanitized );

    if ( ! empty( $input['columns'] ) && is_array( $input['columns'] ) ) {
        foreach ( $input['columns'] as $column ) {
            $column = sanitize_key( $column );

            if ( in_array( $column, $available_columns, true ) ) {
                $sanitized['columns'][] = $column;
            }
        }
    }

    if ( empty( $sanitized['columns'] ) ) {
        $default_columns = array_values( array_intersect( $defaults['columns'], $available_columns ) );

        if ( empty( $default_columns ) ) {
            $default_columns = $available_columns;
        }

        $sanitized['columns'] = $default_columns;
    }

    if ( isset( $input['group_by'] ) ) {
        $group_by = sanitize_key( $input['group_by'] );

        if ( in_array( $group_by, $available_columns, true ) ) {
            $sanitized['group_by'] = $group_by;
        }
    }

    return $sanitized;
}

/**
 * Parse key/label lines into associative array entries.
 *
 * @param string $raw      Raw textarea input.
 * @param string $key_name Array key name to use for the identifier.
 *
 * @return array
 */
function wptt_parse_key_label_lines( $raw, $key_name ) {
    $entries = array();

    $lines = preg_split( '/\r\n|\r|\n/', (string) $raw );

    foreach ( $lines as $line ) {
        $line = trim( $line );

        if ( '' === $line ) {
            continue;
        }

        $parts = array_map( 'trim', explode( '|', $line, 2 ) );

        if ( empty( $parts[0] ) ) {
            continue;
        }

        $entry = array(
            $key_name => sanitize_key( $parts[0] ),
            'label'   => isset( $parts[1] ) ? sanitize_text_field( $parts[1] ) : $parts[0],
        );

        if ( 'key' === $key_name ) {
            $entry['key']  = trim( $parts[0] );
            $entry['label'] = isset( $parts[1] ) ? sanitize_text_field( $parts[1] ) : trim( $parts[0] );
        }

        if ( '' === trim( $entry['label'] ) ) {
            $entry['label'] = $entry[ $key_name ];
        }

        $entries[] = $entry;
    }

    return $entries;
}

/**
 * Retrieve all available column keys for the supplied settings array.
 *
 * @param array $settings Settings array.
 *
 * @return array
 */
function wptt_get_all_column_keys_from_settings( $settings ) {
    $base = array( 'name', 'price', 'stock', 'product_cat' );

    $taxonomies = array();
    if ( ! empty( $settings['taxonomies'] ) ) {
        foreach ( $settings['taxonomies'] as $taxonomy ) {
            if ( ! empty( $taxonomy['slug'] ) ) {
                $taxonomies[] = sanitize_key( $taxonomy['slug'] );
            }
        }
    }

    $meta_keys = array();
    if ( ! empty( $settings['meta_fields'] ) ) {
        foreach ( $settings['meta_fields'] as $meta ) {
            if ( ! empty( $meta['key'] ) ) {
                $meta_keys[] = sanitize_key( $meta['key'] );
            }
        }
    }

    return array_values( array_unique( array_merge( $base, $taxonomies, $meta_keys ) ) );
}

/**
 * Add the plugin settings page to the admin menu.
 *
 * @return void
 */
function wptt_add_settings_page() {
    add_submenu_page(
        'woocommerce',
        __( 'Product Tag Tabel', 'woocommerce-product-tag-table' ),
        __( 'Product Tag Tabel', 'woocommerce-product-tag-table' ),
        'manage_woocommerce',
        'wptt-settings',
        'wptt_render_settings_page'
    );
}

/**
 * Render the settings page.
 *
 * @return void
 */
function wptt_render_settings_page() {
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        return;
    }

    $settings          = wptt_get_settings();
    $available_columns = wptt_get_column_definitions_from_settings( $settings );

    $taxonomy_lines = array();
    foreach ( $settings['taxonomies'] as $taxonomy ) {
        $taxonomy_lines[] = $taxonomy['slug'] . '|' . $taxonomy['label'];
    }

    $meta_lines = array();
    foreach ( $settings['meta_fields'] as $meta ) {
        $meta_lines[] = $meta['key'] . '|' . $meta['label'];
    }
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'Product Tag Tabel instellingen', 'woocommerce-product-tag-table' ); ?></h1>
        <form method="post" action="options.php">
            <?php settings_fields( 'wptt_settings_group' ); ?>
            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row">
                            <label for="wptt-columns"><?php esc_html_e( 'Zichtbare kolommen', 'woocommerce-product-tag-table' ); ?></label>
                        </th>
                        <td>
                            <fieldset id="wptt-columns">
                                <legend class="screen-reader-text"><?php esc_html_e( 'Selecteer zichtbare kolommen', 'woocommerce-product-tag-table' ); ?></legend>
                                <?php foreach ( $available_columns as $key => $definition ) : ?>
                                    <label>
                                        <input type="checkbox" name="wptt_settings[columns][]" value="<?php echo esc_attr( $key ); ?>" <?php checked( in_array( $key, $settings['columns'], true ) ); ?>>
                                        <?php echo esc_html( $definition['label'] ); ?>
                                    </label><br>
                                <?php endforeach; ?>
                            </fieldset>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="wptt-taxonomies"><?php esc_html_e( 'Taxonomie kolommen', 'woocommerce-product-tag-table' ); ?></label>
                        </th>
                        <td>
                            <p class="description"><?php esc_html_e( 'Voer één taxonomie per regel in als slug|Label. Bijvoorbeeld: region|Regio', 'woocommerce-product-tag-table' ); ?></p>
                            <textarea id="wptt-taxonomies" name="wptt_settings[taxonomies_raw]" rows="5" cols="60"><?php echo esc_textarea( implode( "\n", $taxonomy_lines ) ); ?></textarea>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="wptt-meta-fields"><?php esc_html_e( 'Meta kolommen', 'woocommerce-product-tag-table' ); ?></label>
                        </th>
                        <td>
                            <p class="description"><?php esc_html_e( 'Voer één meta sleutel per regel in als meta_sleutel|Label.', 'woocommerce-product-tag-table' ); ?></p>
                            <textarea id="wptt-meta-fields" name="wptt_settings[meta_fields_raw]" rows="5" cols="60"><?php echo esc_textarea( implode( "\n", $meta_lines ) ); ?></textarea>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="wptt-group-by"><?php esc_html_e( 'Groeperen op', 'woocommerce-product-tag-table' ); ?></label>
                        </th>
                        <td>
                            <select id="wptt-group-by" name="wptt_settings[group_by]">
                                <option value=""><?php esc_html_e( 'Geen groepering', 'woocommerce-product-tag-table' ); ?></option>
                                <?php foreach ( $available_columns as $key => $definition ) : ?>
                                    <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $settings['group_by'], $key ); ?>>
                                        <?php echo esc_html( sprintf( __( 'Op %s', 'woocommerce-product-tag-table' ), $definition['label'] ) ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                </tbody>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

/**
 * Build column definitions from settings.
 *
 * @param array $settings Settings array.
 *
 * @return array
 */
function wptt_get_column_definitions_from_settings( $settings ) {
    $definitions = array(
        'name'  => array(
            'label' => 'Naam',
            'type'  => 'builtin',
        ),
        'price' => array(
            'label' => 'Prijs',
            'type'  => 'builtin',
        ),
        'stock' => array(
            'label' => 'Voorraad',
            'type'  => 'builtin',
        ),
        'product_cat' => array(
            'label'    => __( 'Hoofd categorie', 'woocommerce-product-tag-table' ),
            'type'     => 'product_cat',
            'taxonomy' => 'product_cat',
        ),
    );

    if ( ! empty( $settings['taxonomies'] ) ) {
        foreach ( $settings['taxonomies'] as $taxonomy ) {
            $slug = sanitize_key( $taxonomy['slug'] );

            if ( empty( $slug ) ) {
                continue;
            }

            $definitions[ $slug ] = array(
                'label'    => $taxonomy['label'],
                'type'     => 'taxonomy',
                'taxonomy' => $slug,
            );
        }
    }

    if ( ! empty( $settings['meta_fields'] ) ) {
        foreach ( $settings['meta_fields'] as $meta ) {
            $key = sanitize_key( $meta['key'] );

            if ( empty( $key ) ) {
                continue;
            }

            $definitions[ $key ] = array(
                'label'   => $meta['label'],
                'type'    => 'meta',
                'meta_key'=> $meta['key'],
            );
        }
    }

    return $definitions;
}

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

    $settings           = wptt_get_settings();
    $column_definitions = wptt_get_column_definitions_from_settings( $settings );
    $available_columns  = array_keys( $column_definitions );

    $atts = shortcode_atts(
        array(
            'tag'                     => '',
            'columns'                 => implode( ',', $settings['columns'] ),
            'group_by'                => $settings['group_by'],
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
    $columns          = array_values( array_intersect( $columns, $available_columns ) );

    if ( empty( $columns ) ) {
        $columns = $settings['columns'];
    }

    $group_by = sanitize_key( $atts['group_by'] );

    if ( ! in_array( $group_by, $available_columns, true ) ) {
        $group_by = '';
    }

    $args = array(
        'post_type'      => 'product',
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
        'tax_query'      => array(
            array(
                'taxonomy' => 'product_tag',
                'field'    => 'slug',
                'terms'    => $atts['tag'],
            ),
        ),
    );

    $loop     = new WP_Query( $args );
    $products = array();

    while ( $loop->have_posts() ) {
        $loop->the_post();

        $product = wc_get_product( get_the_ID() );

        if ( $product ) {
            $products[] = $product;
        }
    }

    wp_reset_postdata();

    if ( empty( $products ) ) {
        return '<p>Geen producten gevonden voor tag: ' . esc_html( $atts['tag'] ) . '</p>';
    }

    $grouped_products = wptt_group_products( $products, $group_by, $column_definitions, $atts );

    ob_start();

    foreach ( $grouped_products as $group_key => $group ) {
        if ( '' !== $group_key ) {
            printf( '<h3 class="woocommerce-product-tag-table-group">%s</h3>', esc_html( $group['label'] ) );
        }
        ?>
        <table class="woocommerce-product-tag-table">
            <thead>
                <tr>
                    <?php foreach ( $columns as $column ) :
                        if ( isset( $column_definitions[ $column ] ) ) :
                            ?>
                            <th>
                                <?php echo esc_html( $column_definitions[ $column ]['label'] ); ?>
                            </th>
                            <?php
                        endif;
                    endforeach; ?>
                </tr>
            </thead>
            <tbody>
            <?php foreach ( $group['products'] as $product ) : ?>
                <tr>
                    <?php foreach ( $columns as $column ) :
                        if ( ! isset( $column_definitions[ $column ] ) ) {
                            continue;
                        }

                        $cell_value = wptt_get_column_value( $column, $product, $atts, $column_definitions );
                        ?>
                        <td>
                            <?php
                            if ( 'name' === $column ) {
                                ?>
                                <a href="<?php echo esc_url( get_permalink( $product->get_id() ) ); ?>"><?php echo esc_html( $cell_value ); ?></a>
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
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    return ob_get_clean();
}

/**
 * Group products for display.
 *
 * @param array  $products           Array of WC_Product objects.
 * @param string $group_by           Column key used for grouping.
 * @param array  $column_definitions Column definitions map.
 * @param array  $atts               Shortcode attributes.
 *
 * @return array
 */
function wptt_group_products( $products, $group_by, $column_definitions, $atts ) {
    if ( empty( $group_by ) || ! isset( $column_definitions[ $group_by ] ) ) {
        return array(
            '' => array(
                'label'    => '',
                'products' => $products,
            ),
        );
    }

    $definition = $column_definitions[ $group_by ];
    $groups     = array();

    foreach ( $products as $product ) {
        $group_keys = wptt_get_product_group_keys( $product, $group_by, $definition, $column_definitions, $atts );

        if ( empty( $group_keys ) ) {
            $group_keys = array(
                array(
                    'key'   => '__overig__',
                    'label' => __( 'Overig', 'woocommerce-product-tag-table' ),
                ),
            );
        }

        foreach ( $group_keys as $group_key ) {
            $key = $group_key['key'];

            if ( ! isset( $groups[ $key ] ) ) {
                $groups[ $key ] = array(
                    'label'    => $group_key['label'],
                    'products' => array(),
                );
            }

            $groups[ $key ]['products'][] = $product;
        }
    }

    uasort(
        $groups,
        function( $a, $b ) {
            return strcasecmp( $a['label'], $b['label'] );
        }
    );

    return $groups;
}

/**
 * Determine the grouping keys for a product based on the column definition.
 *
 * @param WC_Product $product           Product instance.
 * @param string     $column_key        Column key.
 * @param array      $definition        Column definition.
 * @param array      $column_definitions All column definitions.
 * @param array      $atts              Shortcode attributes.
 *
 * @return array
 */
function wptt_get_product_group_keys( $product, $column_key, $definition, $column_definitions, $atts ) {
    switch ( $definition['type'] ) {
        case 'taxonomy':
            $terms = get_the_terms( $product->get_id(), $definition['taxonomy'] );

            if ( empty( $terms ) || is_wp_error( $terms ) ) {
                return array();
            }

            $keys = array();

            foreach ( $terms as $term ) {
                $keys[] = array(
                    'key'   => $term->slug,
                    'label' => $term->name,
                );
            }

            return $keys;

        case 'meta':
            $value = get_post_meta( $product->get_id(), $definition['meta_key'], true );

            if ( is_array( $value ) ) {
                $value = implode( ', ', array_map( 'trim', $value ) );
            }

            $value = trim( (string) $value );

            if ( '' === $value ) {
                return array();
            }

            return array(
                array(
                    'key'   => sanitize_title( $value ),
                    'label' => $value,
                ),
            );

        case 'product_cat':
            $terms = wptt_get_primary_product_categories( $product );

            if ( empty( $terms ) ) {
                return array();
            }

            $keys = array();

            foreach ( $terms as $term ) {
                $keys[] = array(
                    'key'   => $term->slug,
                    'label' => $term->name,
                );
            }

            return $keys;

        case 'builtin':
        default:
            $value = wptt_get_column_value( $column_key, $product, $atts, $column_definitions );

            $value = wp_strip_all_tags( (string) $value );

            $value = trim( (string) $value );

            if ( '' === $value ) {
                return array();
            }

            return array(
                array(
                    'key'   => sanitize_title( $value ),
                    'label' => $value,
                ),
            );
    }
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
    $settings = wptt_get_settings();

    return wptt_get_column_definitions_from_settings( $settings );
}

/**
 * Get the display value for a specific column.
 *
 * @param string     $column  Column key.
 * @param WC_Product $product Product instance.
 * @param array      $atts    Shortcode attributes.
 * @param array      $column_definitions Column definitions map.
 *
 * @return string
 */
function wptt_get_column_value( $column, $product, $atts, $column_definitions ) {
    if ( ! isset( $column_definitions[ $column ] ) ) {
        return '';
    }

    $definition = $column_definitions[ $column ];

    switch ( $definition['type'] ) {
        case 'taxonomy':
            return wptt_get_taxonomy_terms_list( $product->get_id(), $definition['taxonomy'] );

        case 'meta':
            $value = get_post_meta( $product->get_id(), $definition['meta_key'], true );

            if ( is_array( $value ) ) {
                $value = implode( ', ', array_map( 'trim', $value ) );
            }

            return $value !== '' ? $value : '–';

        case 'product_cat':
            $terms = wptt_get_primary_product_categories( $product );

            if ( empty( $terms ) ) {
                return '–';
            }

            return implode( ', ', wp_list_pluck( $terms, 'name' ) );

        case 'builtin':
        default:
            switch ( $column ) {
                case 'price':
                    return $product->get_price_html();

                case 'stock':
                    return wptt_get_stock_display_value( $product, $atts );

                case 'name':
                default:
                    return $product->get_name();
            }
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
 * Retrieve the primary product categories for a product.
 *
 * The function will return the top-most parent categories for any assigned
 * product categories so grouping mirrors a "main" category structure.
 *
 * @param WC_Product $product Product instance.
 *
 * @return array Array of WP_Term objects.
 */
function wptt_get_primary_product_categories( $product ) {
    $terms = wc_get_product_terms(
        $product->get_id(),
        'product_cat',
        array(
            'orderby' => 'parent',
            'order'   => 'ASC',
        )
    );

    if ( empty( $terms ) ) {
        return array();
    }

    $primary_terms = array();

    foreach ( $terms as $term ) {
        $top_term = $term;

        while ( $top_term->parent ) {
            $parent = get_term( $top_term->parent, 'product_cat' );

            if ( ! $parent || is_wp_error( $parent ) ) {
                break;
            }

            $top_term = $parent;
        }

        $primary_terms[ $top_term->term_id ] = $top_term;
    }

    return array_values( $primary_terms );
}

/**
 * Register the frontend styles for the product tag table output.
 *
 * @return void
 */
function wptt_enqueue_assets() {
    $handle = 'wptt-frontend';

    wp_register_style( $handle, false, array(), '1.0.0' );

    wp_enqueue_style( $handle );

    $css = '.woocommerce-product-tag-table{width:100%;border-collapse:collapse;table-layout:auto;}'
        . '.woocommerce-product-tag-table thead tr{border-bottom:2px solid #ccc;}'
        . '.woocommerce-product-tag-table th,'
        . '.woocommerce-product-tag-table td{padding:0.75rem;text-align:left;vertical-align:top;}'
        . '.woocommerce-product-tag-table tbody tr{border-bottom:1px solid #eee;}'
        . '.woocommerce-product-tag-table-group{margin-top:2rem;margin-bottom:0.75rem;}';

    wp_add_inline_style( $handle, $css );
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
