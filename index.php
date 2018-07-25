<?php 
/**
 * Plugin Name: Instagram import
 * Plugin URI: http://www.igorkiselev.com/wp-plugins/import_instagram
 * Description: Plug-in imports images from instagram
 * Version: 0.0.1
 * Author: Igor Kiselev
 * Author URI: http://www.igorkiselev.com/
 * Copyright: Igor Kiselev
 * License: A "JustBeNice" license name e.g. GPL2.
 */


if (! defined('ABSPATH')) {
    exit;
}

$oauth = 'https://api.instagram.com/';

class instagramImport{
	private $oauth = 'https://api.instagram.com/';

public function get_app_code($f){



    session_start();

    $api_query = $this->oauth.'oauth/access_token';
    
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $api_query);

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

    curl_setopt($ch, CURLOPT_TIMEOUT, 20);

    curl_setopt($ch, CURLOPT_POST, true);

    curl_setopt($ch, CURLOPT_POSTFIELDS, $f);

    $result = curl_exec($ch);

    curl_close($ch);

    $result = json_decode($result);
    
    if (!isset($result->code)) {
        update_option('instagram_app_access_token', $result->access_token);
    }
}

public function error_allow_url_fopen(){
    if (ini_get('allow_url_fopen')) {
        return false;
    } else {
        return true;
    }
}
public function preview_app_object(){
  
    
    $a = get_option('instagram_app_access_token');
    
    $string = $this->oauth.'v1/users/self/media/recent?access_token='.$a;
    
    if ($a) {
        $result = json_decode(
            file_get_contents($string)
        );
        
        if ($result) {
            echo print_r($result->data, false);
        } else {
            if ($this->error_allow_url_fopen()) {
                _e('<span style="color:red">You need to turn on "allow_url_fopen" in your hosting php settings.</span>', 'instagram');
            }
        }
    }
}

private function wp_insert_post($item){
    return wp_insert_post(
        array(
            'post_type' => 'instagram',
            'post_title'    => $item->caption->text,
            'post_name'    => $item->id,
            'post_status'   => 'publish',
            'post_author'   => 1,
            'post_date' => date('Y-m-d h:i:s', $item->created_time)
        )
    );
}
private function wp_insert_postmeta($item, $post_id){
    add_post_meta($post_id, 'link', $item->link, false);
}
private function wp_insert_attachment($images, $id, $post_id, $featured){
    if ($images->standard_resolution) {
        $image_id = media_sideload_image($images->standard_resolution->url, $post_id, $id, 'id');
    
        if ($featured) {
            set_post_thumbnail($post_id, $image_id);
        }
    }
}

public function update_app_data(){


    $a = get_option('instagram_app_access_token');

    if ($a) {
        $result = json_decode(
            file_get_contents($this->oauth.'v1/users/self/media/recent?access_token='.$a)
        );
    
        if ($result) {
            foreach ($result->data as &$item) {
                global $wpdb;
            
                $table = $wpdb->prefix."posts";
            
                $query = $wpdb->get_row("SELECT post_name FROM $table WHERE post_name='".$item->id."'", 'ARRAY_A');

                if (!$query) {
                    $post_id = $this->wp_insert_post($item);
            
                    $this->wp_insert_postmeta($item, $post_id);
            
                    if ($item->type == "image" || $item->type == "video") {
                        $this->wp_insert_attachment($item->images, $item->id, $post_id, true);
                    } elseif ($item->type == "carousel") {
                        $i = false;
                
                        foreach ($item->carousel_media as &$carousel_item) {
                            $attach = !$i ? true : false;
                    
                            $this->wp_insert_attachment($carousel_item->images, $item->id, $post_id, $attach);
                    
                            $i = true;
                        }
                    }
                }
            }
        
            if (get_option('instagram_plugin_notify')) {
                mail(get_option('admin_email'), __('Instagram', 'instagram'), __('Data was updated.', 'instagram'));
            }
        }
    }
}
}

$instagramImport = new instagramImport();

