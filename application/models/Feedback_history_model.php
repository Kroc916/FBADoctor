<?php
/*
 * Generated by Webdimensions
 * www.webdimensions.co.in
 */

class Feedback_history_model extends CI_Model {

    function __construct() {
        parent::__construct();
    }

    /*
     * Get feedback_history_setting
     */

    function get_feedback_setting() {
        $t           = time();
        $last24hours = $t - (1 * 24 * 60 * 60);
        $result      = $this->db->query("
	SELECT a.ID_ACCOUNT, s.mws_sellerid, s.mws_authtoken, s.loadedfirstorderdata, s.amazonstorename,
        d.marketplace_id, d.access_key, d.secret_key, m.marketplace_id, m.host, m.id
        FROM accounts AS a, feedback_settings AS s, dev_accounts AS d, marketplaces AS m
	WHERE m.id= d.marketplace_id AND s.mws_marketplaceid = m.id AND s.ID_ACCOUNT = a.ID_ACCOUNT
        ");
        return $result;
    }

    /*
     * Get feedback_history by ID_ACCOUNT
     */

    function add_feedback_order($params) {
        $this->db->insert('feedback_orders', $params);
        return $this->db->insert_id();
    }
    /*
     * add feedback data 
     */
     function insert_feedback_data($params) {
        $this->db->insert('feedback_history', $params);
        return $this->db->insert_id();
    }

    /*
     * Get feedback_history count by ID_ACCOUNT
     */

    function custom_count_feedback_history($params) {
        $countTotal = $this->db->get_where('feedback_history', $params);
        return $countTotal->num_rows();
    }
    
    function custom_where_update_amazon_feedback_history($wherecondition,$params)
    {
        $this->db->where($wherecondition);
        $this->db->update('feedback_history',$params);
    }

    function updatependingemail($orderid){
        $params = array('sent' => '2');
        $this->db->select('*');
        $this->db->from('feedback_orders as fborders');
        $this->db->join('feedback_pendingemails as fbpendingemail', 'fbpendingemail.ID_ORDER = fborders.ID_ORDER');
        $this->db->where('orderid',$orderid);
        $this->db->update('feedback_pendingemails', $params);
    }
}
