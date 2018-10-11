<?php
/**
 * Leads class
 *
 * DISCLAIMER
 *
 * @package             Contact Form 7 Lead Manager
 * @author              Evgeniya Lashkevich
 */


class Leads{

    public $parent;

    public function __construct($parent) {

        $this->parent = $parent;
        // Add the Admin Config page for this plugin

        // Add Lead Management page
        add_action('admin_menu', array(&$this, 'admin_menu'));

        // Hook into Contact Form 7 when a form post is made to save the data to the DB
        add_action('wpcf7_before_send_mail', array(&$this, 'save_form_data'));

        // Add setting to CF7 form creation
        add_action('wpcf7_admin_after_general_settings', array(&$this, 'add_meta_box'));

        //Save CF7 fields names
        add_action('wp_ajax_save_cf7_fields_names', array(&$this, 'save_cf7_fields_names'));

        // Have our own hook to receive form submissions independent of other plugins
        do_action('cf7lm_submit', array(&$this, 'save_form_data'));

        add_action('wp_ajax_ajax_get_leads', array(&$this, 'ajax_get_leads'));

        add_action('wp_ajax_create_lead', array(&$this, 'create_lead'));
        add_action('wp_ajax_delete_lead', array(&$this, 'delete_lead'));
        add_action('wp_ajax_edit_lead', array(&$this, 'edit_lead'));
        add_action('wp_ajax_send_lead', array(&$this, 'send_lead'));
    }

    /*
     * Install page for this plugin in WP Admin
     */
    public function admin_menu() {
        add_submenu_page('lead-management', __('Lead Management', 'cf7ml'), __('Lead Management', 'cf7ml'), 'edit_lead_management', 'lead-management', array(&$this, 'leads_page'));
        add_submenu_page('lead-management', __('Match Fields', 'cf7ml'), __('Match Fields', 'cf7ml'), 'edit_lead_management', 'match-fields', array(&$this, 'match_fields_page'));
        add_submenu_page('lead-management', __('Settings', 'cf7ml'), __('Settings', 'cf7ml'), 'edit_lead_management', 'leads-settings', array(&$this, 'leads_settings'));
    }

    /*
   * Callback from Contact Form 7. CF7 passes an object with the posted data which is inserted into the database
   * by this function.
   * @param $cf7 WPCF7_ContactForm|object the former when coming from CF7, the latter $fsctf_posted_data object variable
   * @return void
   */
    public function save_form_data($cf7) {
        try {
            $id = stripslashes($cf7->id);
            $time = date("Y-m-d H:i:s", time());
            $ip = (isset($_SERVER['X_FORWARDED_FOR'])) ? $_SERVER['X_FORWARDED_FOR'] : $_SERVER['REMOTE_ADDR'];
            $url = urldecode($_SERVER['REQUEST_URI']);
            if (substr($url, 0, 1) == '/') {
                $url = $_SERVER['HTTP_REFERER'];
            }

            if(!empty($_COOKIE['refer_link'])){
                $refer_url = urldecode($_COOKIE['refer_link']);
            }
            else{
                $refer_url = 'undefined';
            }

            // Search ref
            $refer_url_parse = parse_url($refer_url);
            parse_str($refer_url_parse['query'], $query_params);

            if (!empty($_COOKIE['ref'])){
                $tag = $_COOKIE['ref'];
            }
            elseif (!empty($_REQUEST['ref'])){
                $tag = $_REQUEST['ref'];
            }
            elseif(isset($query_params['ref']) && !empty($query_params['ref'])){
                $tag =  $query_params['ref'];
            }
            elseif(isset($query_params['q']) && !empty($query_params['q'])){
                $tag = $query_params['q'];
            }
            elseif(isset($query_params['text']) && !empty($query_params['text'])){
                $tag = $query_params['text'];
            }

            // Set up to allow all this data to be filtered
            $cf7->submit_time = $time;
            $cf7->ip = $ip;

            try {
                $newCf7 = apply_filters('cfdb_form_data', $cf7);
                if ($newCf7 && is_object($newCf7)) {
                    $cf7 = $newCf7;
                    $time = $cf7->submit_time;
                    $ip = $cf7->ip;
                }
                else {
                    error_log('CFLM Error: No or invalid value returned from "cfdb_form_data" filter: ' .
                            print_r($newCf7, true));
                }
            }
            catch (Exception $ex) {
                error_log(sprintf('CFLM Error: %s:%s %s  %s', $ex->getFile(), $ex->getLine(), $ex->getMessage(), $ex->getTraceAsString()));
            }
            $submission = WPCF7_Submission::get_instance();
            $postedData = $submission->get_posted_data();
            foreach ($postedData as $name => $value) {
                $name_clean = stripslashes($name);

                $value = is_array($value) ? implode($value, ', ') : $value;
                $value_clean = stripslashes($value);

                $lm_name = $this->get_lmname_by_cf7name($name_clean);
                if($name[0] != '_' && !empty($lm_name)){
                    $lead = $this->get_lead_by_name($lm_name['lm_name'], $time);

                    if ($cf7->uploaded_files && isset($cf7->uploaded_files[$name_clean])) {
                        $file_name = $_FILES[$name_clean]['tmp_name'];

                        if (!file_exists($this->parent->get_upload_files_dir())){
                            mkdir($this->parent->get_upload_files_dir(), 0777);
                        }

                        $file_path = $this->parent->get_upload_files_dir() .  '/' .$_FILES[$name_clean]['name'];
                        $filePath = $cf7->uploaded_files[$name_clean];
                        if ($file_path) {
                            copy($filePath, $file_path);
                            $value_clean = 'http://' . $_SERVER['HTTP_HOST'].str_replace($_SERVER['DOCUMENT_ROOT'], '', $file_path);
                        }
                    }
                    if(empty($lead)){
                        if(!empty($lm_name['lm_name'])){
                            $this->lead_insert($time, $id, $lm_name['lm_name'], $value_clean);
                        }
                    }
                    else{
                        if(!empty($value_clean) && !empty($lead['field_value'])){
                            $value_clean = $lead['field_value'] . ', '. $value_clean;
                        }

                        $this->lead_update($time, $id, $lm_name['lm_name'], $value_clean);
                    }

                    $custom_fields = new Fields($this->parent);
                    $all_fields = $custom_fields->get_fields();

                    foreach ($all_fields as $field){
                        if(empty($field['default_value']) && $field['type'] == 'alphanumeric'){
                            $field['default_value'] = '';
                        }
                        else{
                            $field['default_value'] = '0';
                        }
                        $this->lead_insert($time, $id, $field['field_name'], $field['default_value']);
                    }
                }
            }

            // Capture the IP Address of the submitter
            $this->lead_insert($time, $id, 'IP', $ip);

            // Capture the URL on which submitted form
            $this->lead_insert($time, $id, 'URL', $url);

            // Capture the tag
            $this->lead_insert($time, $id, 'tag', $tag);

            // Capture referrer url
            $this->lead_insert($time, $id, 'refer_url', $refer_url);

            //  Capture allowed field (false by default)
            $this->lead_insert($time, $id, 'allowed', '0');
        }
        catch (Exception $ex) {
            error_log(sprintf('CFLM Error: %s:%s %s  %s', $ex->getFile(), $ex->getLine(), $ex->getMessage(), $ex->getTraceAsString()));
        }
    }

