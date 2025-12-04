<?php
/**
 * Plugin Name: Contact Form 7 to API - LexHoy
 * Plugin URI: https://lexhoy.com
 * Description: Envía los envíos de Contact Form 7 a la API de LexHoy para procesamiento de leads
 * Version: 1.0.2
 * Author: LexHoy
 * Author URI: https://lexhoy.com
 * Text Domain: cf7-to-lexhoy-api
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Definir constantes
define('CF7_LEXHOY_API_VERSION', '1.0.2');

// ... (resto del código igual hasta el final) ...

/**
 * Inicializar actualizador desde GitHub
 */
add_action('init', 'cf7_lexhoy_init_updater');

function cf7_lexhoy_init_updater() {
    if (is_admin()) {
        $updater_path = plugin_dir_path(__FILE__) . 'includes/class-plugin-updater.php';
        
        if (file_exists($updater_path)) {
            require_once $updater_path;
            
            // CONFIGURACIÓN DEL REPOSITORIO GITHUB
            // Cambia estos valores por tu usuario y repositorio real
            $github_user = 'LexHoy'; 
            $github_repo = 'cf7-to-lexhoy-api';
            $access_token = ''; // Opcional: Solo para repositorios privados
            
            new CF7_LexHoy_Updater(
                __FILE__, 
                $github_user, 
                $github_repo,
                $access_token
            );
        }
    }
}

/**
 * Hook para capturar envíos de Contact Form 7
 */
add_action('wpcf7_mail_sent', 'cf7_lexhoy_send_to_api', 10, 1);

function cf7_lexhoy_send_to_api($contact_form) {
    // Verificar que WPCF7_Submission existe para evitar errores fatales
    if (!class_exists('WPCF7_Submission')) {
        return;
    }

    try {
        // Obtener la URL de la API desde las opciones
        $api_url = get_option('cf7_lexhoy_api_url', 'https://apibacklexhoy.onrender.com/api/leads');
        
        // Obtener los datos del formulario
        $submission = WPCF7_Submission::get_instance();
        
        if (!$submission) {
            return;
        }
        
        $posted_data = $submission->get_posted_data();
        
        // Helper para buscar valor en múltiples claves
        $find_value = function($keys, $data) {
            foreach ($keys as $key) {
                if (isset($data[$key]) && !empty($data[$key])) {
                    return $data[$key];
                }
            }
            return '';
        };

        // Definir posibles claves para cada campo
        $name_keys = ['your-name', 'nombre', 'name', 'nombre-completo', 'full-name', 'usuario'];
        $email_keys = ['your-email', 'email', 'correo', 'correo-electronico', 'e-mail'];
        $phone_keys = ['your-phone', 'tel', 'telefono', 'phone', 'movil', 'celular'];
        $message_keys = ['your-message', 'mensaje', 'message', 'cuerpo', 'comentarios', 'consulta'];
        $terms_keys = ['acceptance-terms', 'terminos', 'aceptacion', 'condiciones', 'gdpr'];
        
        // Obtener información de la página de forma segura
        $current_url = home_url($_SERVER['REQUEST_URI']);
        $page_title = function_exists('get_the_title') ? get_the_title() : 'Formulario Web';
        
        // Preparar los datos para enviar a la API
        $data = array(
            'nombre' => sanitize_text_field($find_value($name_keys, $posted_data)),
            'correo' => sanitize_email($find_value($email_keys, $posted_data)),
            'telefono' => sanitize_text_field($find_value($phone_keys, $posted_data)),
            'cuerpoMensaje' => sanitize_textarea_field($find_value($message_keys, $posted_data)),
            'urlPagina' => $current_url,
            'tituloPost' => $page_title,
            'checkbox' => ($find_value($terms_keys, $posted_data) ? true : false)
        );
        
        // Si no se encontró mensaje, intentar concatenar otros campos no mapeados
        if (empty($data['cuerpoMensaje'])) {
            $extra_fields = [];
            $mapped_keys = array_merge($name_keys, $email_keys, $phone_keys, $message_keys, $terms_keys);
            
            foreach ($posted_data as $key => $value) {
                // Ignorar campos internos de CF7 (empiezan con _) y campos ya mapeados
                if (strpos($key, '_') !== 0 && !in_array($key, $mapped_keys) && is_string($value)) {
                    $extra_fields[] = ucfirst($key) . ': ' . $value;
                }
            }
            
            if (!empty($extra_fields)) {
                $data['cuerpoMensaje'] = implode("\n", $extra_fields);
            }
        }

        // Log para debugging
        error_log('CF7 to LexHoy API: Enviando datos: ' . print_r($data, true));
        
        // Enviar a la API
        $response = wp_remote_post($api_url, array(
            'method' => 'POST',
            'timeout' => 45,
            'blocking' => false, // No bloquear la carga de la página
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode($data),
        ));
        
        // Log para debugging (opcional)
        if (is_wp_error($response)) {
            error_log('CF7 to LexHoy API Error: ' . $response->get_error_message());
        }
    } catch (Exception $e) {
        error_log('CF7 to LexHoy API Exception: ' . $e->getMessage());
    }
}

