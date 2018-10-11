<?php
/**
 * Export class
 *
 * DISCLAIMER
 *
 * @package             Contact Form 7 Lead Manager
 * @author              Evgeniya Lashkevich
 */


class Export{

    public $parent;

    public function __construct($parent) {

        $this->parent = $parent;
        // Add the Admin Config page for this plugin

        // Ajax export
        add_action('wp_ajax_ajax_export', array(&$this, 'ajax_export'));
    }


    /*
     * Ajax export
     */
    public function ajax_export(){

        $this->export();
        die();
    }

    /*
     *  Export
     */
    public function export() {
        // Headers
        $charSet = 'UTF-8';

        $this->echo_headers(
            array("Content-Type: text/csv; charset=$charSet",
                "Content-Disposition: attachment; filename=Lead Management.csv"));
		 echo chr(239) . chr(187) . chr(191);

        $this->echo_csv();
    }

    /*
     * @param string|array|null $headers mixed string header-string or array of header strings.
     * E.g. Content-Type, Content-Disposition, etc.
     * @return void
     */
    protected function echo_headers($headers = null) {
        if (!headers_sent()) {
            header('Expires: 0');
            header('Cache-Control: no-store, no-cache, must-revalidate');
            // Hoping to keep the browser from timing out if connection from Google SS Live Data
            // script is calling this page to get information
            header("Keep-Alive: timeout=60"); // Not a standard HTTP header; browsers may disregard

            if ($headers) {
                if (is_array($headers)) {
                    foreach ($headers as $aheader) {
                        header($aheader);
                    }
                }
                else {
                    header($headers);
                }
            }
            flush();
        }
    }

    /*
     *  Generate csv file
     */

    public function echo_csv() {
        $eol = "\r\n";

        $header['submit_time'] = 'Submitted';
        $header['form_id'] = 'Form ID';
        $header['URL'] = 'URL';
        $header['IP'] = 'IP';
        $header['tag'] = 'Tag';
        $header['refer_url'] = 'Referring URL';

        $custom_fields = new Fields($this->parent);
        $leads = new Leads($this->parent);
        $all_fields = $custom_fields->get_fields('id DESC', 0, 0);

        foreach ($all_fields as $field){
            $header[$field['field_name']] = $field['field_label'];
        }

        $all_match = $leads->get_distinct_lm_names();
        if(!empty($all_match)){
            foreach($all_match as $lm_name){
                if(!empty($lm_name['lm_name'])){
                    $header[$lm_name['lm_name']] = $lm_name['lm_name'];
                }
            }
        }

        print implode(',', $header) . $eol;

        $criteria = $leads->get_search_criteria();

        $order = 'submit_time DESC';
        $where = $criteria['where'];

        $rows = $leads->get_leads_array($order, 0, 0, $where);

        foreach($rows as $row){
            foreach($header as $key => $title){
                $str = str_replace('"', "'", $row[$key]);
                $str = str_replace("\r\n", ' ', $str);
                $str = str_replace("\n", ' ', $str);
                print '"' . $str . '",';
            }
            print $eol;
        }

    }
}