    /*
     * Metabox to CF7 form creation
     */
    public function add_meta_box() {
        if (! current_user_can('publish_pages'))
            return;

        add_meta_box(
            'save_cf7_field_names',
            __('Save Fields Names'),
            array(&$this, 'save_cf7_field_names_metabox'),
            'cfseven',
            'save_cf7_field_names',
            'core'
        );

        do_meta_boxes('cfseven', 'save_cf7_field_names', array());
    }

    public function save_cf7_field_names_metabox() {
        wp_enqueue_script( 'cf7lm',  $this->parent->dir . '/js/cf7lm.js' );

        $names = $this->get_cf7names_by_id($_GET['post']);
    ?>
        <script> var spinner = jQuery(new Image()).attr('src', '<?php echo admin_url('images/wpspin_light.gif'); ?>');</script>
        <div>Already in DB: <span class="in-db"><?php echo implode(', ', $names);?></span></div>
        <p><label><?php _e('Field names'); ?></label></p>
        <div id="cf7-fields">
            <p><input type="text"  name="cf7_name[]" value="" /></p>
        </div>
        <p><a href="#" onclick="addFieldName(); return false;">+ Add field name</a></p>
          <input type="button" id="save-cf7-names" value="<?php _e('Save'); ?>" class="button" />
        <div class="clear"></div>

    <?php
    }

    public function save_cf7_fields_names() {
        foreach ($_POST['fields_names'] as $name){
            if(!empty($name)){
                $cf7_name = $this->get_match_by_name($_POST['id'], $name);

                if(empty($cf7_name)){
                    $this->match_insert($_POST['id'], $name);
                }
            }
        }

        $names = $this->get_cf7names_by_id($_POST['id']);
        die(implode(', ', $names));
    }

