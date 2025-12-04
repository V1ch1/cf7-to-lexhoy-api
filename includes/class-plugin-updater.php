<?php
if (!defined('ABSPATH')) {
    exit;
}

class CF7_LexHoy_Updater {
    private $slug;
    private $plugin_data;
    private $username;
    private $repo;
    private $plugin_file;
    private $github_response;
    private $access_token;

    public function __construct($plugin_file, $github_username, $github_repo, $access_token = '') {
        $this->plugin_file = $plugin_file;
        $this->username = $github_username;
        $this->repo = $github_repo;
        $this->access_token = $access_token;
        $this->slug = plugin_basename($plugin_file);
        
        add_filter('pre_set_site_transient_update_plugins', array($this, 'check_update'));
        add_filter('plugins_api', array($this, 'check_info'), 10, 3);
    }

    private function get_repository_info() {
        if (!empty($this->github_response)) {
            return;
        }

        $url = "https://api.github.com/repos/{$this->username}/{$this->repo}/releases/latest";
        
        $args = array(
            'headers' => array(
                'Accept' => 'application/vnd.github.v3+json',
                'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . home_url()
            )
        );

        if (!empty($this->access_token)) {
            $args['headers']['Authorization'] = "token {$this->access_token}";
        }

        $response = wp_remote_get($url, $args);

        if (is_wp_error($response) || 200 !== wp_remote_retrieve_response_code($response)) {
            return;
        }

        $this->github_response = json_decode(wp_remote_retrieve_body($response), true);
    }

    public function check_update($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }

        $this->get_repository_info();

        if (!$this->github_response) {
            return $transient;
        }

        $remote_version = isset($this->github_response['tag_name']) ? $this->github_response['tag_name'] : '';
        
        // Limpiar 'v' del inicio si existe
        $remote_version = ltrim($remote_version, 'v');
        
        $plugin_data = get_plugin_data($this->plugin_file);
        $current_version = $plugin_data['Version'];

        if (version_compare($remote_version, $current_version, '>')) {
            $obj = new stdClass();
            $obj->slug = $this->slug;
            $obj->new_version = $remote_version;
            $obj->url = $this->github_response['html_url'];
            $obj->package = $this->github_response['zipball_url'];
            
            // Asegurar que el paquete tenga autenticación si es privado
            if (!empty($this->access_token)) {
                $obj->package = add_query_arg('access_token', $this->access_token, $obj->package);
            }
            
            $transient->response[$this->slug] = $obj;
        }

        return $transient;
    }

    public function check_info($false, $action, $arg) {
        if (isset($arg->slug) && $arg->slug === $this->slug) {
            $this->get_repository_info();

            if (!$this->github_response) {
                return $false;
            }

            $remote_version = isset($this->github_response['tag_name']) ? $this->github_response['tag_name'] : '';
            $remote_version = ltrim($remote_version, 'v');

            $obj = new stdClass();
            $obj->slug = $this->slug;
            $obj->name = $this->github_response['name'];
            $obj->plugin_name = $this->slug;
            $obj->sections = array(
                'description' => $this->github_response['body']
            );
            $obj->version = $remote_version;
            $obj->download_link = $this->github_response['zipball_url'];
            
            // Asegurar que el paquete tenga autenticación si es privado
            if (!empty($this->access_token)) {
                $obj->download_link = add_query_arg('access_token', $this->access_token, $obj->download_link);
            }

            return $obj;
        }

        return $false;
    }
}
