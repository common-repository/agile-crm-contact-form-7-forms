<div class="wrap">
    <?php $this->plugin_settings_tabs(); ?>

    <form method="post" action="#" id="agilecrm_cf7_form_map">
        <?php wp_nonce_field('agilecrm_cf7_form_nonce_action','agilecrm_cf7_form_nonce_field'); ?>
        <?php if (!extension_loaded('curl')) { ?>
        <div id="warningMsg" style="color: #cc3300; font-weight: bold; padding: 5px 0">Error : cURL library is not loaded. Enable cURL extension in your server to make the plugin work properly.</div>
        <?php } ?>

        <h3 class="title">Select the form to link it with Agile CRM</h3>

        <input type="hidden" name="action" value="agilecrm_cf7_map_fields">
        <table class="form-table">
            <tr valign="top">
                <th scope="row"><label for="agilecrm_cf7_sync_form">Contact Form</label></th>
                <td>
                    <select id="agilecrm_cf7_sync_form" name="agilecrm_cf7_sync_form" required="">
                        <option value="">Select form</option>                        
                        <?php
                        $args = array('post_type' => 'wpcf7_contact_form', 'posts_per_page' => -1); 
                        $cf7Forms = get_posts( $args );
                        //Above codes will return all the contact for 7 forms
                        $syncedForms = get_option('agilecrm_cf7_mapped_forms');
                        $form_post_ids = wp_list_pluck( $cf7Forms , 'ID' ); 
                        $form_titles = wp_list_pluck( $cf7Forms , 'post_title' );
                        //Above snippet will return form IDs and Titles in an array like below
                        $form_elements = array_combine($form_post_ids,$form_titles);                                          
                        foreach ($form_elements as $key=>$value) {
                            
                            $args = array('post_type' => 'wpcf7_contact_form', 'posts_per_page' => -1);
                            $cf7form = get_posts($args);

                            $form_title = wp_list_pluck($cf7form, 'post_title');                           
                           
                            $isSynced = false;
                            if ($syncedForms && in_array($key, $syncedForms)) {
                                $isSynced = true;
                            }
                            echo '<option data-isSynced="' . $isSynced . '" value="' . $key . '" >' . $value . '</option>';
                        }
                        ?>
                        

                    </select>
                </td>
            </tr>
            <tr valign="top" id="agilecrm_cf7_mapped_forms_row" style="display: none;">
                <th scope="row"></th>
                <td><input id="agilecrm_cf7_mapped_forms" name="agilecrm_cf7_mapped_forms[]" type="checkbox"/> <label id="agilecrm_cf7_mapped_forms_label" for="agilecrm_cf7_mapped_forms">Integrate this form with Agile</label></td>
            </tr>
        </table>

        <div id="wp_agile_ajax_result"></div>
        <p class="submit"><input type="submit" name="updateFields" id="updateFields" class="button button-primary" value="Save Changes"> <span id="ajax_spinner"></span></p>

    </form>
</div>
<?php echo '<script src="' . plugins_url('js/settings.js', dirname(__FILE__)) . '"></script>'; ?>