    /*
     * Leads page in WP Admin
     */
    public function leads_page() {
        $all_match = array();

        $fields['submit_time']['key'] = true;
        $fields['submit_time']['title'] = 'Submitted';
        $fields['submit_time']['create'] = true;
        $fields['submit_time']['edit'] = false;
        $fields['submit_time']['inputClass'] = 'date';
        $fields['form_id']['title'] = 'Form ID';
        $fields['URL']['title'] = 'URL';
        $fields['URL']['defaultValue'] = '';
        $fields['refer_url']['title'] = 'Referring URL';
        $fields['refer_url']['defaultValue'] = '';
        $fields['IP']['title'] = 'IP';
        $fields['IP']['edit'] = false;
        $fields['IP']['sorting'] = false;
        $fields['IP']['defaultValue'] = '';
        $fields['tag']['title'] = 'Tag';
        $fields['tag']['defaultValue'] = '';

    // Permission field
        if ( current_user_can('allow_leads') ) {
            $fields['allowed']['title'] = 'Allowed';
            $fields['allowed']['type'] = 'checkbox';
            $fields['allowed']['defaultValue'] = 0; // false by default
            $fields['allowed']['values']['0'] = '<span class="no">No</span>';
            $fields['allowed']['values']['1'] = '<span class="yes">Yes</span>';
            $fields['allowed']['formText'] = 'Yes';

            $fields['allowed_hidden']['type'] = 'hidden';
            $fields['allowed_hidden']['defaultValue'] =  $fields['allowed']['defaultValue'];
            $fields['allowed_hidden']['visibility'] = 'hidden';
        }

        $custom_fields = new Fields($this->parent);
        $all_fields = $custom_fields->get_fields('id DESC', 0, 0);

        foreach ($all_fields as $field){
            $fields[$field['field_name']]['title'] = $field['field_label'];
            $fields[$field['field_name']]['defaultValue'] = $field['default_value'];
            if($field['type'] == 'boolean'){
                $fields[$field['field_name']]['type'] = 'checkbox';
                $fields[$field['field_name']]['defaultValue'] = $field['default_value'];
                $fields[$field['field_name']]['values']['0'] = '<span class="no">No</span>';
                $fields[$field['field_name']]['values']['1'] = '<span class="yes">Yes</span>';
                $fields[$field['field_name']]['formText'] = 'Yes';

                $fields[$field['field_name'] . '_hidden']['type'] = 'hidden';
                $fields[$field['field_name'] . '_hidden']['defaultValue'] = $field['default_value'];
                $fields[$field['field_name'] . '_hidden']['visibility'] = 'hidden';
            }
            elseif($field['type'] == 'alphanumeric'){
                $fields[$field['field_name']]['type'] = 'textarea';
            }
        }

        $all_match = $this->get_match();
        if(!empty($all_match)){
            foreach($all_match as $cf7_name => $lm_name){
                if(!empty($lm_name)){
                    $fields[$lm_name]['title'] = $lm_name;
                    $fields[$lm_name]['defaultValue'] = '';
                    $fields[$lm_name]['type'] = 'textarea';
                }
            }
        }

        $boolean_fields = $custom_fields->get_fields_by_type('boolean');
        $numeric_fields = $custom_fields->get_fields_by_type('integers');

        wp_enqueue_script( 'jquery-ui',  $this->parent->dir . '/js/jquery-ui-1.10.0.min.js' );
        wp_enqueue_script( 'jtable',  $this->parent->dir . '/js/jtable/jquery.jtable.js' );
        wp_enqueue_script( 'dragtable',  $this->parent->dir . '/js/jquery.dragtable.js' );
        wp_enqueue_script( 'cf7lm',  $this->parent->dir . '/js/cf7lm.js' );
        wp_enqueue_style( 'jtable',  $this->parent->dir . '/js/jtable/themes/lightcolor/blue/jtable.min.css' );
        wp_enqueue_style( 'jquery-ui',  $this->parent->dir . '/js/jtable/themes/jquery-ui.custom.css' );
        wp_enqueue_style( 'dragtable',  $this->parent->dir . '/css/dragtable.css' );
        wp_enqueue_style( 'cf7lm',  $this->parent->dir . '/css/style.css' );

        // For database updating
        /*global $wpdb;
        $tableName = $this->parent->get_submits_table_name();

        $query =  "SELECT DISTINCT `submit_time`, `form_id` FROM `$tableName`";
        $results = $wpdb->get_results($query);

        foreach($results as $result){
            $query = "INSERT INTO `$tableName` (`submit_time`, `form_id`, `field_name`, `field_value`) VALUES (%s, %s, %s, %s)";

            $wpdb->query($wpdb->prepare($query, $result->submit_time, $result->form_id, 'allowed', '1'));
        }*/


    ?>
    <script>var fields = <?php echo json_encode($fields);?> </script>
        <div class="wrap leads">
             <h2><?php _e('Lead Management');?></h2>
            <div class="help-section"><?php _e('Help text');?></div>
            <div class="clear:both;"></div>
            <div class="filtering">
                <form action="" method="get" id="lead-search-form">
                    <div class="search-block">
                        <label><?php _e('Insert  Text');?></label> <input class="left-inputs" name="search_text" type="text" value="">
                    </div>
                    <div class="search-block">
                        <label><?php _e('Form id');?></label> <input class="left-inputs" name="search_form_id" type="text" value="" style="margin-left:45px">
                    </div>
                    <div class="search-block">
                        <label><?php _e('Start date');?>: </label><input class="date left-inputs" type="text" name="start_date" value=""/>
                        <label><?php _e('End date');?>:</label> <input class="date" type="text" name="end_date"  value=""/>
                        <div class="shortcut-buttons">
                            <input type="button" class="today-button button" value="Today">
                            <input type="button" class="this-month-button button" value="This Month">
                            <input type="button" class="last-month-button button" value="Last Month">
                            <input type="button" class="this-year-button button" value="This Year">
                        </div>
                    </div>
                    <div class="search-block">
                        <?php if(!empty($boolean_fields)):?>
                            <label><?php _e('Boolean search');?></label>
                            <select name="search_boolean_field">
                                <option value=""><?php _e('Select field');?></option>
                                     <?php foreach($boolean_fields as $field):?>
                                        <option value="<?php echo $field['field_name'];?>"><?php echo $field['field_label'];?></option>
                                     <?php endforeach;?>
                            </select>
                             <select name="search_boolean_value" class="right-select">
                                  <option value=""><?php _e('Select value');?></option>
                                  <option value="1">true</option>
                                  <option value="0">false</option>
                             </select>
                          <?php endif;?>
                    </div>
                    <div class="search-block">
                        <?php if(!empty($numeric_fields)):?>
                             <label><?php _e('Numeric search');?></label>
                            <select name="search_numeric_field">
                                <option value=""><?php _e('Select field');?></option>
                                     <?php foreach($numeric_fields as $field):?>
                                        <option value="<?php echo $field['field_name'];?>"><?php echo $field['field_label'];?></option>
                                     <?php endforeach;?>
                            </select>
                            <label><?php _e('Bigger than');?>:</label> <input class="numeric-field" type="text" name="bigger_numeric_value" value=""/>
                            <label><?php _e('Small than');?>: </label><input class="numeric-field" type="text" name="small_numeric_value"  value=""/>
                        <?php endif;?>
                    </div>
                    <div class="search-buttons">
                        <div class="filtering-buttons">
                            <input type="button" class="filter-button" value="<?php _e('Filter');?>">
                            <input type="button" class="reset-button button" value="<?php _e('Reset');?>">
                        </div>
                        <div class="export-container"><input type="button" class="export button" value="<?php _e('Export');?>"></div>
                    </div>
                </form>
            </div>
            <div id="lead-table"></div>
        </div>

    <?php
    }

