<?php

class Invoice extends CI_Model {

    function __construct()
    {
        // Call the Model constructor
        parent::__construct();
	 
    }

    function get_invoice_public_url($invoice_id){

    	return base_url().'documents/invoice/'. urlencode( base64_encode($invoice_id) );

    }

	function count_invoice_status_updates($invoice_id){
	
		$this->db->select('count(*) as total_updates');
				
		$this->db->where('invoice_status_update_invoice_id', $invoice_id);
		
		$query = $this->db->get('invoice_status_updates');
		
		//echo $this->db->_error_message().$this->db->last_query(); # Debug assist
		
		if($query->num_rows()>0){

			$result = $query->row_object();

			return $result->total_updates;	

		}				
		
	}
	
	function count_invoice_notifications($invoice_id){
	
		$this->db->select('count(*) as total_notifications');
				
		$this->db->where('invoice_notification_invoice_id', $invoice_id);
		
		$query = $this->db->get('invoice_notifications');
		
		//echo $this->db->_error_message().$this->db->last_query(); # Debug assist
		
		if($query->num_rows()>0){

			$result = $query->row_object();

			return $result->total_notifications;	

		}	
	
	}
	
	function send_invoice_notification($invoice_id){

		$this->load->model('Invoice_notification');
	
		$invoice = $this->get_item($invoice_id);
		
		$item = array();
		
		$item['invoice_notification_invoice_id'] = $invoice->invoice_id;
		
		$item['invoice_notification_type'] = $invoice->invoice_status;
		
		$item['invoice_notification_sent'] = db_now();
		
		try{
		
			$create = $this->Invoice_notification->create($item, $invoice);
		
			return true;
		
		}catch(Exception $e){
		
			throw new Exception($this->lang->line($e->getMessage()));
		
		}
	
	}

	function set_payment($invoice_id, $status){

		$item = array();
		
		$item['invoice_paid_date'] = $status?db_now():null;
	
		$this->send_invoice_notification($invoice_id);
	
		return $this->update($invoice_id, $item);
	
	}
	
