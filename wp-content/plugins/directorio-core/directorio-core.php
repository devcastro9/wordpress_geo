<?php
/**
 * Plugin Name: Directorio Core
 * Description: Tipo de contenido Negocio, taxonomías, ubicación en mapa (Leaflet/OpenStreetMap) y shortcode [mapa_negocios].
 * Version: 1.0.0
 * Text Domain: directorio-core
 */
 
if (!defined('ABSPATH')) {
    exit;
}
 
define('DIR_CORE_VERSION', '1.0.0');
define('DIR_LEAFLET_VERSION', '1.9.4');
 
/* ------------------------------------------------------------------
 * 1. Tipo de contenido "Negocio" y taxonomías
 * ------------------------------------------------------------------ */
function dir_core_register_types() {
    register_post_type('negocio', [
        'labels' => [
            'name'               => 'Negocios',
            'singular_name'      => 'Negocio',
            'add_new'            => 'Añadir nuevo',
            'add_new_item'       => 'Añadir negocio',
            'edit_item'          => 'Editar negocio',
            'new_item'           => 'Nuevo negocio',
            'view_item'          => 'Ver negocio',
            'search_items'       => 'Buscar negocios',
            'not_found'          => 'No se encontraron negocios',
            'not_found_in_trash' => 'No hay negocios en la papelera',
        ],
        'public'       => true,
        'has_archive'  => true,
        'menu_icon'    => 'dashicons-store',
        'show_in_rest' => true,
        'supports'     => ['title', 'editor', 'thumbnail', 'excerpt'],
        'rewrite'      => ['slug' => 'negocios'],
    ]);
 
    register_taxonomy('categoria_negocio', 'negocio', [
        'label'             => 'Categorías',
        'hierarchical'      => true,
        'show_in_rest'      => true,
        'show_admin_column' => true,
        'rewrite'           => ['slug' => 'categoria'],
    ]);
 
    register_taxonomy('zona', 'negocio', [
        'label'             => 'Zonas',
        'hierarchical'      => true,
        'show_in_rest'      => true,
        'show_admin_column' => true,
        'rewrite'           => ['slug' => 'zona'],
    ]);
}
add_action('init', 'dir_core_register_types');
 
// Al activar el plugin, registrar los tipos y refrescar los enlaces permanentes
register_activation_hook(__FILE__, function () {
    dir_core_register_types();
    flush_rewrite_rules();
});
register_deactivation_hook(__FILE__, 'flush_rewrite_rules');
 
/* ------------------------------------------------------------------
 * 2. Metabox de ubicación
 * ------------------------------------------------------------------ */
add_action('add_meta_boxes', function () {
    add_meta_box('negocio_ubicacion', 'Ubicación', 'dir_core_render_ubicacion', 'negocio', 'normal', 'high');
});
 
function dir_core_render_ubicacion($post) {
    wp_nonce_field('dir_guardar_ubicacion', 'dir_nonce');
 
    $lat = get_post_meta($post->ID, '_dir_lat', true);
    $lng = get_post_meta($post->ID, '_dir_lng', true);
    $dir = get_post_meta($post->ID, '_dir_direccion', true);
 
    // Centro por defecto: La Paz, Bolivia
    $lat = ($lat !== '') ? $lat : '-16.5000000';
    $lng = ($lng !== '') ? $lng : '-68.1500000';
    ?>
    <p>
        <label for="dir_direccion"><strong>Dirección</strong></label><br>
        <input type="text" id="dir_direccion" name="dir_direccion" value="<?php echo esc_attr($dir); ?>" style="width:100%">
    </p>
    <p>Haz clic en el mapa o arrastra el marcador para fijar la ubicación.</p>
    <div id="dir-mapa-admin" style="height:350px;"></div>
    <p>
        <small>
            Latitud: <input type="text" id="dir_lat" name="dir_lat" value="<?php echo esc_attr($lat); ?>" size="14" readonly>
            Longitud: <input type="text" id="dir_lng" name="dir_lng" value="<?php echo esc_attr($lng); ?>" size="14" readonly>
        </small>
    </p>
    <?php
}
 
add_action('save_post_negocio', function ($post_id) {
    if (!isset($_POST['dir_nonce']) || !wp_verify_nonce($_POST['dir_nonce'], 'dir_guardar_ubicacion')) {
        return;
    }
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }
 
    $lat = isset($_POST['dir_lat']) ? (float) $_POST['dir_lat'] : 0.0;
    $lng = isset($_POST['dir_lng']) ? (float) $_POST['dir_lng'] : 0.0;
 
    // Validar rangos geográficos
    $lat = max(-90.0, min(90.0, $lat));
    $lng = max(-180.0, min(180.0, $lng));
 
    update_post_meta($post_id, '_dir_direccion', sanitize_text_field(wp_unslash($_POST['dir_direccion'] ?? '')));
    update_post_meta($post_id, '_dir_lat', $lat);
    update_post_meta($post_id, '_dir_lng', $lng);
});
 
