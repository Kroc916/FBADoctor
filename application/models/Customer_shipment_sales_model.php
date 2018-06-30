<?php
/*
 * Generated by Webdimensions
 * www.webdimensions.co.in
 */

class Customer_shipment_sales_model extends CI_Model
{

    function __construct()
    {
        parent::__construct();
    }

    //get user details for return order cron
    function get_customer_sales_feedback_setting()
    {
        $t = time();
        $last24hours = $t - (1 * 9 * 60 * 60);
        $result = $this->db->query("
	SELECT a.ID_ACCOUNT, s.mws_sellerid, s.mws_authtoken, s.loadfirstreturnorder, s.amazonstorename,s.importorderdays,
        d.marketplace_id, d.access_key, d.secret_key, m.marketplace_id, m.host, m.id
        FROM accounts AS a, feedback_settings AS s, dev_accounts AS d, marketplaces AS m
	WHERE m.id= d.marketplace_id AND s.mws_marketplaceid = m.id AND s.ID_ACCOUNT = a.ID_ACCOUNT  and s.apiactive = 1 and a.deleted='0' AND a.removed = 0 AND a.enabled = 1 AND s.hourlimit_customershipmentsale < 6 AND (s.api_customershipmentsaledate  = 0 OR s.api_customershipmentsaledate < $last24hours)
        ");
        return $result;
    }

    function get_customer_sales_feedback_setting_historical()
    {
        $result = $this->db->query("
	SELECT a.ID_ACCOUNT, s.mws_sellerid, s.mws_authtoken, s.loadfirstreturnorder, s.amazonstorename,s.importorderdays,
        d.marketplace_id, d.access_key, d.secret_key, m.marketplace_id, m.host, m.id
        FROM accounts AS a, feedback_settings AS s, dev_accounts AS d, marketplaces AS m
	WHERE m.id= d.marketplace_id AND s.mws_marketplaceid = m.id AND s.ID_ACCOUNT = a.ID_ACCOUNT  and s.apiactive = 1 and a.deleted='0' AND a.removed = 0 AND a.enabled = 1   ");
        return $result;
    }

    function add_customer_shipement_sales($orderParam){
            $this->db->insert('customer_shipment_data', $orderParam);
        return $this->db->insert_id();
    }

    function custom_count_feedback_order($params){
        $countTotal = $this->db->get_where('customer_shipment_data', $params);
        return $countTotal->num_rows();
    }

    function historycronstatusupdate_data($data, $accountID){
        $this->db->where('ID_ACCOUNT', $accountID);
        return $this->db->update('history_cron_status', $data);
    }

    function historycronstatusupdate($data, $accountID){
        $this->db->where('ID_ACCOUNT', $accountID);
        return $this->db->update('history_cron_status', $data);
    }

}