	function invoices_to_notify(){

		$this->db->select('invoice_id');
	
		$this->db->join('accounts', 'accounts.account_id = invoices.invoice_account_id');
	
		# Conta do cliente ativa
		$this->db->where('accounts.account_blocked_date', null);

		# Faturas nao pagas (pagas notificam instantaneamente com status pending)
		$this->db->where('invoice_paid_date', null);

		# Faturas vencidas
		
		$notify_days_after = $this->System_settings->settings->days_after_invoice_pending_overdue_notification;
		
		$this->db->where('invoice_due_date	<', date('Y-m-d', strtotime("-$notify_days_after days")));		
		
		# Se não tem notificação tipo 2 (overdue charging)
		$this->db->where(' 
			((
				SELECT count(*)invoice_notification_type 
				FROM invoice_notifications 
				WHERE invoice_notification_invoice_id = invoices.invoice_id 
				AND invoice_notification_type = 2
			) = 0)', null, false);		
		
		$query = $this->db->get('invoices');
		
		//echo $this->db->_error_message().$this->db->last_query(); # Debug assist
	
		return $query->result();		
	
	}
	
	function dispatch_notifications(){
	
		$created=0;
		
		$error=false;
	
		$invoice_statuses = $this->get_invoice_statuses();
	
		$invoices_due = $this->invoices_to_notify();
		
		foreach($invoices_due as $invoice){
			
			try{
			
				$this->send_invoice_notification($invoice->invoice_id);
			
				$created++;
			
			} catch(Exception $e) {
			
				$error = $e->getMessage();
			
			}
			
			
		}
		
		$return = new stdClass;
		
		$return->created = $created;
		
		$return->last_error = $error;
		
		return $return;

	
	}
	
	function get_invoice_statuses(){
	
		$invoice_statuses = array(
			0=>"invoice_status_pending",
			1=>"invoice_status_paid",
			2=>"invoice_status_pending_overdue"
			
		);
	
		return $invoice_statuses;
	
	
	}
	
	function get_invoice_status($invoice){
		
		if($invoice->invoice_paid_date){
		
			return 1;
		
		}elseif($invoice->invoice_due_date <= db_now()){
		
			return 2;
		
		}else{
		
			return 0;
		
		}
		
	}
	
	# Index #
	
    function index($invoice_account_id=false, $paginate=true){

		# Security clauses goes here #
	
		# Joins #
		
		$this->db->join('accounts', 'accounts.account_id = invoices.invoice_account_id');
	
		# Filters #

		if($invoice_account_id){
		
			$this->db->where('invoice_account_id', $invoice_account_id);
		
		}		
		
		if($this->input->get('invoice_id')){

			$this->db->where('invoice_id', $this->input->get('invoice_id'));

		}

		if($this->input->get('invoice_recurrency_id')){

			$this->db->where('invoice_recurrency_id', $this->input->get('invoice_recurrency_id'));

		}		
		
		if($this->input->get('invoice_account_id_min')){

			$this->db->where('invoice_account_id >=', $this->input->get('invoice_account_id_min'));

		}

		if($this->input->get('invoice_account_id_max')){

			$this->db->where('invoice_account_id <=', $this->input->get('invoice_account_id_max'));
		}

		if($this->input->get('invoice_amount_min')){

			$this->db->where('invoice_amount >=', $this->input->get('invoice_amount_min'));

		}

		if($this->input->get('invoice_amount_max')){

			$this->db->where('invoice_amount <=', $this->input->get('invoice_amount_max'));

		}

		if($this->input->get('invoice_description')){

			$this->db->like('invoice_description', $this->input->get('invoice_description'));

		}

		if($this->input->get('invoice_created_date_min')>0){

			$this->db->where('invoice_created_date >=', $this->input->get('invoice_created_date_min'));

		}

		if($this->input->get('invoice_created_date_max')>0){

			$this->db->where('invoice_created_date <=', $this->input->get('invoice_created_date_max'));

		}

		if($this->input->get('invoice_due_date_min')>0){

			$this->db->where('invoice_due_date >=', $this->input->get('invoice_due_date_min'));

		}

		if($this->input->get('invoice_due_date_max')>0){

			$this->db->where('invoice_due_date <=', $this->input->get('invoice_due_date_max'));

		}

		if($this->input->get('invoice_paid_date_min')>0){

			$this->db->where('invoice_paid_date >=', $this->input->get('invoice_paid_date_min'));

		}

		if($this->input->get('invoice_paid_date_max')>0){

			$this->db->where('invoice_paid_date <=', $this->input->get('invoice_paid_date_max'));

		}

		#Orderby

		if($this->input->get('order_by')){

			$this->db->order_by($this->input->get('order_by'));

		}else{
		
			$this->db->order_by('invoice_id desc');
		
		}

		#Limit & Offset
		if($paginate){

			$this->db->limit(get_limit());

			$this->db->offset($this->input->get('offset'));

		}

		#Performs query
		
		$query = $this->db->get('invoices');
		
		# echo $this->db->_error_message().$this->db->last_query(); # Debug assist
		
		#Return
		$return = array();
		
		$array = $query->result();
		
		foreach ($array as $invoice){
			
			$invoice->total_notifications = $this->count_invoice_notifications($invoice->invoice_id);
			
			$invoice->total_status_updates = $this->count_invoice_status_updates($invoice->invoice_id);
			
			$return[] = $invoice;
		
		}
		
		//print_r($return);
		
		return $return;

    } 	


	# Create #

	function create($item){

		$item['invoice_created_date'] = db_now();
	
		$insert = $this->db->insert('invoices', $item);

		if($id = $this->db->insert_id()){
		
			return $id;

		}else if($this->db->_error_message()){

			throw new Exception($this->db->_error_message());

		}

	}


	# Update #

	function update($invoice_id, $item){

		$this->db->where('invoice_id', $invoice_id);

		$this->db->update('invoices', $item);

		if($this->db->affected_rows()>0){

			return $item;

		}else if($this->db->_error_message()){

			throw new Exception($this->db->_error_message());

		}

	}


	# Get item #

	function get_item($invoice_id){

		$this->load->model('account');
	
		$this->db->join('recurrencies', 'recurrencies.recurrency_id = invoices.invoice_recurrency_id', 'left');
	
		$this->db->join('accounts', 'accounts.account_id = invoices.invoice_account_id');

		$query = $this->db->get_where('invoices', array('invoice_id' => $invoice_id), 1);

		if($query->num_rows()>0){

			$result = $query->row_object();

			$result->invoice_status = $this->get_invoice_status($result);
			
			#Adiciona os atributos já formatados ao item
			
			$item = new stdClass;
			
			foreach($result as $key => $value){
			
				$item->$key = $value;
			
				if($key === 'invoice_id'){$item->formatted_invoice_id = format_id($value);}
				
				if($key === 'invoice_created_date'){$item->formatted_invoice_created_date = human_date($value);}
				
				if($key === 'invoice_due_date'){$item->formatted_invoice_due_date = human_date($value);}
				
				if($key === 'invoice_paid_date'){$item->formatted_invoice_paid_date = human_date($value);}
							
				#Adiciona o link para acesso direto pela área do cliente
				
				$item->invoice_public_url = $this->get_invoice_public_url($item->invoice_id);

				$item->account_public_url = $this->account->get_account_public_url($result->invoice_account_id);
				
			}
			
			return $item;

		}

	}

	# Remove #

    function remove($invoice_id){

		$this->db->where('invoice_id', $invoice_id);

		$this->db->delete('invoices'); 

		if($this->db->affected_rows()>0){

			return $invoice_id;

		}else if($this->db->_error_message()){

			throw new Exception($this->db->_error_message());

		}

    }

}


/* End of file invoice.php */
/* Location: ./application/models/invoice.php */