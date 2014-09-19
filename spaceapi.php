<?php
/*
Plugin Name: Spaceapi
Plugin URI: http://fholzhauer.de
Description: Generate a space api file
Version: 0.1.2
Author: Florian Holzhauer
License: GPL
*/

/*
 * This plugin is pretty much an ugly proof of concept.
 *
 * The basic idea is to add direct rendering and validation of the settings from the json api spec, which would be way nicer and somewhat useful
 *
 */

if (!function_exists('add_action')) {
    //Direct access, no WP call
    header('HTTP/1.1 403 Forbidden');
    die();
}

if (!class_exists('fh_wpplugin_spaceapi')) {

    define('FH_WPPLUGIN_SPACEAPI_FORMPREFIX', 'fh_wpplugin_spaceapi');

    class fh_wpplugin_spaceapi
    {

        var $base = false;
        var $folder = false;

        const VERSION = '0.1.2';
        /**
         * Identifier-Prefix for Forms and Settings
         * @var string
         */
        const PREFIX = FH_WPPLUGIN_SPACEAPI_FORMPREFIX;


        private $textfields = array(
            'name' => 'The name of your space <b>mandatory</b>',
            'logo' => 'URL to your space logo <b>mandatory</b>',
            'url' => 'URL to your space website *',
            'location' => array(
                'address' => 'The postal address of your space',
                'lat' => 'Latitude (degree with decimal places, positive=N, negative=S) *',
                'lon' => 'Longitude (degree with decimal places, positive=W, negative=E) *',
            ),
            'contact' => array(
                'phone' => 'Phone number, including country code with a leading plus sign.',
                'sip' => 'URI for Voice-over-IP via SIP.',
                'irc' => 'URL of the IRC channel, in the form irc://example.org/#channelname',
                'twitter' => 'Twitter handle, with leading @',
                'facebook' => 'Facebook',
                'identica' => 'Identi.ca or StatusNet account, in the form yourspace@example.org',
                'foursquare' => 'Foursquare ID, in the form 4d8a9114d85f3704eab301dc.',
                'email' => 'E-mail address for contacting your space. If this is a mailing list consider to use the contact/ml field.',
                'ml' => 'The e-mail address of your mailing list.',
                'jabber' => 'A public Jabber/XMPP multi-user chatroom in the form chatroom@conference.example.net',
                'issue' => 'A seperate email address for issue reports (see the issue_report_channels field).',
            )
        );

        public function __construct()
        {
            $this->base = plugin_basename(__FILE__);
            $this->folder = dirname($this->base);


            add_action('init', array(&$this, 'init'));
            add_action('generate_rewrite_rules', array(&$this, 'generateRewriteRules'));
            add_filter('query_vars', array(&$this, 'queryVars'));
            add_action('parse_request', array(&$this, 'parseRequest'));


            add_action('plugins_loaded', array(&$this, 'onStart'));
            add_action('admin_menu', array(&$this, 'addAdminMenu'));

            //WARNING: Prio has to be set, or it wont work - see http://stackoverflow.com/questions/1580378/plugin-action-links-not-working-in-wordpress-2-8
            add_filter('plugin_action_links', array(&$this, 'addActionLinks'), 10, 2);
        }

        public function init()
        {
            global $wp_rewrite;
            $wp_rewrite->flush_rules();
        }

        public function generateRewriteRules()
        {
            //http://stackoverflow.com/questions/13140182/wordpress-wp-rewrite-rules
            global $wp_rewrite;
            $new_rules = array(
                '(spaceapi.json)?$' => 'index.php?spaceapi=show'
            );
            // Always add your rules to the top, to make sure your rules have priority
            $wp_rewrite->rules = $new_rules + $wp_rewrite->rules;
        }

        /*
         * Renders Admin interface, stores settings
         * @todo i18n
         */
        public function adminInterface()
        {

            if (isset($_POST['submitted'])) {
                foreach ($_POST as $key => $value) {
                    $l = strlen(self::PREFIX . '_s_');
                    if (substr($key, 0, $l) == self::PREFIX . '_s_') {
                        update_option($key, trim($value));
                    }
                }
            }

            $storedValues = array();
            foreach ($this->textfields as $key => $value) {
                if (is_array($value)) {
                    foreach ($value as $innerKey => $innerValue) {
                        $id = $key . '_' . $innerKey;
                        $storedValues[$id] = get_option(self::PREFIX . '_s_' . $id);
                    }
                } else {
                    $storedValues[$key] = get_option(self::PREFIX . '_s_' . $key);
                }
            }

            $inner = $this->innerAdminInterfaceHtml($storedValues);
            echo $this->adminInterfaceHtml($inner, 'Space Api Config');
        }

        /**
         * Inits Admin-Hooks, adds Settings-Link in Backend
         */
        public function addAdminMenu()
        {
            add_options_page(
                'Space Api Options',
                'Space Api',
                'manage_options',
                self::PREFIX,
                array(&$this, 'adminInterface')
            );
        }

        public function addActionLinks($action_links, $plugin_file)
        {
            $this_file = basename(__FILE__);

            if (substr($plugin_file, -strlen($this_file)) == $this_file) {
                $new_action_links = array(
                    "<a href='options-general.php?page=" . self::PREFIX . "'>Settings</a>"
                );
                return $new_action_links;
            }
            return $action_links;
        }

        public function onStart()
        {
            if (is_admin()) {
                if (get_option(self::PREFIX . 'warnings')) {
                    add_action(
                        'admin_notices',
                        create_function(
                            '',
                            'echo \'<div id="message" class="error"><p>' . get_option(
                                self::PREFIX . 'warnings'
                            ) . '</p></div>\';'
                        )
                    );
                }
            }
        }

        public function parseRequest($wp)
        {
            if (array_key_exists('spaceapi', $wp->query_vars)
                && $wp->query_vars['spaceapi'] == 'show'
            ) {
                header('Content-Type: application/json');
                $data = array();
                foreach ($this->textfields as $key => $value) {
                    if (is_array($value)) {
                        $tmp = array();
                        foreach ($value as $innerKey => $innerValue) {
                            $id = $key . '_' . $innerKey;
                            $databaseValue = get_option(self::PREFIX . '_s_' . $id);
                            if (!empty($databaseValue)) {
                                $tmp[$innerKey] = $databaseValue;
                            }
                        }
                        $data[$key] = $tmp;
                    } else {
                        $databaseValue = get_option(self::PREFIX . '_s_' . $key);
                        if (!empty($databaseValue)) {
                            $data[$key] = $databaseValue;
                        }
                    }
                }
                $data['api'] = '0.12';
                $data['open'] = null;
                $data = apply_filters('spaceapi_data_result',$data);
                echo json_encode($data);
                die();
            }
        }

        public function queryVars()
        {
            return array('spaceapi');
        }

        public function adminInterfaceHtml(
            $inner = '',
            $title = 'Admin Menu',
            $submitbutton = 'Save &raquo;'
        ) {

            $return = <<<EOF
<div class="wrap">
<h2>$title</h2>
<form id="settings" action="" method="post">
<p><b>Warning: No field validation!</b></p>
<p>This plugin is <b>deprecated</b>. Please consider disabling it, and to use the <a href="https://wordpress.org/plugins/hackerspace/">hackerspace plugin</a> instead.</p>
$inner
<p class="submit">
	<input type="hidden" name="submitted" />
	<input type="submit" name="Submit" class="button-primary" value="$submitbutton" />
</p>
</form>
</div>
EOF;
            return $return;
        }

        public function innerAdminInterfaceHtml(
            $data = array()
        ) {

            /*
             * This is a very stupid initial version to get the plugin started.
             * The idea here is to remove all those hard coded fields and do a direct
             * rendering of the schema.json files - which is way cooler.
             *
             * Since this is only a temporary solution, I did not put too much effort in it,
             * hence the "array" fields are not supported.
             */
            $str = '<table class="form-table"><tbody>';

            //issue report channel

            foreach ($this->textfields as $name => $descr) {
                if (is_array($descr)) {
                    continue;
                }
                $str .= $this->trStringInput(
                    $descr,
                    $name,
                    array_key_exists($name, $data) ? $data[$name] : ''
                );
            }

            $str .= "</tbody></table>\n<h3>Contact:</h3>\n<table class='form-table'><tbody>\n";

            foreach ($this->textfields['contact'] as $name => $descr) {
                $str .= $this->trStringInput(
                    $descr,
                    'contact_' . $name,
                    array_key_exists('contact_' . $name, $data) ? $data['contact_' . $name] : ''
                );
            }
            $str .= "</tbody></table>\n<h3>Location:</h3>\n<table class='form-table'><tbody>\n";

            foreach ($this->textfields['location'] as $name => $descr) {
                $str .= $this->trStringInput(
                    $descr,
                    'location_' . $name,
                    array_key_exists('location_' . $name, $data) ? $data['location_' . $name] : ''
                );
            }

            $str .= '</tbody></table>';

            return $str;

        }

        public function trStringInput(
            $description,
            $name,
            $value
        ) {
            $str = '<tr><th scope="row" width="33%"><label for="' . FH_WPPLUGIN_SPACEAPI_FORMPREFIX . '_s_' . $name . '">';
            $pseudotitle = explode('_', $name);
            //oh boy, this is sooo ugly :)
            foreach ($pseudotitle as $t) {
                $str .= ucfirst($t) . ' ';
            }
            $str = substr($str, 0, -1);
            $str .= ':';
            $str .= '</label></th>';
            $str .= '<td><input name="' . FH_WPPLUGIN_SPACEAPI_FORMPREFIX . '_s_' . $name . '" value="' . $value . '" />';
            $str .= '<p class="need-' . $name . ' description">' . $description . '</p></td></tr>';
            $str .= "\n";
            return $str;
        }


    }
}

/**
 * Global Init Funktion, called by wordpress on init.
 * All other hooks and settings are set in the constructor of the object.
 */
function fh_wpplugin_spaceapi_init()
{
    new fh_wpplugin_spaceapi();
}

add_action('plugins_loaded', 'fh_wpplugin_spaceapi_init');