/* ------------------------------------------------------------------
 * 3. Leaflet (se carga desde cdnjs)
 * ------------------------------------------------------------------ */
function dir_core_enqueue_leaflet() {
    $v = DIR_LEAFLET_VERSION;
    wp_enqueue_style('leaflet', "https://cdnjs.cloudflare.com/ajax/libs/leaflet/{$v}/leaflet.min.css", [], $v);
    wp_enqueue_script('leaflet', "https://cdnjs.cloudflare.com/ajax/libs/leaflet/{$v}/leaflet.min.js", [], $v, true);
}
 
// Panel de administración: solo al editar un negocio
add_action('admin_enqueue_scripts', function ($hook) {
    global $post_type;
    if (!in_array($hook, ['post.php', 'post-new.php'], true) || $post_type !== 'negocio') {
        return;
    }
    dir_core_enqueue_leaflet();
    wp_enqueue_script(
        'dir-admin-mapa',
        plugin_dir_url(__FILE__) . 'assets/admin-mapa.js',
        ['leaflet'],
        DIR_CORE_VERSION,
        true
    );
});
 
/* ------------------------------------------------------------------
 * 4. Shortcode [mapa_negocios] con todos los negocios publicados
 * ------------------------------------------------------------------ */
add_shortcode('mapa_negocios', function () {
    $negocios = get_posts([
        'post_type'   => 'negocio',
        'post_status' => 'publish',
        'numberposts' => -1,
    ]);
 
    $datos = [];
    foreach ($negocios as $n) {
        $lat = get_post_meta($n->ID, '_dir_lat', true);
        $lng = get_post_meta($n->ID, '_dir_lng', true);
        if ($lat === '' || $lng === '') {
            continue;
        }
        $datos[] = [
            'titulo' => get_the_title($n),
            'url'    => get_permalink($n),
            'dir'    => get_post_meta($n->ID, '_dir_direccion', true),
            'lat'    => (float) $lat,
            'lng'    => (float) $lng,
        ];
    }
 
    dir_core_enqueue_leaflet();
    wp_enqueue_script(
        'dir-front-mapa',
        plugin_dir_url(__FILE__) . 'assets/front-mapa.js',
        ['leaflet'],
        DIR_CORE_VERSION,
        true
    );
    wp_localize_script('dir-front-mapa', 'DIR_NEGOCIOS', $datos);
 
    return '<div id="dir-mapa-front" style="height:500px;"></div>';
});
 
/* ------------------------------------------------------------------
 * 4b. Ficha pública: dirección, mapa y "Cómo llegar" bajo el contenido
 * ------------------------------------------------------------------ */
add_filter('the_content', function ($content) {
    if (!is_singular('negocio') || !in_the_loop() || !is_main_query()) {
        return $content;
    }
 
    $id  = get_the_ID();
    $lat = get_post_meta($id, '_dir_lat', true);
    $lng = get_post_meta($id, '_dir_lng', true);
    $dir = get_post_meta($id, '_dir_direccion', true);
 
    if ($lat === '' || $lng === '') {
        return $content;
    }
 
    dir_core_enqueue_leaflet();
    wp_enqueue_script(
        'dir-ficha-mapa',
        plugin_dir_url(__FILE__) . 'assets/ficha-mapa.js',
        ['leaflet'],
        DIR_CORE_VERSION,
        true
    );
 
    $ruta = 'https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode($lat . ',' . $lng);
 
    $html  = '<section class="dir-ubicacion">';
    $html .= '<h3>Ubicación</h3>';
    if ($dir) {
        $html .= '<p>' . esc_html($dir) . '</p>';
    }
    $html .= sprintf(
        '<div class="dir-mapa-ficha" data-lat="%s" data-lng="%s" style="height:350px;"></div>',
        esc_attr($lat),
        esc_attr($lng)
    );
    $html .= '<p><a href="' . esc_url($ruta) . '" target="_blank" rel="noopener">Cómo llegar</a></p>';
    $html .= '</section>';
 
    return $content . $html;
});
 
/* ------------------------------------------------------------------
 * 5. ACF: guardar y leer los grupos de campos desde acf-json (versionado en Git)
 * ------------------------------------------------------------------ */
add_filter('acf/settings/save_json', function () {
    return __DIR__ . '/acf-json';
});
add_filter('acf/settings/load_json', function ($paths) {
    $paths[] = __DIR__ . '/acf-json';
    return $paths;
});