    /*
     * Match fields page in WP Admin
     */
    public function match_fields_page() {
        wp_enqueue_style( 'jtable',  $this->parent->dir . '/css/style.css' );
        wp_enqueue_script( 'cf7lm',  $this->parent->dir . '/js/cf7lm.js' );

        $all_match = array();

        $lm_names = $this->get_distinct_lm_names();

        if(isset($_POST['save'])){
           foreach($_POST['lead_field_name'] as $cf7_name => $lm_name){
               $this->match_update($cf7_name, $lm_name);

           }
        }
        $all_match = $this->get_match();

    ?>
        <div class="wrap match-fields">

            <h2><?php _e('Match Fields');?></h2>
            <div class="help-section"><?php _e('Help text');?></div>

            <form action="" method="post">
                <table cellspacing="10">
                    <thead>
                        <tr>
                            <td><h3><?php _e('CF7 field name');?></h3></td>
                            <td></td>
                            <td><h3><?php _e('Lead management field name');?></h3></td>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($all_match as $cf7_name => $lm_name):?>
                        <tr>
                            <td><?php echo $cf7_name;?></td>
                            <td><h3>=</h3></td>
                            <td>
                                <input type="text" class="lm_name_<?php echo $cf7_name;?>" value="<?php echo $lm_name;?>" name="lead_field_name[<?php echo $cf7_name;?>]">
                                <?php _e('or select from existed');?>
                                <select class="select_lm_name_<?php echo $cf7_name;?>">
                                    <option><?php _e('Select');?></option>
                                    <?php foreach($lm_names as $name):?>
                                        <?php if(!empty($name['lm_name'])):?>
                                               <option value="<?php echo $name['lm_name'];?>"><?php echo $name['lm_name'];?></option>
                                        <?php endif;?>
                                    <?php endforeach;?>
                                </select>
                            </td>
                        </tr>
                    <?php endforeach;?>
                    </tbody>
                </table>
                <input type="submit" name="save" class="button" value="<?php _e('Save');?>">
           </form>
        </div>

    <?php
    }

    /*
     * Plugin settings page in WP Admin
     */
    public function leads_settings() {
        wp_enqueue_style( 'jtable',  $this->parent->dir . '/css/style.css' );

        // get users who can manage leads
        $all_users = get_users();
        $lead_management_users = array();
        $allowed_users = array();

        foreach($all_users as $user){

            if($user->has_cap('edit_lead_management')){
                $lead_management_users[$user->data->ID] = $user->data->user_login;
            }
            if($user->has_cap('allow_leads')){
                $allowed_users[] = $user->data->ID;
            }
        }

        // Default email to send leads
        $default_email = isset($_POST['cf7lm_default_email']) ? $_POST['cf7lm_default_email'] : get_option('cf7lm_default_email');
        if(isset($_POST['save'])){
            if ( get_option('cf7lm_default_email') !== false ) {
                 update_option( 'cf7lm_default_email', $_POST['cf7lm_default_email'] );
            }
            else {
                add_option( 'cf7lm_default_email', $_POST['cf7lm_default_email'] );
            }

            update_option('cf7lm_default_email', $_POST['cf7lm_default_email']);

            // remove capability
            foreach($all_users as $id){
                $user = new WP_User( $id );
                $user->remove_cap( 'allow_leads' );
            }

            if(!empty($_POST['cf7lm_user_id'])){
                // add capability
                foreach($_POST['cf7lm_user_id'] as $id){
                    $user = new WP_User( $id );
                    $user->add_cap( 'allow_leads', true );
                }
            }
        }

    ?>
        <div class="wrap leads-settings">

            <h2><?php _e('Settings');?></h2>
            <form action="" method="post">
                <div>
                    <label><h3><?php _e('Default email', 'cf7ml');?>:</h3></label>
                    <input type="text" value="<?php echo $default_email;?>" name="cf7lm_default_email">
                </div>
                <div>
                    <h3><?php _e('User Permissions', 'cf7ml');?></h3>
                    <label><?php _e('Select user who can allow leads', 'cf7ml');?></label>
                    <div>
                        <select name="cf7lm_user_id[]" multiple>
                            <?php if(!empty($lead_management_users)):?>
                                <?php foreach($lead_management_users as $id => $login):?>
                                    <option value="<?php echo $id;?>" <?php if(in_array($id, $allowed_users)):?>selected<?php endif;?>>
                                        <?php echo $login;?>
                                    </option>
                                <?php endforeach;?>
                            <?php endif;?>
                        </select>
                    </div>
                </div>
                <p><input type="submit" name="save" class="button" value="<?php _e('Save');?>"></p>
           </form>
        </div>

    <?php
    }