add_action('plugins_loaded', function () {
    add_action('init', function () {
        register_post_type(
            'instagram',
            array(
            'label' => __('Instagram', 'instagram'),
            'public' => false,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,

            'query_var' => true,
            'exclude_from_search' => true,
            'rewrite' => array('slug' => 'instagram'),
            'capability_type' => 'post',

            'menu_position' => 10,
            'menu_icon' => 'dashicons-images-alt',

            'supports' => array('title','thumbnail','custom-fields'),
            'description' => __('Images from my instagram', 'instagram'),
            )
        );
    });

    add_action('admin_init', function () {
        global $oauth;
		global $instagramImport;
        
        $c = get_option('instagram_app_client_id');
        $s = get_option('instagram_app_client_secret');
        $r = get_option('instagram_app_redirect_uri');
        $a = get_option('instagram_app_access_token');
        
        if ($c && $s && $r) {
            $f = array( 'client_id' => $c, 'client_secret' => $s, 'grant_type' => 'authorization_code', 'redirect_uri'  => $r );
            $code = $oauth.'oauth/authorize/?client_id='.$f['client_id'].'&redirect_uri='.$f['redirect_uri'].'&response_type=code';
            if (!$a) {
                if (!isset($_GET["code"])) {
                    header(sprintf('Location: %s', $code));
                    exit;
                } else {
                    $f['code'] = $_GET["code"];
                    $instagramImport->get_app_code($f);
                }
            }
        }
        
        
        register_setting('instagram_app', 'instagram_app_client_id');
        register_setting('instagram_app', 'instagram_app_client_secret');
        register_setting('instagram_app', 'instagram_app_redirect_uri');
        register_setting('instagram_app', 'instagram_app_access_token');
    
        register_setting('instagram_plugin', 'instagram_plugin_cron');
        register_setting('instagram_plugin', 'instagram_plugin_notify');
    });

    add_action('admin_menu', function () {
        add_options_page(__('Instagram', 'instagram'), __('Instagram', 'instagram'), 'manage_options', 'instagram_app', '_options_page');
    });
    
	


function _option_field($t =  null, $type = 'html', $p =  null, $c = null, $n = null, $d = null, $v = null)
    {
        if (!$v) {
            $v = get_option($n);
        }
    
        $h = ($type == 'hidden') ? true : false;
    
        $result = "";
    
        $result .= (!$h) ? "<tr><th scope=\"row\">" : '';
    
        if ($type == 'html') {
            $result .= __($t);
        } elseif ($type == 'field' || $type == 'checkbox') {
            $result .= "<label for=\"$n\">".__($t)."</label>";
        }
		
    	$result .= (!$h) ? "</th><td>" : '';
		
        
    
        if ($type == 'html') {
            $result .= "<p>$p<p>";
        } elseif ($type == 'field') {
            $result .= "<p><input id=\"$n\" name=\"$n\" type=\"text\" value=\"$v\" class=\"$c\" placeholder=\"$p\" /><p>";
        } elseif ($type == 'hidden') {
            $result .= "<input id=\"$n\" name=\"$n\" type=\"hidden\" value=\"$v\" />";
        } elseif ($type == 'checkbox') {
            $result .= "<input id=\"$n\" name=\"$n\" type=\"checkbox\" value=\"1\"  ".checked('1', $v, false)." /> ".$p;
        }
    
        $result .= ($d) ? "<p class=\"description\">".__($d)."</p>" : "";
		
		$result .= (!$h) ? "</td></tr>" : '';
        
    
        echo $result;
    }


function _options_page()
    {
        global $oauth;
		global $instagramImport;
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'instagram'));
        }
    
        
        $c = get_option('instagram_app_client_id');
        $s = get_option('instagram_app_client_secret');
        $r = get_option('instagram_app_redirect_uri');
        $a = get_option('instagram_app_access_token'); ?><div class="wrap">
			<h2><?php _e('Import images from instagram account to attachments', 'instagram'); ?></h2>
			<form method="post" action="options.php">
				<?php settings_fields('instagram_app'); ?>
				<table class="form-table">
					<?php

                        
                        if (!$c || !$s || !$a) {
                            _option_field(
                                __('Lets start', 'instagram'),
                                'html',
                                sprintf(__('<a href="https://www.instagram.com/developer/clients/manage/" class="button" target="_blank">%s</a>', 'instagram'), __('Manage Client', 'instagram'))
                            );
                        }
                
        /* Client ID; */
        $title = __('Client ID', 'instagram');
        if ($a) {
            _option_field($title, 'html', sprintf(__('<strong>%s</strong><span class="dashicons dashicons-yes" style="color:green"></span>', 'instagram'), $c));
        } else {
            _option_field($title, 'field', __('enter client ID from app', 'instagram'), 'code regular-text', 'instagram_app_client_id');
        }
                    
        /* Client Secret; */
        $title = __('Client Secret', 'instagram');
        if ($a) {
            _option_field($title, 'html', sprintf(__('<strong>%s</strong><span class="dashicons dashicons-yes" style="color:green"></span>', 'instagram'), $s));
        } else {
            _option_field($title, 'field', __('enter client secret from app', 'instagram'), 'code regular-text', 'instagram_app_client_secret');
        }
                    
        /* Callback Uri; */
        $g = 'http://'.$_SERVER["HTTP_HOST"].$_SERVER["PHP_SELF"].'?page=instagram_app';
        $title = __('Authorization callback URI', 'instagram');
        if ($a) {
            if ($r != $g) {
                _option_field(
                                    $title,
                                    'html',
                                    sprintf(__('<strong>%s</strong><span class="dashicons dashicons-no-alt" style="color:orange"></span>', 'instagram'), $r),
                                    '',
                                    '',
                                    sprintf(__('You have an access token, but the calback URI is different.<br /><small><b>%s</b> — the correct one.</small>', 'instagram'), $g)
                                );
            } else {
                _option_field(
                                    $title,
                                    'html',
                                    sprintf(__('<strong>%s</strong><span class="dashicons dashicons-yes" style="color:green"></span>', 'instagram'), $r)
                                );
            }
        } else {
            $r = (empty($r) || $r != $g) ? $g : $r;
            _option_field(
                                $title,
                                'html',
                                sprintf(__('<strong>%s</strong>', 'instagram'), $r),
                                '',
                                '',
                                __('We need the instagram APP redirect to this page after it recieves the code. Set this link in your APP api settings.', 'instagram')
                            );
                            
            _option_field('', 'hidden', '', '', 'instagram_app_redirect_uri', '', $g);
        }
                    
        /* Access Token */
        if ($a) {
            _option_field(
                                __('Access token', 'instagram'),
                                'html',
                                sprintf(__('<strong>%s</strong><span class="dashicons dashicons-yes" style="color:green"></span>', 'instagram'), $a),
                                '',
                                '',
                                __('Access token that your application created.', 'instagram')
                            );
        }
                    
        do_settings_sections("theme-options");
                    
        /* Revoke Token */
        if ($a) {
            _option_field('', 'html', get_submit_button(__('Revoke', 'instagram'), 'secondary delete', '', false));
        }
        /* Fetch data button*/
        if ($a) {
            _option_field(
                                '',
                                'html',
                                sprintf(
                                __('<a id="ajax_instagram_update" href="#update" class="button-large button-primary button_ajax">%s</a>', 'instagram'),
                                __('Fetch data now', 'instagram')
                            )
                            );
        }; ?>
				</table>
				<?php
                    if (!$a) {
                        submit_button();
                    } ?>
			</form>
			<?php if ($a) {
                        ?>
			<form method="post" action="options.php">
				<?php settings_fields('instagram_plugin'); ?>
				<table class="form-table">
					<?php
                        _option_field(
                            __('Autoupdate', 'instagram'),
                            'checkbox',
                            __('Cron task', 'instagram'),
                            '',
                            'instagram_plugin_cron',
                            __('A cron task will call the update function each hour.', 'instagram')
                        );
                        _option_field(
                            '',
                            'checkbox',
                            sprintf(__('Email notification (%s)', 'instagram'), get_option('admin_email')),
                            '',
                            'instagram_plugin_notify',
                            __('Send hourly an email about instagram import updates.', 'instagram')
                        );
                        _option_field(
                            'Debug',
                            'html',
                            sprintf(
                                __('<div id="instagram_preview"><a id="ajax_instagram_preview" href="#preview" class="button-large button button_ajax">%s</a><pre></pre></div>', 'instagram'),
                                __('Fetch object preview', 'instagram')
                            )
                        );
                        if ($instagramImport->error_allow_url_fopen()) {
                            _option_field(__('System check', 'instagram'), 'html', __('<span style="color:red"><span class="dashicons dashicons-warning"></span> You need to turn on "allow_url_fopen" in your hosting php settings.</span>', 'instagram'));
                        } ?>
					
				</table>
				<?php do_settings_sections("theme-options"); ?>
				<?php submit_button(); ?>
			</form>
			<?php
                    } ?>
			<p>
				<?php _e('Plugin to import instagram images. Developed by Igor Kiselev, <a href="//www.justbenice.ru/">Just Be Nice</a>', 'instagram'); ?>
			</p>

	</div>
	
