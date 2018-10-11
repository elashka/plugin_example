<?php
/**
 * Custom fields class
 *
 * DISCLAIMER
 *
 * @package             Contact Form 7 Lead Manager
 * @author              Evgeniya Lashkevich
 */

class Fields{

    public $parent;
    public $types = array(
                        'alphanumeric' => 'Alphanumeric',
                        'integers' => 'Integers',
                        'boolean' => 'Boolean',
                    );

    public function __construct($parent) {
        $this->parent = $parent;
        // Add Fields page for add/edir/delete custom fields to Lead Management table
        add_action('admin_menu', array(&$this, 'admin_menu'));

        add_action('wp_ajax_ajax_get_fields', array(&$this, 'ajax_get_fields'));

        add_action('wp_ajax_create_field', array(&$this, 'create_field'));
        add_action('wp_ajax_edit_field', array(&$this, 'edit_field'));
        add_action('wp_ajax_delete_field', array(&$this, 'delete_field'));

        // scripts
        wp_register_script( 'cf7ml_types', $this->parent->dir );
        wp_localize_script( 'jquery', 'cf7ml', array( 'cf7ml_types' => json_encode($this->types) ) );

    }

    /**
     * Install page for this plugin in WP Admin
     */
    public function admin_menu() {
        add_submenu_page('lead-management', __('Custom Fields', 'cf7ml'), __('Custom Fields', 'cf7ml'), 'administrator', 'cf7lm-custom-fields', array(&$this, 'fields_page'));
    }

    /**
     * Install page for this plugin in WP Admin
     */
    public function fields_page() {
        wp_enqueue_script( 'jquery-ui',  $this->parent->dir . '/js/jquery-ui-1.10.0.min.js' );
        wp_enqueue_script( 'jtable',  $this->parent->dir . '/js/jtable/jquery.jtable.js' );
        wp_enqueue_script( 'cf7lm',  $this->parent->dir . '/js/cf7lm.js' );
        wp_enqueue_style( 'jtable',  $this->parent->dir . '/js/jtable/themes/lightcolor/blue/jtable.min.css' );
        wp_enqueue_style( 'jquery-ui',  $this->parent->dir . '/js/jtable/themes/jquery-ui.custom.css' );
        wp_enqueue_style( 'cf7lm',  $this->parent->dir . '/css/style.css' );
    ?>
    <script>var fields = null </script>
        <div class="wrap">
            <h2>Lead Management Custom Fields</h2>
            <div class="help-section">Help text</div>
            <div id="fields-table"></div>
        </div>
    <?php
    }

    /**
     * Return all info about field from DB
     * @return array()
     */
    public function get_fields($order = 'id DESC',  $start = 0, $count = 10){
        global $wpdb;
        if($count === 0){
            $limit = '';
        }
        else{
            $limit = "LIMIT $start, $count";
        }

        $table_name = $this->parent->get_fields_table_name();
        if(empty($count)){
            $query = "SELECT * FROM `$table_name` ORDER BY $order";
        }
        else{
            $query = "SELECT * FROM `$table_name` ORDER BY $order $limit";
        }

        $fields = $wpdb->get_results($query, ARRAY_A);

        return $fields;

    }

    /**
     * Get fields count
     * @return int
     */
    public function get_fields_count(){
        global $wpdb;
        $table_name = $this->parent->get_fields_table_name();
        $query = "SELECT COUNT(*) as count FROM `$table_name`";
        $count = $wpdb->get_row($query);

        return $count->count;
    }

    /**
     * Return field by name
     * @return array()
     */
    public function get_field_by_name($name){
        global $wpdb;
        $table_name = $this->parent->get_fields_table_name();
        $query = "SELECT * FROM `$table_name` WHERE `field_name` = $name";
        $fields = $wpdb->get_row($query, ARRAY_A);

        return $fields;
    }

    /**
     * Return field name by id
     * @return array()
     */
    public function get_field_by_id($id){
        global $wpdb;
        $table_name = $this->parent->get_fields_table_name();
        $query = "SELECT * FROM `$table_name` WHERE `id` = $id";
        $field = $wpdb->get_row($query, ARRAY_A);

        return $field;
    }

    /**
     * Return fields by type
     * @return array()
     */
    public function get_fields_by_type($type){
        global $wpdb;
        $table_name = $this->parent->get_fields_table_name();
        $query = "SELECT * FROM `$table_name` WHERE `type` = '$type'";
        $fields = $wpdb->get_results($query, ARRAY_A);

        return $fields;

    }