    /*
     * Return all info about submits from DB
     * @return array()
     */
    public function get_submits($order = 'submit_time DESC', $start = 0, $count = 10, $where = array()){
        global $wpdb;

        $leads = array();
        $tableName = $this->parent->get_submits_table_name();
        $final_where = [];

        if($count === 0){
            $limit = '';
        }
        else{
            $limit = "LIMIT $start, $count";
        }

        // Select all needed times
        if(!empty($where)){
            if(isset($where['field_search'])){
                if(count($where['field_search']) > 1){
                    for ($i = 0; $i < count($where['field_search']) - 1; $i++){
                        $final_where[] = "(SELECT `submit_time` FROM `$tableName` WHERE " . $where['field_search'][$i] . ")";
                    }

                    $final_where = '`submit_time` IN ' . implode(' AND submit_time IN ', $final_where) . ' AND ' . $where['field_search'][count($where['field_search']) - 1];
                }
                else{
                    $final_where = $where['field_search'][0];
                }
            }

            // date range select
            if(isset($where['date_range'])){
                if(!empty($final_where)){
                    $final_where .= ' AND ' .$where['date_range'];
                }
                else{
                    $final_where = $where['date_range'];
                }
            }

            $query = "SELECT DISTINCT `submit_time` FROM  `$tableName` WHERE $final_where ORDER BY $order $limit";

        }
        else{
            $query = "SELECT DISTINCT `submit_time` FROM  `$tableName` ORDER BY $order $limit";
        }

        $submits_time = $wpdb->get_results($query, ARRAY_A);

        // Select all fields for times
        foreach ($submits_time as $time){
            $query = "SELECT * FROM  `$tableName` WHERE `submit_time`='". $time['submit_time']. "'";
            $submits = $wpdb->get_results($query, ARRAY_A);

            foreach ($submits as $submit){
                $leads[$submit['submit_time']]['form_id'] = $submit['form_id'];
                $leads[$submit['submit_time']]['submit_time'] = $submit['submit_time'];
                $leads[$submit['submit_time']][$submit['field_name']] = $submit['field_value'];
            }
        }

        return $leads;
    }

    /*
     * Get leads count
     * @return int
     */
    public function get_leads_count($where){
        global $wpdb;
        $tableName = $this->parent->get_submits_table_name();
        if(!empty($where)){
            if(isset($where['field_search'])){
                if(count($where['field_search']) > 1){
                    for ($i = 0; $i < count($where['field_search']) - 1; $i++){
                        $final_where[] = "(SELECT `submit_time` FROM `$tableName` WHERE " . $where['field_search'][$i] . ")";
                    }

                    $final_where = '`submit_time` IN ' . implode(' AND submit_time IN ', $final_where) . ' AND ' . $where['field_search'][count($where['field_search']) - 1];
                }
                else{
                    $final_where = $where['field_search'][0];
                }
            }

            if(isset($where['date_range'])){
                if(!empty($final_where)){
                    $final_where .= ' AND ' .$where['date_range'];
                }
                else{
                    $final_where = $where['date_range'];
                }
            }

            $query = "SELECT COUNT(DISTINCT `submit_time`) as count FROM  `$tableName` WHERE $final_where";
        }
        else{
            $query = "SELECT COUNT(DISTINCT `submit_time`) as count FROM `$tableName`";
        }

        $count = $wpdb->get_row($query);

        return $count->count;
    }

     /*
     * Get submitted dates and form id
     * @return array()
     */
    public function get_submitted_dates_form_id(){
        global $wpdb;
        $dates = array();
        $tableName = $this->parent->get_submits_table_name();
        $query = "SELECT DISTINCT `submit_time`, `form_id` FROM `$tableName`";

        $submits = $wpdb->get_results($query);

        return $submits;
    }

    /*
     * Return lead by name
     * @return array()
     */
    public function get_lead_by_name($name, $time){
        global $wpdb;
        $tableName = $this->parent->get_submits_table_name();
        $query = "SELECT * FROM `$tableName` WHERE `field_name`='$name' AND `submit_time`=STR_TO_DATE('$time', '%Y-%m-%d %H:%i:%s')";
        $lead = $wpdb->get_row($query, ARRAY_A);

        return $lead;

    }