<?php
    }

    if (get_option('instagram_plugin_cron')) {
		
		register_activation_hook(__FILE__, function(){
		    if (! wp_next_scheduled ( 'justbenice_instagram_hourly_event' )) {
			wp_schedule_event(time(), 'hourly', 'justbenice_instagram_hourly_event');
		    }
		});

		add_action('justbenice_instagram_hourly_event', function(){
			global $instagramImport;
			
	        $instagramImport->update_app_data();
			
		});

		register_deactivation_hook(__FILE__, function(){
			wp_clear_scheduled_hook('justbenice_instagram_hourly_event');
		});
	
    }

    add_action('admin_enqueue_scripts', function () {
        wp_enqueue_style('custom_wp_admin_css', plugins_url('stylesheet.css', __FILE__));
        wp_enqueue_script('custom_wp_admin_js', plugins_url('application.js', __FILE__), array('jquery'));
    });
    
    add_action('wp_ajax_instagram_update', function () {
        global $wpdb;
		global $instagramImport;
        $instagramImport->update_app_data();
        wp_die();
    });
    
    add_action('wp_ajax_instagram_preview', function () {
        global $wpdb;
		global $instagramImport;
       	$instagramImport->preview_app_object();
        wp_die();
    });
    
    //require('shortcode/instagram.php');
    
    add_filter('plugin_action_links_' . plugin_basename(__FILE__), function ($links) {
        return array_merge($links, array('<a href="' . admin_url('options-general.php?page=justbenice-instagram') . '">'.__('Settings', 'instagram').'</a>',));
    });
});

?>