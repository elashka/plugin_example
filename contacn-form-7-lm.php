<?php
/*
   Plugin Name: Contact Form 7 Lead Manager
   Plugin URI:
   Version: 1.0
   Author: Yevheniia Lashkevich
   Description: Lead management  for Contact Form 7 plugin
*/

include_once( 'classes/fields.class.php' );
include_once( 'classes/leads.class.php' );
include_once( 'classes/export.class.php' );

$cf7lm = new Cf7lm();


class Cf7lm
{
    public $dir;
    public $path;
    public $prefix = 'cf7lm';

    /*
    *  Constructor
    */

    public function __construct()
    {

        // vars
        $this->path = plugin_dir_path(__FILE__);
        $this->dir = plugins_url('', __FILE__);

        register_activation_hook(__FILE__,  array($this, 'install'));
        // actions
        add_action('admin_menu', array($this, 'admin_menu'));

        add_action('init', array($this, 'save_refer_url'));

        add_action( 'admin_init', array($this, 'add_capability'));

        // scripts
        wp_register_script( 'cf7ml_ajax', $this->dir );

        wp_localize_script( 'jquery', 'cf7ml_ajax', array( 'cf7ml_ajax' => admin_url( 'admin-ajax.php' ) ) );

        $leads = new Leads($this);

        $fields = new Fields($this);

        $export = new Export($this);

        return true;
    }


    /*
     * Install
     */
    public function install() {
        global $wpdb;
        $tableName = $this->get_submits_table_name();

        $wpdb->query("CREATE TABLE IF NOT EXISTS `$tableName` (
                    `submit_time` DATETIME NOT NULL,
                    `form_id` INT(11),
                    `field_name` VARCHAR(127),
                    `field_value` LONGTEXT CHARACTER SET utf8,
                    `file` LONGBLOB)
                    ");
        $wpdb->query("ALTER TABLE `$tableName` ADD INDEX `submit_time_idx` ( `submit_time` )");
        $wpdb->query("ALTER TABLE `$tableName` ADD INDEX `form_name_idx` ( `form_name` )");
        $wpdb->query("ALTER TABLE `$tableName` ADD INDEX `field_name_idx` ( `field_name` )");

        $tableNameFields = $this->get_fields_table_name();

        $wpdb->query("CREATE TABLE IF NOT EXISTS `$tableNameFields` (
                    `id` INT(11) NOT NULL AUTO_INCREMENT,
                    `field_name`  VARCHAR(255) CHARACTER SET utf8,
                    `field_label` VARCHAR(255) CHARACTER SET utf8,
                    `type` VARCHAR(127),
                    `default_value` VARCHAR(255) CHARACTER SET utf8,
                    PRIMARY KEY (`id`))
                    ");

        $tableFieldsCompare = $this->get_fields_compare_table_name();

        $wpdb->query("CREATE TABLE IF NOT EXISTS `$tableFieldsCompare` (
                    `id` INT(11) NOT NULL,
                    `cf7_name`  VARCHAR(255),
                    `lm_name` VARCHAR(255)
                    ");
    }

    /**
     * @return string
     */

    public function get_submits_table_name() {
        global $wpdb;
        return $wpdb->prefix . $this->prefix . '_submits';
    }

    /**
     * @return string
     */

    public function get_fields_table_name() {
        global $wpdb;
        return $wpdb->prefix . $this->prefix . '_custom_fields';
    }

    /**
     * @return string
     */

    public function get_fields_compare_table_name() {
        global $wpdb;
        return $wpdb->prefix . $this->prefix . '_fields_compare';
    }

    /**
     * @return string
     */

    public function get_upload_files_dir() {
        $upload_info = wp_upload_dir();
        return $upload_info['basedir'] . '/cf7lm_files';
    }

    /**
     *  admin_menu
     */

    public function admin_menu() {
        add_menu_page(__('CF7 Lead Management', 'cf7ml'), __('CF7 Lead Management','cf7ml'), 'edit_lead_management', 'lead-management', '', $this->dir . '/img/icon.png');
    }

    /**
     *  wp_head hook
     *  save refer link
     */

    public function save_refer_url() {
        $domain = site_url();
        // If the referrer is from outside the domain, store the url
        if(substr($_SERVER['HTTP_REFERER'], 0, strlen($domain)) !== $domain) {
            if(!isset($_COOKIE['refer_link'])) {
                setcookie('refer_link', $_SERVER['HTTP_REFERER'], time()+24*60*60);
            }
        }
    }

    /**
     *  add capability
     */

    public function add_capability() {
        add_role('lead_management_editor', 'Lead Manager', array('read'=> true));

        $role =& get_role('lead_management_editor');
        $role->add_cap( 'edit_lead_management', '1' );

        $role =& get_role('administrator');
        $role->add_cap( 'edit_lead_management', '1' );

    }

}