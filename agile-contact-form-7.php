<?php

/**
 * @package AgileCRM_ContactForm7
 * @version 1.6
 */
/*
 * Plugin Name: Agile CRM Contact Form 7 Forms
 * Plugin URI: https://www.agilecrm.com/agile-contact-form-7
 * Description: Agile CRM integration plugin for contact forms (contact form7). Sync form entries to Agile easily.
 * Author: Agile CRM Team
 * Author URI: https://www.agilecrm.com/
 * Version: 1.6
 * Requires at least: 4.0
 * Tested up to: 5.5.1
 */

defined('ABSPATH') or die('Plugin file cannot be accessed directly.');

if (!class_exists('AgileCF7Addon')) {

    class AgileCF7Addon
    {

        protected $tag = 'agile-cf7-addon';
        private $account_settings_tab = 'account';
        private $form_settings_tab = 'form';
        private $plugin_settings_tabs = array();
        protected $name = 'Contact Form 7 Agile CRM Add-On';
        protected $version = '1.0';

        function __construct()
        {
            //register actions or hooks
            add_action('init', array(&$this, 'start_session'));
            add_action('wp_footer', array(&$this, 'set_email'), 98765);
            
            add_action('admin_init', array(&$this, 'admin_init'));
            add_action('admin_menu', array(&$this, 'add_menu'));

            add_action('wpcf7_before_send_mail', array(&$this, 'sync_contact_form_entries_to_agile'),10,9999);           
            add_action('wp_ajax_agilecrm_cf7_load_fields', array(&$this, 'load_form_fields'));
            add_action('wp_ajax_agilecrm_cf7_map_fields', array(&$this, 'map_form_fields'));
            
        }

        /**
         * Start PHP session if not started earlier
         */
        public function start_session()
        {
            if (!session_id()) {
                session_start();
            }
        }

        /**
         * hook into WP's admin_init action hook
         */
        public function admin_init()
        {
            // Set up the settings for this plugin
            $this->init_settings();
            $this->plugin_settings_tabs[$this->account_settings_tab] = 'Account Details';
            $this->plugin_settings_tabs[$this->form_settings_tab] = 'Form Settings';
        }

        /**
         * Initialize some custom settings
         */
        public function init_settings()
        {
            // register the settings for this plugin
            register_setting($this->tag . '-settings-group', 'agilecrm_cf7_domain');
            register_setting($this->tag . '-settings-group', 'agilecrm_cf7_admin_email');
            register_setting($this->tag . '-settings-group', 'agilecrm_cf7_api_key');

            register_setting($this->tag . '-settings-group1', 'agilecrm_cf7_form_map');
            register_setting($this->tag . '-settings-group2', 'agilecrm_cf7_contact_fields');
            register_setting($this->tag . '-settings-group3', 'agilecrm_cf7_mapped_forms');

            add_settings_section($this->tag . '-section-one', '', '', $this->tag);
        }

        /**
         * add a menu
         */
        public function add_menu()
        {
            add_options_page('Settings-' . $this->name, 'Agile Contact Form 7 Forms', 'manage_options', $this->tag, array(&$this, 'plugin_settings_page'));
        }

        /**
         * Generate plugin setting tabs
         */
        public function plugin_settings_tabs()
        {
            $current_tab = (isset($_GET['tab']) && isset($this->plugin_settings_tabs[$_GET['tab']])) ? $_GET['tab'] : $this->account_settings_tab;

            echo '<h2 class="nav-tab-wrapper">';
            foreach ($this->plugin_settings_tabs as $tab_key => $tab_caption) {
                $active = $current_tab == $tab_key ? 'nav-tab-active' : '';
                echo '<a class="nav-tab ' . $active . '" href="?page=' . $this->tag . '&tab=' . $tab_key . '">' . $tab_caption . '</a>';
            }
            echo '</h2>';
        }

        /**
         * Menu Callback
         */
        public function plugin_settings_page()
        {
            if (!current_user_can('manage_options')) {
                wp_die(__('You do not have sufficient permissions to access this page.'));
            }

            // Render the settings template based on the tab selected
            $current_tab = (isset($_GET['tab']) && isset($this->plugin_settings_tabs[$_GET['tab']])) ? $_GET['tab'] : $this->account_settings_tab;
            include(sprintf("%s/templates/" . $current_tab . "-tab.php", dirname(__FILE__)));
        }

        /**
         * Load form fields related to form id through Ajax
         */
        public function load_form_fields()
        {
            global $wpdb;
            $formId = $_POST['formid'];        

            $cf7form = get_post($formId);

            $form_post_content = $cf7form->post_content;        

            preg_match_all( '/\[[a-z]*\S [a-z0-9]*.[a-z0-9].*\]/', $form_post_content, $match );

            $formFieldsOptions = '<option value="" selected="selected"></option>';

            $final_field_names = array();
            if (is_array($match)) {
                $i=0;
                foreach ($match[0] as $field_name) {
                    
                    $statement_inside_braces = trim($field_name, "[]");
                    $get_words = explode(" ", $statement_inside_braces);               
                    $space_trim = trim($get_words[1]," ");
                    $final_field_name = trim($get_words[1],"[]");

                    $formFieldsOptions .= '<option value="'.$final_field_name.'">'. $final_field_name .'</option>';
                }

            }
            
            $agileFields = array(
                'first_name' => array('name' => 'First name', 'is_required' => true, 'type' => 'SYSTEM', 'is_address' => false),
                'last_name' => array('name' => 'Last name', 'is_required' => true, 'type' => 'SYSTEM', 'is_address' => false),
                'company' => array('name' => 'Company', 'is_required' => false, 'type' => 'SYSTEM', 'is_address' => false),
                'title' => array('name' => 'Job description', 'is_required' => false, 'type' => 'SYSTEM', 'is_address' => false),
                'tags' => array('name' => 'Tag', 'is_required' => false, 'type' => 'SYSTEM', 'is_address' => false),
                'email' => array('name' => 'Email', 'is_required' => true, 'type' => 'SYSTEM', 'is_address' => false),
                'phone' => array('name' => 'Phone', 'is_required' => false, 'type' => 'SYSTEM', 'is_address' => false),
                'website' => array('name' => 'Website', 'is_required' => false, 'type' => 'SYSTEM', 'is_address' => false),
                'address_address' => array('name' => 'Address', 'is_required' => false, 'type' => 'SYSTEM', 'is_address' => true),
                'address_city' => array('name' => 'City', 'is_required' => false, 'type' => 'SYSTEM', 'is_address' => true),
                'address_state' => array('name' => 'State', 'is_required' => false, 'type' => 'SYSTEM', 'is_address' => true),
                'address_zip' => array('name' => 'Zip', 'is_required' => false, 'type' => 'SYSTEM', 'is_address' => true),
                'address_country' => array('name' => 'Country', 'is_required' => false, 'type' => 'SYSTEM', 'is_address' => true)
            );

            // $customFields = $this->agile_http("custom-fields/scope?scope=CONTACT", null, "GET");
            $agile_domain = get_option('agilecrm_cf7_domain');
            $agile_email = get_option('agilecrm_cf7_admin_email');
            $agile_api_key = get_option('agilecrm_cf7_api_key');

            $agile_url = "https://" .$agile_domain. ".agilecrm.com/dev/api/";
            $headers = array(
                        'Authorization' => 'Basic ' . base64_encode( $agile_email. ':' .$agile_api_key ),
                        'Content-type' => 'application/json',
                        'Accept' => 'application/json'
                        );

            $args = array(
                    'timeout' => 120,
                    'sslverify'   => false,
                    'headers' => $headers
                     );

            $request = wp_remote_get($agile_url.'custom-fields/scope?scope=CONTACT',$args);
            $customFields = wp_remote_retrieve_body( $request );

            if ($customFields) {
                $customFields = json_decode($customFields, true);
                foreach ($customFields as $customField) {
                    $agileFields[AgileCF7Addon::clean($customField['field_label'])] = array(
                        'name' => $customField['field_label'],
                        'is_required' => (boolean) $customField['is_required'],
                        'type' => 'CUSTOM',
                        'is_address' => false
                    );
                }
            }

            update_option("agilecrm_cf7_contact_fields", $agileFields);


            $mapFieldsMarkup = '';
            foreach ($agileFields as $fieldKey => $fieldVal) {
                $mapFieldsMarkup .= '<tr valign="top"><th scope="row">' . $fieldVal['name'];
                $required = '';
                if ($fieldVal['is_required']) {
                    $mapFieldsMarkup .= '<span style="color:#FF0000"> *</span>';
                    $required = 'class="required" required';
                }
                $mapFieldsMarkup .= '</th>';
                $mapFieldsMarkup .= '<td><select id="agilecrm_form_field_' . $fieldKey . '" name="agilecrm_cf7_form_map[' . $fieldKey . ']"' . $required . '>' . $formFieldsOptions . '</select></td></tr>';
            }

            $agilecrm_cf7_form_map = get_option('agilecrm_cf7_form_map');

            $responseJson = array(
                'markup' => '',
                'selectedFields' => ($agilecrm_cf7_form_map && isset($agilecrm_cf7_form_map['form_' . $formId])) ? $agilecrm_cf7_form_map['form_' . $formId] : array()
            );

            $responseJson['markup'] .= '<h3 class="title">Map Contact form 7 fields to Agile CRM contact properties</h3>';

            $responseJson['markup'] .= '<table class="form-table" style="width:33%"><tbody>';
            $responseJson['markup'] .= '<tr valign="top"><th scope="row">Agile property</th><td><strong>Form field</strong></td></tr>';
            $responseJson['markup'] .= $mapFieldsMarkup;
            $responseJson['markup'] .= '</tbody></table>';

            $responseJson['markup'] .= '<h3>Add a tag to all contacts created from this form</h3>';
            $responseJson['markup'] .= '<table class="form-table"><tbody><tr valign="top">'
                    . '<th scope="row" style="width: 136px;">Tag</th>'
                    . '<td><input type="text" name="agilecrm_cf7_form_map[hard_tag]" id="agilecrm_form_field_hard_tag"><br>'
                    . '<small>Tag name can not have special characters except space and underscore.</small></td>'
                    . '</tr></tbody></table>';

            echo json_encode($responseJson);
            die();
        }

        /**
         * Save form mapped fields to database via Ajax
         */
        public function map_form_fields()
        {
            global $wpdb;
            $agilecrm_cf7_form_map = get_option('agilecrm_cf7_form_map');
            $agilecrm_form_sync_id = $_POST['agilecrm_cf7_sync_form'];

            //save checked forms ids  
            $agilecrm_cf7_mapped_forms = get_option('agilecrm_cf7_mapped_forms');
            if (isset($_POST['agilecrm_cf7_mapped_forms']) && check_admin_referer( 'agilecrm_cf7_form_nonce_action', 'agilecrm_cf7_form_nonce_field' )) {
                $syncedForms = $_POST['agilecrm_cf7_mapped_forms'];
                if (in_array($agilecrm_form_sync_id, $agilecrm_cf7_mapped_forms)) {
                    $syncedForms = array();
                }
                if ($agilecrm_cf7_mapped_forms != false) {
                    $syncedForms = array_merge($agilecrm_cf7_mapped_forms, $syncedForms);
                }
            } else {
                $syncedForms = $agilecrm_cf7_mapped_forms;
                if (($key = array_search($agilecrm_form_sync_id, $syncedForms)) !== false) {
                    unset($syncedForms[$key]);
                }
            }
            $update = update_option('agilecrm_cf7_mapped_forms', $syncedForms);

            if (isset($_POST['agilecrm_cf7_form_map']) && check_admin_referer( 'agilecrm_cf7_form_nonce_action', 'agilecrm_cf7_form_nonce_field' )) {
                $formFields['form_' . $agilecrm_form_sync_id] = $_POST['agilecrm_cf7_form_map'];
                if ($agilecrm_cf7_form_map != false) {
                    $formFields = array_merge($agilecrm_cf7_form_map, $formFields);
                }

                if (isset($formFields['form_' . $agilecrm_form_sync_id]['hard_tag']) && $formFields['form_' . $agilecrm_form_sync_id]['hard_tag'] != '') {
                    $formFields['form_' . $agilecrm_form_sync_id]['hard_tag'] = sanitize_text_field($formFields['form_' . $agilecrm_form_sync_id]['hard_tag']); // mb_ereg_replace('[^ \w]+', '', $formFields['form_' . $agilecrm_form_sync_id]['hard_tag']);
                    // $formFields['form_' . $agilecrm_form_sync_id]['hard_tag'] = preg_replace('!\s+!', ' ', $formFields['form_' . $agilecrm_form_sync_id]['hard_tag']);
                }

                $update = update_option('agilecrm_cf7_form_map', $formFields);
            }

            echo ($update) ? '1' : '0';

            die();
        }

        /**
         * Syncs form entries to Agile CRM whenever a mapped form is submited.
         */
        public function sync_contact_form_entries_to_agile($cf7)
        {   
             $output = "";
               $output .= "Name:";
               $output .= "Email: " ;
             $output .= "Message: ";
            
            $submission = WPCF7_Submission::get_instance();
            if ( $submission ) {
                $formdata = $submission->get_posted_data();
            }
            
            $formId = $formdata['_wpcf7'];
            // file_put_contents("cf7outputtest.txt", print_r($formId, true));
            $agilecrm_cf7_form_map = get_option('agilecrm_cf7_form_map');
            $agilecrm_cf7_mapped_forms = get_option('agilecrm_cf7_mapped_forms');

            if (empty($formId)) {
                $get_current = WPCF7_ContactForm::get_current();
                $formId = $get_current->id;
            }
            
            if ($formId) {

                if ($agilecrm_cf7_mapped_forms && in_array($formId, $agilecrm_cf7_mapped_forms)) {
                    if ($agilecrm_cf7_form_map && isset($agilecrm_cf7_form_map['form_' . $formId])) {

                        $agileFields = get_option('agilecrm_cf7_contact_fields');
                        $mappedFields = $agilecrm_cf7_form_map['form_' . $formId];
                        $contactProperties = array();
                        $addressProp = array();

                        //for web tracking
                        if (isset($formdata[$mappedFields['email']]) && $formdata[$mappedFields['email']] != '') {
                            $_SESSION['agileCRMTrackEmail'] = $formdata[$mappedFields['email']];
                        }  
                        
                        foreach ($agileFields as $fieldKey => $fieldVal) {
                            if ($mappedFields[$fieldKey] != '') {
                                if ($fieldVal['type'] == 'CUSTOM') {                        

                                    if(is_array($formdata[$mappedFields[$fieldKey]])){
                                        $contactProperties[] = array(
                                            "name" => $fieldVal['name'],
                                            "value" => $formdata[$mappedFields[$fieldKey]][0],
                                            "type" => $fieldVal['type']
                                        );                                         
                                    }
                                    else{
                                        $contactProperties[] = array(
                                            "name" => $fieldVal['name'],
                                            "value" => $formdata[$mappedFields[$fieldKey]],
                                            "type" => $fieldVal['type']
                                        );
                                    }
                                    
                                } elseif ($fieldVal['type'] == 'SYSTEM') {
                                    if($fieldKey == "email"){
                                        $contact_email = $formdata[$mappedFields[$fieldKey]];           
                                    }
                                    
                                    if($formdata[$mappedFields[$fieldKey]] == ""){
                                        $formdata[$mappedFields[$fieldKey]] = " ";
                                    }                                    

                                    if ($fieldVal['is_address']) {
                                        $addressField = explode("_", $fieldKey);
                                        $addressProp[$addressField[1]] = $formdata[$mappedFields[$fieldKey]];
                                    } else {
                                        if ($fieldKey != 'tags') {
                                            if(is_array($formdata[$mappedFields[$fieldKey]])){
                                                $contactProperties[] = array(
                                                    "name" => $fieldKey,
                                                    "value" => $formdata[$mappedFields[$fieldKey]][0],
                                                    "type" => $fieldVal['type']
                                                );
                                            }
                                            else{
                                                $contactProperties[] = array(
                                                    "name" => $fieldKey,
                                                    "value" => $formdata[$mappedFields[$fieldKey]],
                                                    "type" => $fieldVal['type']
                                                );
                                            }                                            
                                        }
                                    }                                    
                                }
                            }
                        }

                        if ($addressProp) {
                            $contactProperties[] = array(
                                "name" => "address",
                                "value" => json_encode($addressProp),
                                "type" => "SYSTEM"
                            );
                        }

                        $finalData = array("properties" => $contactProperties);

                        //tags
                        $finalData['tags'] = array();

                        if ($mappedFields["hard_tag"] != '') {
                             $finalData['tags'] = explode("," , $mappedFields['hard_tag']);                     
                        }   

                        if ($mappedFields["tags"] != '') {
                            // $finalData['tags'] = array_merge($finalData['tags'], $formdata[$mappedFields['tags']][0]);
                             array_push($finalData['tags'], implode(',',$formdata[$mappedFields['tags']]));
                        }
                                                                                                                        
                        // $search_email = $this->agile_http("contacts/search/email/".$contact_email, null, "GET");
                        $agile_domain = get_option('agilecrm_cf7_domain');
                        $agile_email = get_option('agilecrm_cf7_admin_email');
                        $agile_api_key = get_option('agilecrm_cf7_api_key');

                        $agile_url = "https://" .$agile_domain. ".agilecrm.com/dev/api/";
                        $headers = array(
                                        'Authorization' => 'Basic ' . base64_encode( $agile_email. ':' .$agile_api_key ),
                                        'Content-type' => 'application/json',
                                        'Accept' => 'application/json'
                                        );

                        $args_get = array(
                                        'timeout' => 120,
                                        'sslverify'   => false,
                                        'headers' => $headers
                                        );

                        $request = wp_remote_get($agile_url.'contacts/search/email/'.$formdata[$mappedFields['email']],$args_get);
                        $result = wp_remote_retrieve_body( $request );                        
                        $search_email = json_decode($result, false, 512, JSON_BIGINT_AS_STRING);

                        // $search_email = json_decode($search_email, false, 512, JSON_BIGINT_AS_STRING);
                        $agile_url = "https://" .$agile_domain. ".agilecrm.com/dev/api/";
                        if($search_email && $search_email->id){
                            $contact_id = $search_email->id;
                            $finalData['id'] = $contact_id;     
                            $headers = array(
                                        'Authorization' => 'Basic ' . base64_encode( $agile_email. ':' .$agile_api_key ),
                                        'Content-type' => 'application/json',
                                        'Accept' => 'application/json'
                                        );

                            $args_put = array(
                                        'method' => 'PUT',
                                        'timeout' => 120,
                                        'sslverify'   => false,
                                        'headers' => $headers,
                                        'body' => json_encode($finalData)
                                         );

                            $request = wp_remote_request($agile_url.'contacts/edit-properties',$args_put);              
                            // $this->agile_http("contacts/edit-properties", json_encode($finalData), "PUT");
                            
                            $tags_json = array(
                                            'id' => $contact_id,
                                            'tags' => $finalData['tags']
                                        );
                            
                            $args_put = array(
                                            'method' => 'PUT',
                                            'timeout' => 120,
                                            'sslverify'   => false,
                                            'headers' => $headers,
                                            'body' => json_encode($tags_json)
                                             );

                            $response = wp_remote_request($agile_url.'contacts/edit/tags',$args_put);

                            // $this->agile_http("contacts/edit/tags", json_encode($tags_json), "PUT");      
                        }
                        else{

                            $headers = array(
                                    'Authorization' => 'Basic ' . base64_encode( $agile_email. ':' .$agile_api_key ),
                                    'Content-type' => 'application/json',
                                    'Accept' => 'application/json'
                                    );

                            $args_post = array(
                                    'method' => 'POST',
                                    'timeout' => 120,
                                    'sslverify'   => false,
                                    'headers' => $headers,
                                    'body' => json_encode($finalData)
                                     );

                            wp_remote_post($agile_url.'contacts',$args_post);      
                            // $this->agile_http("contacts", json_encode($finalData), "POST");
                        }

                    }
                }
            }
        }

        /**
         * Set user entered email to track web activities
         */
        public function set_email()
        {    
            $agile_domain = get_option('agilecrm_cf7_domain');
            $agile_api_key = get_option('agilecrm_cf7_api_key');
            
            echo '<script id="_agile_min_js" type="text/javascript" src="https://'.$agile_domain.'.agilecrm.com/stats/min/agile-min.js"> </script>'; 
            echo '<script> ';
            echo 'if(typeof _agile != "undefined") { ';
            echo '_agile.set_account("'.$agile_api_key.'","'.$agile_domain.'");';
            if (isset($_SESSION['agileCRMTrackEmail'])) {
                echo '_agile.set_email("' . $_SESSION['agileCRMTrackEmail'] . '");';
                unset($_SESSION['agileCRMTrackEmail']);
            }
            echo '_agile.track_page_view()';
            echo ' }';
            echo ' </script>';
        }

        /**
         * AgileCRM Request Wrapper function
         */
        public function agile_http($endPoint, $data, $requestMethod)
        {
            $agile_domain = get_option('agilecrm_cf7_domain');
            $agile_email = get_option('agilecrm_cf7_admin_email');
            $agile_api_key = get_option('agilecrm_cf7_api_key');

            if ($agile_domain && $agile_email && $agile_api_key) {
                $agile_url = "https://" . $agile_domain . ".agilecrm.com/dev/api/";

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
                curl_setopt($ch, CURLOPT_UNRESTRICTED_AUTH, true);

                switch ($requestMethod) {
                    case "POST":
                        curl_setopt($ch, CURLOPT_URL, $agile_url . $endPoint);
                        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
                        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
                        break;
                    case "GET":
                        curl_setopt($ch, CURLOPT_URL, $agile_url . $endPoint);
                        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
                        break;
                    case "PUT":
                        curl_setopt($ch, CURLOPT_URL, $agile_url . $endPoint);
                        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
                        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
                        break;
                    case "DELETE":
                        curl_setopt($ch, CURLOPT_URL, $agile_url . $endPoint);
                        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
                        break;
                    default:
                        break;
                }

                curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-type : application/json; charset : UTF-8;', 'Accept: application/json'));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_USERPWD, $agile_email . ':' . $agile_api_key);
                curl_setopt($ch, CURLOPT_TIMEOUT, 120);

                $output = curl_exec($ch);
                $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($statusCode == 200) {
                    return $output;
                } elseif ($statusCode == 401) {
                    return false;
                }
            }

            return false;
        }

        /**
         * Sanitize custom field names, return value is used as a key.
         */
        public static function clean($string)
        {
            $string = str_replace(' ', '-', $string); // Replaces all spaces with hyphens.
            $string = preg_replace('/[^A-Za-z0-9\-]/', '', $string); // Removes special chars.
            return preg_replace('/-+/', '-', $string); // Replaces multiple hyphens with single one.
        }

    }

    //class end

    new AgileCF7Addon();
}