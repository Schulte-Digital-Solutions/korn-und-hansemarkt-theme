<?php
/**
 * WordPress Abilities API – lesende Abilities für MCP-Clients.
 *
 * Benötigt die Abilities API (WordPress 6.9+ oder Abilities-API-Plugin).
 * Die Abilities werden zusätzlich über den offiziellen MCP Adapter
 * (WordPress/mcp-adapter) auf dem Default-MCP-Server veröffentlicht.
 *
 * @package KornUndHansemarkt
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

const KUH_ABILITY_NAMESPACE = 'kuh';
const KUH_ABILITY_CATEGORY  = 'korn-und-hansemarkt';

/**
 * Ability-Kategorie registrieren.
 *
 * Muss vor den Abilities selbst laufen – der Core feuert diese Action
 * entsprechend vor `wp_abilities_api_init`.
 */
function kuh_register_ability_categories() {
    if ( ! function_exists( 'wp_register_ability_category' ) ) {
        return;
    }

    wp_register_ability_category(
        KUH_ABILITY_CATEGORY,
        array(
            'label'       => __( 'Korn- und Hansemarkt', 'korn-und-hansemarkt' ),
            'description' => __( 'Lesende Abilities für Inhalte und Einstellungen der Korn- und Hansemarkt-Website.', 'korn-und-hansemarkt' ),
        )
    );
}
add_action( 'wp_abilities_api_categories_init', 'kuh_register_ability_categories' );

/**
 * Gemeinsames Meta für alle Abilities dieses Themes.
 *
 * `public` ist der High-Level-Schalter des MCP Adapters, `mcp.public` der
 * MCP-spezifische Override. `show_in_rest` macht die Ability zusätzlich über
 * die Core-REST-Routen aufrufbar.
 *
 * @param array $overrides Werte, die das Standard-Meta überschreiben.
 * @return array
 */
function kuh_ability_meta( array $overrides = array() ) {
    $defaults = array(
        'show_in_rest' => true,
        'public'       => true,
        'mcp'          => array(
            'public' => true,
        ),
        'annotations'  => array(
            'readonly'   => true,
            'idempotent' => true,
        ),
    );

    return array_merge( $defaults, $overrides );
}

/**
 * Permission-Callback für lesende Abilities.
 *
 * @return bool
 */
function kuh_ability_can_read() {
    return current_user_can( 'read' );
}

/**
 * Input-Schema für Abilities ohne bzw. mit optionalen Parametern.
 *
 * Der `default`-Wert sorgt dafür, dass ein Aufruf ganz ohne Input
 * (`$ability->execute()`) die Schema-Validierung besteht.
 *
 * @param array $properties JSON-Schema-Properties.
 * @return array
 */
function kuh_ability_input_schema( array $properties = array() ) {
    return array(
        'type'                 => 'object',
        'properties'           => $properties,
        'additionalProperties' => false,
        'default'              => array(),
    );
}

/**
 * Einen Beitrag/eine Seite für die Ability-Ausgabe aufbereiten.
 *
 * @param WP_Post $post Post-Objekt.
 * @return array
 */
function kuh_ability_format_post( WP_Post $post ) {
    $thumbnail = get_the_post_thumbnail_url( $post, 'large' );

    return array(
        'id'             => $post->ID,
        'title'          => get_the_title( $post ),
        'excerpt'        => kuh_ability_get_excerpt( $post ),
        'date'           => get_post_datetime( $post )->format( DATE_W3C ),
        'permalink'      => (string) get_permalink( $post ),
        'featured_image' => $thumbnail ? $thumbnail : null,
    );
}

/**
 * Auszug ohne Shortcodes, Blocks-Kommentare und HTML erzeugen.
 *
 * `get_the_excerpt()` läuft durch Theme-/Plugin-Filter (u. a. Complianz),
 * daher wird für Beiträge ohne manuellen Auszug selbst gekürzt.
 *
 * @param WP_Post $post Post-Objekt.
 * @return string
 */
function kuh_ability_get_excerpt( WP_Post $post ) {
    if ( '' !== trim( $post->post_excerpt ) ) {
        return wp_strip_all_tags( $post->post_excerpt );
    }

    $content = strip_shortcodes( $post->post_content );
    $content = excerpt_remove_blocks( $content );
    $content = wp_strip_all_tags( $content );

    return wp_trim_words( $content, 30, '…' );
}