/**
 * Añadir página de configuración en el admin
 */
add_action('admin_menu', 'cf7_lexhoy_add_admin_menu');

function cf7_lexhoy_add_admin_menu() {
    add_options_page(
        'CF7 to LexHoy API Settings',
        'CF7 to LexHoy API',
        'manage_options',
        'cf7-lexhoy-api',
        'cf7_lexhoy_settings_page'
    );
}

/**
 * Página de configuración
 */
function cf7_lexhoy_settings_page() {
    ?>
    <div class="wrap">
        <h1>Contact Form 7 to LexHoy API - Configuración</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('cf7_lexhoy_api_settings');
            do_settings_sections('cf7-lexhoy-api');
            submit_button();
            ?>
        </form>
        
        <div class="card" style="max-width: 800px; margin-top: 20px;">
            <h2>Instrucciones</h2>
            <p>Este plugin envía automáticamente los envíos de Contact Form 7 a la API de LexHoy para procesamiento de leads.</p>
            <h3>Campos del formulario requeridos:</h3>
            <ul>
                <li><code>your-name</code> - Nombre del contacto</li>
                <li><code>your-email</code> - Email del contacto</li>
                <li><code>your-phone</code> - Teléfono (opcional)</li>
                <li><code>your-message</code> - Mensaje/consulta</li>
                <li><code>acceptance-terms</code> - Checkbox de aceptación de términos (opcional)</li>
            </ul>
            <h3>URL actual de la API:</h3>
            <p><code><?php echo esc_html(get_option('cf7_lexhoy_api_url', 'https://apibacklexhoy.onrender.com/api/leads')); ?></code></p>
        </div>
    </div>
    <?php
}

/**
 * Registrar configuraciones
 */
add_action('admin_init', 'cf7_lexhoy_register_settings');

function cf7_lexhoy_register_settings() {
    register_setting('cf7_lexhoy_api_settings', 'cf7_lexhoy_api_url');
    
    add_settings_section(
        'cf7_lexhoy_api_section',
        'Configuración de la API',
        'cf7_lexhoy_section_callback',
        'cf7-lexhoy-api'
    );
    
    add_settings_field(
        'cf7_lexhoy_api_url',
        'URL de la API',
        'cf7_lexhoy_api_url_callback',
        'cf7-lexhoy-api',
        'cf7_lexhoy_api_section'
    );
}

function cf7_lexhoy_section_callback() {
    echo '<p>Configura la URL de la API de LexHoy donde se enviarán los leads.</p>';
}

function cf7_lexhoy_api_url_callback() {
    $api_url = get_option('cf7_lexhoy_api_url', 'https://apibacklexhoy.onrender.com/api/leads');
    echo '<input type="text" name="cf7_lexhoy_api_url" value="' . esc_attr($api_url) . '" class="regular-text" />';
    echo '<p class="description">URL completa del endpoint de la API (ej: https://apibacklexhoy.onrender.com/api/leads)</p>';
}

/**
 * Activación del plugin
 */
register_activation_hook(__FILE__, 'cf7_lexhoy_activate');

function cf7_lexhoy_activate() {
    if (!get_option('cf7_lexhoy_api_url')) {
        add_option('cf7_lexhoy_api_url', 'https://apibacklexhoy.onrender.com/api/leads');
    }
}
