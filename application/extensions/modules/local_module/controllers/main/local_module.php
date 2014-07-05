<?php if ( ! defined('BASEPATH')) exit('No direct access allowed');

class Local_module extends MX_Controller {

	public function __construct() {
		parent::__construct(); 																	// calls the constructor
		$this->load->library('location'); 														// load the location library
		$this->load->library('currency'); 														// load the location library
		$this->load->library('language');
		$this->lang->load('local_module/local_module', $this->language->folder());
	}

	public function index() {
		if ( ! file_exists(EXTPATH .'modules/local_module/views/main/local_module.php')) { 								//check if file exists in views folder
			show_404(); 																		// Whoops, show 404 error page!
		}
		
		$data['local_page'] = ($this->uri->segment(1) === 'local') ? TRUE : FALSE;
		
		if ($this->session->flashdata('local_alert')) {
			$data['local_alert'] = $this->session->flashdata('local_alert');  								// retrieve session flashdata variable if available
		} else {
			$data['local_alert'] = '';
		}

		// START of retrieving lines from language file to pass to view.
		$data['text_heading'] 			= $this->lang->line('text_heading');
		$data['text_local'] 			= $this->lang->line('text_local');
		$data['text_find'] 				= $this->lang->line('text_find');
		$data['text_postcode'] 			= ($this->config->item('search_by') === 'postcode') ? $this->lang->line('entry_postcode') : $this->lang->line('entry_address');
		$data['text_delivery_charge'] 	= $this->lang->line('text_delivery_charge');
		$data['text_min_total'] 		= $this->lang->line('text_min_total');
		$data['text_order_type'] 		= $this->lang->line('text_order_type');
		$data['text_avail'] 			= $this->lang->line('text_avail');
		$data['text_more_info'] 		= $this->lang->line('text_more_info');

		$data['button_view_map'] 		= $this->lang->line('button_view_map');
		$data['button_check_postcode'] 	= $this->lang->line('button_check_postcode');
		// END of retrieving lines from language file to send to view.

		$data['info_url'] 			= site_url('main/local');

		if ($this->config->item('maps_api_key')) {
			$data['map_key'] = '&key='. $this->config->item('maps_api_key');
		} else {
			$data['map_key'] = '';
		}

		$local_info = $this->session->userdata('local_info');
		if (isset($local_info['search_query'])) {
			$data['postcode'] = $local_info['search_query'];
		} else {
			$data['postcode'] = '';
		}

		if (isset($local_info['order_type'])) {
			$data['order_type'] = $local_info['order_type'];
		} else {
			$data['order_type'] = '';
		}

		$data['local_info'] = $this->location->local(); 										// retrieve local location data
		$data['location_name'] = $this->location->getName(); 										// retrieve local location data
		$data['location_address'] = $this->location->getAddress(); 										// retrieve local location data

		$data['distance'] = $this->location->distance(); //format diatance to 2 decimal place

		if ($this->location->isOpened()) { 														// checks if local location is open
			$data['text_open_or_close'] = $this->lang->line('text_opened');						// display we are open
		} else {
			$data['text_open_or_close'] = $this->lang->line('text_closed');						// display we are closed
		}

		$check_delivery = $this->location->checkDelivery();
		$check_collection = $this->location->checkCollection();
		
		if (!$check_delivery AND $check_collection) { 														// checks if cart contents is empty  
			$data['text_delivery'] = $this->lang->line('text_collection');
		} else if ($check_delivery AND !$check_collection) {
			$data['text_delivery'] = $this->lang->line('text_delivery');
		} else if ($check_delivery AND $check_collection) {
			$data['text_delivery'] = $this->lang->line('text_both_types');						// display we are open
		} else {
			$data['text_delivery'] = $this->lang->line('text_no_types');
		}
		
		if ($check_delivery AND !$this->location->checkDeliveryCoverage()) {		
			$data['text_covered'] = $this->lang->line('text_covered_area');		
		}

		if ($this->location->getDeliveryCharge() > 0) {
			$data['delivery_charge'] = $this->currency->format($this->location->getDeliveryCharge());
		} else {
			$data['delivery_charge'] = $this->lang->line('text_free');
		}
		
		if ($this->location->getMinTotal() > 0) {
			$data['min_total'] = $this->currency->format($this->location->getMinTotal());
		} else {
			$data['min_total'] = $this->lang->line('text_none');
		}
		
		$this->load->model('Reviews_model');
		$total_reviews = $this->Reviews_model->getTotalLocationReviews($this->location->getId());
		$data['text_total_review'] = sprintf($this->lang->line('text_total_review'), $total_reviews);

		// pass array $data and load view files
		$this->load->view('local_module/main/local_module', $data);
	}		

	public function distance() {
		$this->load->library('user_agent');
		$json = array();
		$error = 0;
		
		$this->session->unset_userdata('local_info');
		$output = $this->location->getLatLng($this->input->post('postcode'), TRUE);
		
		if ($output === 'NO_SEARCH_QUERY') {															// check if geocoding is not successful
			$error = 1;
		} else if ($output === 'ENTER_POSTCODE') {															// check if geocoding is not successful
			$error = 2;
		} else if ($output === 'FAILED') {															// check if geocoding is not successful
			$error = 3;
		} else if (is_array($output)) {							// validate $_POST postcode data using getLatLng() method and return latitude and longitude if successful
			$search_query	= $output['search_query'];
			$lat 			= $output['lat'];																// store the latitute from geocode data in variable
			$lng 			= $output['lng'];																// store the longitude from geocode data in variable
		}
		
		$json['error'] = '';
		switch ($error) {
		case 1:
			$json['error'] = $this->lang->line('error_no_postcode');
			break;
		case 2:
			$json['error'] = $this->lang->line('error_enter_postcode');	// display error: enter postcode
			break;
		case 3:
			$json['error'] = $this->lang->line('error_failed');				// display error: invalid postcode
			break;
		default:
			if ( ! $json) {
				$local_info = $this->Locations_model->getLocalRestaurant($lat, $lng, $search_query);
				if ( ! $local_info) {					// check if longitude and latitude doesnt have a nearest local restaurant from getLocalRestaurant() method in Locations model.
					$json['error'] = $this->lang->line('error_no_restaurant');	// display error: no available restaurant
				} else {
					if (is_numeric($this->input->post('order_type'))) {
						$local_info['order_type'] = $this->input->post('order_type');
					} else {
						$local_info['order_type'] = '1';
					}

					$this->session->set_userdata('local_info', $local_info);
				}
			}
			break;	
		}
		
		if ($this->agent->referrer() == site_url() OR $this->agent->referrer() == site_url('home')) {
			$redirect = site_url('menus');
		} else {
			$redirect = $this->agent->referrer();
		}
		
		if ($this->input->is_ajax_request()) {
			$this->output->set_output(json_encode($json));											// encode the json array and set final out to be sent to jQuery AJAX
		} else {
			$this->session->set_flashdata('local_alert', $json['error']);
			redirect($redirect);
		}
	}
}

/* End of file local_module.php */
/* Location: ./application/extensions/modules/local_module/controllers/main/local_module.php */