/**
 * Alle Abilities dieses Themes registrieren.
 *
 * Weitere Abilities einfach am Ende der Funktion ergänzen.
 */
function kuh_register_abilities() {
    if ( ! function_exists( 'wp_register_ability' ) ) {
        return;
    }

    /*
     * kuh/get-site-info – Basisdaten der Website.
     */
    wp_register_ability(
        KUH_ABILITY_NAMESPACE . '/get-site-info',
        array(
            'label'               => __( 'Website-Informationen abrufen', 'korn-und-hansemarkt' ),
            'description'         => __( 'Liefert Basisdaten der Website: Name, Beschreibung, URL, WordPress-Version, Sprache und Zeitzone.', 'korn-und-hansemarkt' ),
            'category'            => KUH_ABILITY_CATEGORY,
            'input_schema'        => kuh_ability_input_schema(),
            'output_schema'       => array(
                'type'       => 'object',
                'properties' => array(
                    'name'        => array(
                        'type'        => 'string',
                        'description' => __( 'Name der Website (Blogname).', 'korn-und-hansemarkt' ),
                    ),
                    'description' => array(
                        'type'        => 'string',
                        'description' => __( 'Untertitel bzw. Kurzbeschreibung der Website.', 'korn-und-hansemarkt' ),
                    ),
                    'url'         => array(
                        'type'        => 'string',
                        'format'      => 'uri',
                        'description' => __( 'Startseiten-URL der Website.', 'korn-und-hansemarkt' ),
                    ),
                    'wp_version'  => array(
                        'type'        => 'string',
                        'description' => __( 'Installierte WordPress-Version.', 'korn-und-hansemarkt' ),
                    ),
                    'language'    => array(
                        'type'        => 'string',
                        'description' => __( 'Sprachcode der Website, z. B. de-DE.', 'korn-und-hansemarkt' ),
                    ),
                    'timezone'    => array(
                        'type'        => 'string',
                        'description' => __( 'Zeitzone der Website.', 'korn-und-hansemarkt' ),
                    ),
                ),
            ),
            'execute_callback'    => 'kuh_ability_get_site_info',
            'permission_callback' => 'kuh_ability_can_read',
            'meta'                => kuh_ability_meta(),
        )
    );

    /*
     * kuh/list-posts – zuletzt veröffentlichte Beiträge.
     */
    wp_register_ability(
        KUH_ABILITY_NAMESPACE . '/list-posts',
        array(
            'label'               => __( 'Beiträge auflisten', 'korn-und-hansemarkt' ),
            'description'         => __( 'Liefert die zuletzt veröffentlichten Beiträge mit Titel, Auszug, Datum und Permalink. Optional nach Suchbegriff oder Kategorie gefiltert.', 'korn-und-hansemarkt' ),
            'category'            => KUH_ABILITY_CATEGORY,
            'input_schema'        => kuh_ability_input_schema(
                array(
                    'count'    => array(
                        'type'        => 'integer',
                        'description' => __( 'Anzahl der Beiträge (1–50).', 'korn-und-hansemarkt' ),
                        'minimum'     => 1,
                        'maximum'     => 50,
                        'default'     => 10,
                    ),
                    'search'   => array(
                        'type'        => 'string',
                        'description' => __( 'Optionaler Suchbegriff für Titel und Inhalt.', 'korn-und-hansemarkt' ),
                    ),
                    'category' => array(
                        'type'        => 'string',
                        'description' => __( 'Optionaler Kategorie-Slug zum Filtern.', 'korn-und-hansemarkt' ),
                    ),
                )
            ),
            'output_schema'       => array(
                'type'  => 'array',
                'items' => array(
                    'type'       => 'object',
                    'properties' => kuh_ability_post_output_properties(),
                ),
            ),
            'execute_callback'    => 'kuh_ability_list_posts',
            'permission_callback' => 'kuh_ability_can_read',
            'meta'                => kuh_ability_meta(),
        )
    );

    /*
     * kuh/list-pages – alle veröffentlichten Seiten.
     */
    wp_register_ability(
        KUH_ABILITY_NAMESPACE . '/list-pages',
        array(
            'label'               => __( 'Seiten auflisten', 'korn-und-hansemarkt' ),
            'description'         => __( 'Liefert alle veröffentlichten Seiten mit Titel, Permalink, Slug und übergeordneter Seite.', 'korn-und-hansemarkt' ),
            'category'            => KUH_ABILITY_CATEGORY,
            'input_schema'        => kuh_ability_input_schema(
                array(
                    'parent' => array(
                        'type'        => 'integer',
                        'description' => __( 'Optionale ID einer übergeordneten Seite; liefert nur deren direkte Unterseiten.', 'korn-und-hansemarkt' ),
                        'minimum'     => 0,
                    ),
                )
            ),
            'output_schema'       => array(
                'type'  => 'array',
                'items' => array(
                    'type'       => 'object',
                    'properties' => array(
                        'id'        => array(
                            'type'        => 'integer',
                            'description' => __( 'Seiten-ID.', 'korn-und-hansemarkt' ),
                        ),
                        'title'     => array(
                            'type'        => 'string',
                            'description' => __( 'Seitentitel.', 'korn-und-hansemarkt' ),
                        ),
                        'permalink' => array(
                            'type'        => 'string',
                            'format'      => 'uri',
                            'description' => __( 'Permalink der Seite.', 'korn-und-hansemarkt' ),
                        ),
                        'slug'      => array(
                            'type'        => 'string',
                            'description' => __( 'Slug der Seite.', 'korn-und-hansemarkt' ),
                        ),
                        'parent'    => array(
                            'type'        => 'integer',
                            'description' => __( 'ID der übergeordneten Seite, 0 bei Top-Level-Seiten.', 'korn-und-hansemarkt' ),
                        ),
                    ),
                ),
            ),
            'execute_callback'    => 'kuh_ability_list_pages',
            'permission_callback' => 'kuh_ability_can_read',
            'meta'                => kuh_ability_meta(),
        )
    );

    // Weitere Abilities hier ergänzen – Muster siehe oben:
    // label, description, category, input_schema, output_schema,
    // execute_callback, permission_callback, meta => kuh_ability_meta().
}
add_action( 'wp_abilities_api_init', 'kuh_register_abilities' );