    /**
     * Return last added field
     * @return array()
     */
    public function get_last_added_field(){
        global $wpdb;
        $table_name = $this->parent->get_fields_table_name();
        $result = $wpdb->get_row("SELECT * FROM `$table_name` WHERE id = LAST_INSERT_ID();", ARRAY_A);

        return $result;
    }

    /**
     * Ajax build fields table
     */
    public function ajax_get_fields(){
        $start = isset($_GET['jtStartIndex']) ? $_GET['jtStartIndex'] : 0;
        $count = isset($_GET['jtPageSize']) ? $_GET['jtPageSize'] : 10;
        $order = isset($_GET['jtSorting']) ? $_GET['jtSorting'] : 'id ASC';

        $fields = $this->get_fields($order, $start, $count);
        $j_table_result = array();
        $j_table_result['Result'] = 'OK';
        $j_table_result['TotalRecordCount'] = $this->get_fields_count();
        $j_table_result['Records'] = $fields;
        print json_encode($j_table_result);
        die();
    }

    /**
     * Ajax create field
     */
    public function create_field(){
        if(!empty($_POST)){
            $field_name = $_POST['field_name'];
            $field_label = $_POST['field_label'];
            $type = $_POST['type'];
            $default_value = $_POST['default_value'];

            if(empty($default_value)){
                if($type == 'alphanumeric'){
                    $default_value = '';
                }
                else{
                    $default_value = 0;
                }
            }

            if(!empty($_POST['field_name']) && !empty($_POST['field_label'])){
                $field = $this->get_field_by_name($_POST['field_name']);
                if(empty($field)){
                    $this->field_insert($field_name, $field_label, $type, $default_value);
                    $leads = new Leads($this->parent);
                    $submits = $leads->get_submitted_dates_form_id();

                    foreach($submits as $submit){
                        $leads->lead_insert($submit->submit_time, $submit->form_id, $field_name, $default_value);
                    }

                    $return = $this->get_last_added_field();

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
                    $j_table_result['Message'] = 'Field with this name already exist!';
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
        }
        die();
    }

    /**
     * Ajax edit field
     */
    public function edit_field(){
        if(!empty($_POST)){
            $id = $_POST['id'];
            $field_label = $_POST['field_label'];
            $type = $_POST['type'];
            $default_value = $_POST['default_value'];

            if(empty($default_value)){
                if($type == 'alphanumeric'){
                    $default_value = '';
                }
                else{
                    $default_value = 0;
                }
            }

            if(!empty($_POST['field_label'])){
                $this->field_update($id, $field_label, $type, $default_value);

                $field_name = $this->get_field_by_id($id);
                $leads = new Leads($this->parent);

                $submits = $leads->get_submitted_dates_form_id();

                foreach($submits as $submit){
                    $lead = $leads->get_lead_by_name($field_name['field_name'], $submit->submit_time);
                    if(empty($lead)){
                        $leads->lead_insert($submit->submit_time, $submit->form_id, $field_name['field_name'], $default_value);
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

    /**
     * Ajax delete field
     */
    public function delete_field(){
        if(!empty($_POST['id'])){
            $id = $_POST['id'];
            $this->field_delete($id);

            //Return result to jTable
            $j_table_result = array();
            $j_table_result['Result'] = 'OK';
            print json_encode($j_table_result);
        }
        die();
    }

    /**
     * Insert in fields table
     */
    public function field_insert($field_name, $field_label, $type = 'alphanumeric', $default_value){
        global $wpdb;

        $table_name = $this->parent->get_fields_table_name();

        $query = "INSERT INTO `$table_name` (`field_name`, `field_label`, `type`, `default_value`) VALUES (%s, %s, %s, %s)";
        var_dump($query, $field_name, $field_label, $type, $default_value);
        $wpdb->query($wpdb->prepare($query, $field_name, $field_label, $type, $default_value));
    }

    /**
     * Update field
     */
    public function field_update($id, $field_label, $type = 'alphanumeric', $default_value){
        global $wpdb;

        $table_name = $this->parent->get_fields_table_name();

        $query = "UPDATE `$table_name` SET `field_label` = %s, `type` = %s, `default_value` = %s WHERE `id`=%d";

        $wpdb->query($wpdb->prepare($query, $field_label, $type, $default_value, $id));
    }

    /**
     * Delete field
     */
    public function field_delete($id){
        global $wpdb;

        $table_name = $this->parent->get_fields_table_name();

        $query = "DELETE FROM `$table_name` WHERE `id`=%d";

        $wpdb->query($wpdb->prepare($query, $id));
    }
}