    /*
     * Return criteria for lead search
     * @return array()
     * */
    public function get_search_criteria(){

        // String search
        if(!empty($_REQUEST['search_text'])){
            $_REQUEST['search_text'] = str_replace(' ', '%', $_REQUEST['search_text']);
            $where['field_search'][] = "`field_value` LIKE '%" . $_REQUEST['search_text'] . "%'";
        }

        // Form id search
        if(!empty($_REQUEST['search_form_id'])){
            $where['field_search'][] = "`form_id` ='" . $_REQUEST['search_form_id'] . "'";
        }

        // Date range
        if (!empty($_REQUEST['start_date']) || !empty($_REQUEST['end_date'])) {

            // Select from start date
            if (!empty($_REQUEST['start_date']) AND empty($_REQUEST['end_date'])) {
                $where['date_range'] = "`submit_time` >= STR_TO_DATE('" . $_REQUEST['start_date'] . " 00:00:00', '%Y-%m-%d %H:%i:%s')";
            }
            // Select to end date
            elseif (empty($_REQUEST['start_date']) AND !empty($_REQUEST['end_date'])) {
                $where['date_range'] = "`submit_time` <= STR_TO_DATE('" . $_REQUEST['end_date'] . " 00:00:00', '%Y-%m-%d %H:%i:%s')";
            }
            elseif (!empty($_REQUEST['start_date']) AND !empty($_REQUEST['end_date'])) {
                $where['date_range'] = "`submit_time` BETWEEN STR_TO_DATE('" . $_REQUEST['start_date'] . " 00:00:00', '%Y-%m-%d %H:%i:%s') AND STR_TO_DATE('" . $_REQUEST['end_date'] . " 23:59:59', '%Y-%m-%d %H:%i:%s')";
            }
        }

        // Boolean search
        if(!empty($_REQUEST['boolean_field']) && is_numeric($_REQUEST['boolean_field_value'])){
            $where['field_search'][] = "`field_name` = '" . $_REQUEST['boolean_field'] . "' AND `field_value` = '". $_REQUEST['boolean_field_value'] . "'";
        }

        // Numeric search

        if(!empty($_REQUEST['numeric_field']) && (is_numeric($_REQUEST['bigger_numeric_value']) || is_numeric($_REQUEST['small_numeric_value']))){
            if(is_numeric($_REQUEST['bigger_numeric_value']) && !is_numeric($_REQUEST['small_numeric_value'])){
                $where['field_search'][] = "`field_name` = '" . $_REQUEST['numeric_field'] . "' AND CAST(`field_value` as signed)  >=  '" . $_REQUEST['bigger_numeric_value'] . "'";
            }
            elseif (is_numeric($_REQUEST['small_numeric_value']) && !is_numeric($_REQUEST['bigger_numeric_value'])){
                $where['field_search'][] = "`field_name` = '" . $_REQUEST['numeric_field'] . "' AND CAST(`field_value` as signed) <= '" . $_REQUEST['small_numeric_value'] . "'";

            }
            elseif (is_numeric($_REQUEST['bigger_numeric_value']) && is_numeric($_REQUEST['small_numeric_value'])){
                $where['field_search'][] = "`field_name` = '" . $_REQUEST['numeric_field'] . "' AND CAST(`field_value` as signed) BETWEEN '" . $_REQUEST['bigger_numeric_value'] . "' AND '". $_REQUEST['small_numeric_value'] . "'";
            }
        }

        $criteria['where'] = $where;

        return $criteria;
    }

    /*
     * Ajax build lead table
     */
    public function ajax_get_leads(){
        $where = '';
        $j_table_results = [];

        $start = isset($_REQUEST['jtStartIndex']) ? $_REQUEST['jtStartIndex'] : 0;
        $count = isset($_REQUEST['jtPageSize']) ? $_REQUEST['jtPageSize'] : 10;

        $order = isset($_REQUEST['jtSorting']) ? $_REQUEST['jtSorting'] : 'submit_time DESC';
        $orders = explode(' ', $order);

        // leads order
        if ($orders[0] != 'submit_time' & $orders[0] != 'form_id'){
            if($orders[1] == 'DESC'){
                $field_order = 'ASC';
            }
            else{
                $field_order = 'DESC';
            }
            $order = "FIELD(field_name,  '" . $orders[0] ."') $field_order , field_value " . $orders[1];
        }

        // filter conditions
        $criteria = $this->get_search_criteria();

        $where = $criteria['where'];

        // permissions condition
        if(!current_user_can('allow_leads')){
            $where['field_search'][] = "`field_name` = 'allowed' AND `field_value` = '1'";
        }

        if(empty($where)){
            $where = array();
        }

        $leads = $this->get_leads_array($order, $start, $count, $where);
        $i = 0;
        foreach($leads as $rows){
			$j_table_results[$i] = [];
            foreach($rows as $key => $value){
                if(stripos($value, 'cf7lm_files') !== false){
                    $files_links = array();
                    $files = explode(', ', $value);
                    foreach($files as $file){
                        $extension = substr($file, (strrpos($file, '.') + 1));

                        if(file_exists($this->parent->path . '/img/'.$extension.'.png')){
                            $img_src = $this->parent->dir . '/img/'.$extension.'.png';
                        }
                        else{
                            $img_src = $this->parent->dir . '/img/file.png';
                        }

                        $files_links[] = '<a href="' . $file . '" title="' . $file . '"><img src="' . $img_src . '"/></a>';
                    }

					$j_table_results[$i][$key] = implode(' ', $files_links);
					
                }
                elseif(stripos($key, 'ip') === 0){

                    $country = $this->get_country_by_ip($value);

                    if(!empty($country)){
						
						$j_table_results[$i][$key] = $value . '<br/>[' . $country . ']';
                    }
                    else
						$j_table_results[$i][$key] = $value;
						
                    }

                }
                elseif(stripos($key, 'url') !== false){

					$j_table_results[$i][$key] = urldecode($value);
					
                }
                else{
					
					$j_table_results[$i][$key] = $value;
					
                }
            }
            $i++;
        }