/**
 * Kompatibilitäts-Fix für den MCP Adapter 0.4.x.
 *
 * Der Adapter registriert `mcp-adapter/discover-abilities` ohne `input_schema`.
 * WP_Ability::validate_input() bricht seit WP 6.9 jeden Aufruf mit Input ab,
 * wenn kein Schema definiert ist – der MCP-Client sendet aber mindestens `{}`.
 * Ergebnis: Das Discovery-Tool liefert nur einen Fehler und der Client sieht
 * keine einzige Ability.
 *
 * @param array  $args Ability-Argumente.
 * @param string $name Ability-Name inkl. Namespace.
 * @return array
 */
function kuh_fix_mcp_adapter_input_schema( $args, $name ) {
    if ( 'mcp-adapter/discover-abilities' !== $name ) {
        return $args;
    }

    if ( empty( $args['input_schema'] ) ) {
        $args['input_schema'] = kuh_ability_input_schema();
    }

    return $args;
}
add_filter( 'wp_register_ability_args', 'kuh_fix_mcp_adapter_input_schema', 10, 2 );

/**
 * Abilities dieses Themes als eigenständige Tools auf dem Default-MCP-Server
 * veröffentlichen.
 *
 * Der Adapter registriert von sich aus nur die drei Meta-Tools
 * (discover-abilities, get-ability-info, execute-ability); eigene Abilities
 * wären sonst nur indirekt über `execute-ability` erreichbar.
 *
 * @param array $config Server-Konfiguration des Adapters.
 * @return array
 */
function kuh_expose_abilities_as_mcp_tools( $config ) {
    if ( ! is_array( $config ) || ! function_exists( 'wp_get_abilities' ) ) {
        return $config;
    }

    $tools  = isset( $config['tools'] ) && is_array( $config['tools'] ) ? $config['tools'] : array();
    $prefix = KUH_ABILITY_NAMESPACE . '/';

    foreach ( wp_get_abilities() as $ability ) {
        $name = $ability->get_name();
        if ( str_starts_with( $name, $prefix ) && ! in_array( $name, $tools, true ) ) {
            $tools[] = $name;
        }
    }

    $config['tools'] = $tools;

    return $config;
}
add_filter( 'mcp_adapter_default_server_config', 'kuh_expose_abilities_as_mcp_tools' );

