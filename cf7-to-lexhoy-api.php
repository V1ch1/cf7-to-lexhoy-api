<?php
/**
 * Plugin Name: Contact Form 7 to API - LexHoy
 * Plugin URI: https://lexhoy.com
 * Description: Envía los envíos de Contact Form 7 a la API de LexHoy para procesamiento de leads
 * Version: 2.0.1
 * Author: LexHoy
 * Author URI: https://lexhoy.com
 * Text Domain: cf7-to-lexhoy-api
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Definir constantes
define('CF7_LEXHOY_API_VERSION', '2.0.1');
define('CF7_LEXHOY_WEBHOOK_SECRET', 'a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6q7r8s9t0u1v2w3x4y5z6');

// Hook para capturar envíos de Contact Form 7
add_action('wpcf7_mail_sent', 'cf7_lexhoy_send_to_api', 10, 1);

function cf7_lexhoy_send_to_api($contact_form) {
    if (!class_exists('WPCF7_Submission')) {
        return;
    }

    try {
        // URL de Next.js en Vercel
        $api_url = get_option('cf7_lexhoy_api_url', 'https://despachos.lexhoy.com/api/webhooks/lexhoy');
        
        $submission = WPCF7_Submission::get_instance();
        
        if (!$submission) {
            return;
        }
        
        $posted_data = $submission->get_posted_data();
        
        // Log para debugging - ver TODOS los campos recibidos
        error_log('CF7 DEBUG - Campos recibidos: ' . print_r(array_keys($posted_data), true));
        error_log('CF7 DEBUG - Valores: ' . print_r($posted_data, true));
        
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
        $terms_keys = ['acceptance-terms', 'terminos', 'aceptacion', 'condiciones', 'gdpr', 'checkbox-427'];
        
        // 🆕 Añadir claves para localidad y provincia
        $localidad_keys = ['localidad', 'ciudad', 'city', 'locality'];
        $provincia_keys = ['provincia', 'province', 'state'];
        
        $current_url = home_url($_SERVER['REQUEST_URI']);
        $page_title = function_exists('get_the_title') ? get_the_title() : 'Formulario Web';
        
        // Preparar datos (formato compatible con Next.js)
        $data = array(
            'nombre' => sanitize_text_field($find_value($name_keys, $posted_data)),
            'correo' => sanitize_email($find_value($email_keys, $posted_data)),
            'telefono' => sanitize_text_field($find_value($phone_keys, $posted_data)),
            'cuerpoMensaje' => sanitize_textarea_field($find_value($message_keys, $posted_data)),
            'urlPagina' => $current_url,
            'tituloPost' => $page_title,
            'acepta_terminos' => ($find_value($terms_keys, $posted_data) ? true : false),
            'fuente' => 'wordpress-cf7'
        );
        
        // 🆕 Añadir localidad y provincia si existen
        $localidad = $find_value($localidad_keys, $posted_data);
        $provincia = $find_value($provincia_keys, $posted_data);
        
        if (!empty($localidad)) {
            $data['ciudad'] = sanitize_text_field($localidad);
        }
        
        if (!empty($provincia)) {
            $data['provincia'] = sanitize_text_field($provincia);
        }
        
        // Si no se encontró mensaje, intentar concatenar otros campos no mapeados
        if (empty($data['cuerpoMensaje'])) {
            $extra_fields = [];
            $mapped_keys = array_merge(
                $name_keys, 
                $email_keys, 
                $phone_keys, 
                $message_keys, 
                $terms_keys,
                $localidad_keys,
                $provincia_keys
            );
            
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

        error_log('CF7 to LexHoy API v2.0.1: Enviando a Next.js: ' . print_r($data, true));
        
        // Enviar con header de seguridad
        // 🆕 Cambiar a blocking = true para mejor feedback al usuario
        $response = wp_remote_post($api_url, array(
            'method' => 'POST',
            'timeout' => 30, // Reducido de 45 a 30 segundos
            'blocking' => true, // Cambiado a true para esperar respuesta
            'headers' => array(
                'Content-Type' => 'application/json',
                'x-webhook-secret' => CF7_LEXHOY_WEBHOOK_SECRET,
            ),
            'body' => json_encode($data),
        ));
        
        if (is_wp_error($response)) {
            error_log('CF7 to LexHoy API Error: ' . $response->get_error_message());
        } else {
            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);
            error_log('CF7 to LexHoy API: Respuesta ' . $response_code . ' - ' . $response_body);
        }
    } catch (Exception $e) {
        error_log('CF7 to LexHoy API Exception: ' . $e->getMessage());
    }
}

// Página de configuración en admin
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

function cf7_lexhoy_settings_page() {
    ?>
    <div class="wrap">
        <h1>Contact Form 7 to LexHoy API - Configuración v2.0.1</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('cf7_lexhoy_api_settings');
            do_settings_sections('cf7-lexhoy-api');
            submit_button();
            ?>
        </form>
        
        <div class="card" style="max-width: 800px; margin-top: 20px;">
            <h2>✅ Migrado a Next.js (Vercel)</h2>
            <p><strong>Versión:</strong> 2.0.1</p>
            <p><strong>Estado:</strong> ✅ Activo - Enviando a Next.js</p>
            <h3>URL actual de la API:</h3>
            <p><code><?php echo esc_html(get_option('cf7_lexhoy_api_url', 'https://despachos.lexhoy.com/api/webhooks/lexhoy')); ?></code></p>
            <p><em>Los leads ahora se procesan con IA en Vercel (más rápido y gratuito)</em></p>
            
            <h3>🔍 Debugging</h3>
            <p>Para ver los logs de envío, revisa el archivo <code>wp-content/debug.log</code></p>
            <p>Asegúrate de tener activado el modo debug en <code>wp-config.php</code>:</p>
            <pre>define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);</pre>
        </div>
    </div>
    <?php
}

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
    $api_url = get_option('cf7_lexhoy_api_url', 'https://despachos.lexhoy.com/api/webhooks/lexhoy');
    echo '<input type="text" name="cf7_lexhoy_api_url" value="' . esc_attr($api_url) . '" class="regular-text" />';
    echo '<p class="description">URL del webhook de Next.js (ej: https://despachos.lexhoy.com/api/webhooks/lexhoy)</p>';
}

register_activation_hook(__FILE__, 'cf7_lexhoy_activate');

function cf7_lexhoy_activate() {
    // Actualizar a la nueva URL por defecto
    update_option('cf7_lexhoy_api_url', 'https://despachos.lexhoy.com/api/webhooks/lexhoy');
}
