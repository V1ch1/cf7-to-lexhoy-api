<?php
/**
 * Plugin Name: Contact Form 7 to API - LexHoy
 * Plugin URI: https://lexhoy.com
 * Description: Envía los envíos de Contact Form 7 a la API de LexHoy para procesamiento de leads
 * Version: 2.0.2
 * Author: LexHoy
 * Author URI: https://lexhoy.com
 * Text Domain: cf7-to-lexhoy-api
 */

if (!defined('ABSPATH')) exit;

define('CF7_LEXHOY_API_VERSION', '2.0.2');
define('CF7_LEXHOY_WEBHOOK_SECRET', 'a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6q7r8s9t0u1v2w3x4y5z6');

/**
 * Inicializar actualizador desde GitHub
 */
add_action('init', 'cf7_lexhoy_init_updater');

function cf7_lexhoy_init_updater() {
    if (is_admin()) {
        $updater_path = plugin_dir_path(__FILE__) . 'includes/class-plugin-updater.php';
        
        if (file_exists($updater_path)) {
            require_once $updater_path;
            
            $github_user = 'V1ch1'; 
            $github_repo = 'cf7-to-lexhoy-api';
            $access_token = '';
            
            new CF7_LexHoy_Updater(
                __FILE__, 
                $github_user, 
                $github_repo,
                $access_token
            );
        }
    }
}

add_action('wpcf7_mail_sent', 'cf7_lexhoy_send_to_api', 10, 1);

function cf7_lexhoy_send_to_api($contact_form) {
    if (!class_exists('WPCF7_Submission')) return;

    try {
        $api_url = get_option('cf7_lexhoy_api_url', 'https://despachos.lexhoy.com/api/webhooks/lexhoy');
        $submission = WPCF7_Submission::get_instance();
        
        if (!$submission) return;
        
        $posted_data = $submission->get_posted_data();
        
        $find_value = function($keys, $data) {
            foreach ($keys as $key) {
                if (isset($data[$key]) && !empty($data[$key])) return $data[$key];
            }
            return '';
        };

        $name_keys = ['your-name', 'nombre', 'name', 'nombre-completo', 'usuario'];
        $email_keys = ['your-email', 'email', 'correo', 'correo-electronico'];
        $phone_keys = ['your-phone', 'tel', 'telefono', 'phone', 'movil'];
        $message_keys = ['your-message', 'mensaje', 'message', 'cuerpo', 'comentarios'];
        $localidad_keys = ['localidad', 'ciudad', 'city', 'locality', 'municipio'];
        $provincia_keys = ['provincia', 'province', 'state', 'region'];

        $current_url = home_url($_SERVER['REQUEST_URI']);
        $page_title = function_exists('get_the_title') ? get_the_title() : 'Formulario Web';
        
        $data = array(
            'nombre' => sanitize_text_field($find_value($name_keys, $posted_data)),
            'correo' => sanitize_email($find_value($email_keys, $posted_data)),
            'telefono' => sanitize_text_field($find_value($phone_keys, $posted_data)),
            'cuerpoMensaje' => sanitize_textarea_field($find_value($message_keys, $posted_data)),
            'urlPagina' => $current_url,
            'tituloPost' => $page_title,
            'acepta_terminos' => true,
            'fuente' => 'wordpress-cf7'
        );
        
        $localidad = sanitize_text_field($find_value($localidad_keys, $posted_data));
        $provincia = sanitize_text_field($find_value($provincia_keys, $posted_data));

        if (!empty($localidad)) {
            $data['ciudad'] = $localidad;
        }
        if (!empty($provincia)) {
            $data['provincia'] = $provincia;
        }
        
        // Log para debugging (solo en archivo debug.log)
        error_log('CF7 to LexHoy API v2.0.2: Enviando: ' . print_r($data, true));
        
        wp_remote_post($api_url, array(
            'method' => 'POST',
            'timeout' => 5,
            'blocking' => false,
            'headers' => array(
                'Content-Type' => 'application/json',
                'x-webhook-secret' => CF7_LEXHOY_WEBHOOK_SECRET,
            ),
            'body' => json_encode($data),
        ));
        
    } catch (Exception $e) {
        error_log('CF7 to LexHoy API Error: ' . $e->getMessage());
    }
}
?>