/**
 * Output-Properties für Beitrags-Listen (wird von mehreren Abilities genutzt).
 *
 * @return array
 */
function kuh_ability_post_output_properties() {
    return array(
        'id'             => array(
            'type'        => 'integer',
            'description' => __( 'Beitrags-ID.', 'korn-und-hansemarkt' ),
        ),
        'title'          => array(
            'type'        => 'string',
            'description' => __( 'Titel des Beitrags.', 'korn-und-hansemarkt' ),
        ),
        'excerpt'        => array(
            'type'        => 'string',
            'description' => __( 'Auszug als reiner Text.', 'korn-und-hansemarkt' ),
        ),
        'date'           => array(
            'type'        => 'string',
            'format'      => 'date-time',
            'description' => __( 'Veröffentlichungsdatum im ISO-8601-Format.', 'korn-und-hansemarkt' ),
        ),
        'permalink'      => array(
            'type'        => 'string',
            'format'      => 'uri',
            'description' => __( 'Permalink des Beitrags.', 'korn-und-hansemarkt' ),
        ),
        'featured_image' => array(
            'type'        => array( 'string', 'null' ),
            'description' => __( 'URL des Beitragsbilds oder null.', 'korn-und-hansemarkt' ),
        ),
    );
}

/**
 * Execute-Callback: kuh/get-site-info
 *
 * @param array|null $input Ungenutzt, das Schema erlaubt keine Parameter.
 * @return array
 */
function kuh_ability_get_site_info( $input = null ) {
    return array(
        'name'        => get_bloginfo( 'name' ),
        'description' => get_bloginfo( 'description' ),
        'url'         => home_url( '/' ),
        'wp_version'  => get_bloginfo( 'version' ),
        'language'    => get_bloginfo( 'language' ),
        'timezone'    => wp_timezone_string(),
    );
}

/**
 * Execute-Callback: kuh/list-posts
 *
 * @param array|null $input Optionale Parameter (count, search, category).
 * @return array|WP_Error
 */
function kuh_ability_list_posts( $input = null ) {
    $input = is_array( $input ) ? $input : array();
    $count = isset( $input['count'] ) ? (int) $input['count'] : 10;

    $args = array(
        'post_type'              => 'post',
        'post_status'            => 'publish',
        'posts_per_page'         => $count,
        'orderby'                => 'date',
        'order'                  => 'DESC',
        'ignore_sticky_posts'    => true,
        'no_found_rows'          => true,
        'update_post_term_cache' => false,
    );

    if ( ! empty( $input['search'] ) ) {
        $args['s'] = sanitize_text_field( $input['search'] );
    }

    if ( ! empty( $input['category'] ) ) {
        $slug = sanitize_title( $input['category'] );
        if ( ! term_exists( $slug, 'category' ) ) {
            return new WP_Error(
                'kuh_unknown_category',
                sprintf(
                    /* translators: %s: Kategorie-Slug. */
                    __( 'Die Kategorie "%s" existiert nicht.', 'korn-und-hansemarkt' ),
                    $slug
                )
            );
        }
        $args['category_name'] = $slug;
    }

    $query = new WP_Query( $args );

    return array_map( 'kuh_ability_format_post', $query->posts );
}

/**
 * Execute-Callback: kuh/list-pages
 *
 * @param array|null $input Optionale Parameter (parent).
 * @return array
 */
function kuh_ability_list_pages( $input = null ) {
    $input = is_array( $input ) ? $input : array();

    $args = array(
        'post_type'              => 'page',
        'post_status'            => 'publish',
        'posts_per_page'         => -1,
        'orderby'                => array(
            'menu_order' => 'ASC',
            'title'      => 'ASC',
        ),
        'no_found_rows'          => true,
        'update_post_term_cache' => false,
    );

    if ( isset( $input['parent'] ) ) {
        $args['post_parent'] = (int) $input['parent'];
    }

    $query = new WP_Query( $args );

    return array_map(
        static function ( WP_Post $page ) {
            return array(
                'id'        => $page->ID,
                'title'     => get_the_title( $page ),
                'permalink' => (string) get_permalink( $page ),
                'slug'      => $page->post_name,
                'parent'    => (int) $page->post_parent,
            );
        },
        $query->posts
    );
}