        //Return result to jTable

        $j_table_result = array();
        $j_table_result['Result'] = 'OK';
        $j_table_result['TotalRecordCount'] = $this->get_leads_count($where);
        $j_table_result['Records'] = $j_table_results;
        print json_encode($j_table_result);
        die();
    }

    /*
     * Get leads array with all fields
     * @return array()
     */

    public function get_leads_array($order = 'submit_time DESC', $start = 0, $count = 10, $where = array()){
        $results = array();
        $leads = $this->get_submits($order, $start, $count, $where);

        foreach ($leads as $submitted_time => $lead){

            // Not need because those fields can't be editable
           /* foreach ($lead as $key => $value){
                $new_key = $all_match[strtolower($key)];
                if (!empty($new_key)){
                   $lead[$new_key] = $value;
                }
            }*/

            $results[] = $lead;
        }
        return $results;
    }

    /*
     * Ajax create lead
     */
    public function create_lead(){
       // $time = date("Y-m-d H:i:s", time());
        $return = array();

        if(!empty($_POST)){
            $id = $_POST['form_id'];
            $time = date('Y-m-d H:i:s', strtotime($_POST['submit_time'] . ' ' . date('H:i:s')));
            foreach($_POST as $name => $value) {
                    if($name <> 'form_id' && $name <> 'submit_time'){

                        $pos = strpos($name, '_hidden');
                        if ($pos) {
                            $field_name = substr($name, 0, $pos);
                            $value = 0;
                        }
						else{
							$field_name = $name;
						}

                        $this->lead_insert($time, $id, $field_name, $value);
                    }
                    $return[$field_name] = $value;
            }

            //Return result to jTable
            $j_table_result = array();
            $j_table_result['Result'] = 'OK';
            $j_table_result['Record'] = $return;
            print json_encode($j_table_result);
        }
        else{
            //Return result to jTable
            $j_table_result = array();
            $j_table_result['Result'] = 'Error';
            $j_table_result['Message'] = 'Please fill all fields!';
            print json_encode($j_table_result);
        }
        die();
    }

    /*
     * Ajax edit lead
     */
    public function edit_lead(){
        if(!empty($_POST)){
            $time = $_POST['submit_time'];
            $form_id = $_POST['form_id'];
			$prev_field = '';
			
            if(!empty($time)){
                foreach($_POST as $name => $value) {
                    $value = strip_tags($value);
                    if($name <> 'form_id' && $name <> 'submit_time'){

                        $pos = strpos($name, '_hidden');
                        if ($pos) {
                            $field_name = substr($name, 0, $pos);
                            $value = 0;
                        }
						else{
							$field_name = $name;
						}

						if($field_name != $prev_field)	{
							$lead = $this->get_lead_by_name($field_name, $time);

							if(!empty($lead)){
							   $this->lead_update($time, $form_id, $field_name, $value);
							}
							else{
								if($value !== ''){
									$this->lead_insert($time, $form_id, $field_name, $value);
								}
							}
						}

						$prev_field = $field_name;
                    }
                }
                //Return result to jTable
                $j_table_result = array();
                $j_table_result['Result'] = 'OK';
                print json_encode($j_table_result);
            }
        }
        else{
            //Return result to jTable
            $j_table_result = array();
            $j_table_result['Result'] = 'Error';
            $j_table_result['Message'] = 'Please fill all fields!';
            print json_encode($j_table_result);
        }
        die();
    }

    /*
     * Ajax delete lead
     */
    public function delete_lead(){
        if(!empty($_POST['submit_time'])){
            $time = $_POST['submit_time'];
            $this->lead_delete($time);

            //Return result to jTable
            $j_table_result = array();
            $j_table_result['Result'] = 'OK';
            print json_encode($j_table_result);
        }
        die();
    }

     /*
     * Ajax send lead to email
     */
    public function send_lead(){
        if(!empty($_POST['submit_time'])){
            $default_email = get_option('cf7lm_default_email');

            if(!empty($default_email)){
                $body = '<p>Hi. <br/>
                        You received an incoming lead:</p>';

                $matches = $this->get_match();

                foreach($_POST as $key => $field_value){
                    if(in_array($key, $matches)){
                        $body .= $key . ': ' . $field_value. '<br/>';
                    }

                }

                $body .= '<p>Thank you,<br/>
                         Your website.</p>';
                $subject = __('New Lead');
                $headers = 'From: ' . get_bloginfo('name') . ' <noreply@' .  $_SERVER['HTTP_HOST'] . '>' . "\r\n";
                add_filter( 'wp_mail_content_type', array(&$this, 'set_html_content_type') );
                wp_mail( $default_email, $subject, $body, $headers);
                $j_table_result['Result'] = 'OK';
                print json_encode($j_table_result);
            }
        }
        die();
    }

    /* HTML format for emails */
    function set_html_content_type() {
        return 'text/html';
    }

    /*
     * Insert in submits table
     */
    public function lead_insert($time, $id, $name, $value){
        global $wpdb;

        $tableName = $this->parent->get_submits_table_name();

        $query = "INSERT INTO `$tableName` (`submit_time`, `form_id`, `field_name`, `field_value`) VALUES (%s, %s, %s, %s)";

        $wpdb->query($wpdb->prepare($query, $time, $id, $name, $value));
    }

    /*
     * Update lead
     */
    public function lead_update($time, $form_id, $field_name, $field_value){
        global $wpdb;

        $tableName = $this->parent->get_submits_table_name();

        $query = "UPDATE `$tableName` SET `field_value`=%s, `form_id`=%d WHERE `submit_time`=%s AND `field_name`=%s";
        $wpdb->query($wpdb->prepare($query, $field_value, $form_id, $time, $field_name));

    }

    /*
     * Delete lead
     */
    public function lead_delete($time){
        global $wpdb;

        $tableName = $this->parent->get_submits_table_name();

        $query = "DELETE FROM `$tableName` WHERE `submit_time`=%s";

        $wpdb->query($wpdb->prepare($query, $time));
    }

    /*
     * Get all match
     */
    public function get_match(){
        global $wpdb;

        $match = array();

        $tableName = $this->parent->get_fields_compare_table_name();

        $query = "SELECT * FROM `$tableName` ORDER BY `cf7_name` ASC";

        $results = $wpdb->get_results($query, ARRAY_A);

        foreach ($results as $result){
            $match[strtolower($result['cf7_name'])] = $result['lm_name'];
        }

        return $match;
    }

    /*
     * Get match by form id
     */
    public function get_cf7names_by_id($id){
        global $wpdb;

        $names = array();

        $tableName = $this->parent->get_fields_compare_table_name();

        $query = "SELECT * FROM `$tableName` WHERE `id`=$id ORDER BY `cf7_name` ASC";

        $results = $wpdb->get_results($query, ARRAY_A);

        foreach ($results as $result){
            $names[] = $result['cf7_name'];
        }

        return $names;
    }

    /*
     * Get distinct lm_names
     */
    public function get_distinct_lm_names(){
        global $wpdb;

        $lm_names = array();

        $tableName = $this->parent->get_fields_compare_table_name();

        $query = "SELECT DISTINCT `lm_name` FROM `$tableName` ORDER BY `cf7_name` ASC";

        $lm_names = $wpdb->get_results($query, ARRAY_A);

        return $lm_names;
    }

    /*
     * Get match by name
     */
    public function get_match_by_name($id, $cf7_name){
        global $wpdb;

        $tableName = $this->parent->get_fields_compare_table_name();

        $query = "SELECT * FROM `$tableName` WHERE `cf7_name`='$cf7_name' AND `id`=$id";

        $match = $wpdb->get_row($query, ARRAY_A);

        return $match;
    }

    /*
     * Get lm_name by cf7_name
     */
    public function get_lmname_by_cf7name($cf7_name){
        global $wpdb;

        $tableName = $this->parent->get_fields_compare_table_name();

        $query = "SELECT `lm_name` FROM `$tableName` WHERE LOWER(cf7_name)=LOWER('$cf7_name')";

        $lm_name = $wpdb->get_row($query, ARRAY_A);

        return $lm_name;
    }


    /*
     * Insert in fields compare table
     */
    public function match_insert($id, $cf7_field){
        global $wpdb;

        $tableName = $this->parent->get_fields_compare_table_name();

        $query = "INSERT INTO `$tableName` (`id`, `cf7_name`) VALUES (%d, %s)";

       $wpdb->query($wpdb->prepare($query, $id, trim($cf7_field)));
    }

    /*
     * Update match
     */
    public function match_update($cf7_field, $lm_field){
        global $wpdb;

        $tableName = $this->parent->get_fields_compare_table_name();

        $query = "UPDATE `$tableName` SET `lm_name`=%s WHERE `cf7_name`=%s";

        $wpdb->query($wpdb->prepare($query, trim($lm_field), trim($cf7_field)));
    }

    /*
     * Delete match
     */
    public function match_delete($cf7_field){
        global $wpdb;

        $tableName = $this->parent->get_fields_compare_table_name();

        $query = "DELETE FROM `$tableName` WHERE `cf7_name`=%s";

        $wpdb->query($wpdb->prepare($query, $cf7_field));
    }

     /*
     * Get country by IP
     */
    public function get_country_by_ip($ip){
        $result['country'] = '';
        $ip_data = @json_decode(file_get_contents("http://www.geoplugin.net/json.gp?ip=".$ip));

        if($ip_data && $ip_data->geoplugin_countryName != null){
            $result['country'] = $ip_data->geoplugin_countryName;
        }

        return $result['country'];
    }

}