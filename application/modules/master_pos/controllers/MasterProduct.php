<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
class MasterProduct extends MY_Controller {
	
	public $table;
		
	function __construct()
	{
		parent::__construct();
		$this->prefix = config_item('db_prefix2');
		$this->load->model('model_masterproduct', 'm');
	}
	
	//important for service load
	function services_model_loader(){
		$this->prefix = config_item('db_prefix2');
		$dt_model = array( 'm' => '../../master_pos/models/model_masterproduct');
		return $dt_model;
	}

	public function gridData()
	{
		$this->table = $this->prefix.'product';
		$this->product_img_url = RESOURCES_URL.'product/thumb/';
		
		//is_active_text
		$sortAlias = array(
			'is_active_text' => 'a.is_active'
		);		
		
		// Default Parameter
		$params = array(
			'fields'		=> 'a.*, b.product_category_name, c.id as item_id, c.item_code',
			'primary_key'	=> 'a.id',
			'table'			=> $this->table.' as a',
			'join'			=> array(
									'many', 
									array( 
										array($this->prefix.'product_category as b','b.id = a.category_id','LEFT'),
										array($this->prefix.'items as c','c.id = a.id_ref_item','LEFT')
									) 
								),
			'where'			=> array('a.is_deleted' => 0),
			'order'			=> array('a.id' => 'DESC'),
			'sort_alias'	=> $sortAlias,
			'single'		=> false,
			'output'		=> 'array' //array, object, json
		);
		
		//DROPDOWN & SEARCHING
		$is_dropdown = $this->input->post('is_dropdown');
		$searching = $this->input->post('query');
		$category_id = $this->input->post('category_id');
		$keywords = $this->input->post('keywords');
		$is_active = $this->input->post('is_active');
		$by_code = $this->input->post('by_code');
		if(!empty($keywords)){
			$searching = $keywords;
		}
		
		if(!empty($is_dropdown)){
			$params['order'] = array('a.product_desc' => 'ASC');
		}
		if(!empty($searching)){
			$params['where'][] = "(a.product_name LIKE '%".$searching."%' OR b.product_category_name LIKE '%".$searching."%' OR c.item_code LIKE '%".$searching."%')";
		}
		if(!empty($category_id)){
			$params['where'][] = "a.category_id = ".$category_id;
		}
		
		if(!empty($is_active)){
			
			if($is_active == 1){
				$params['where'][] = array('a.is_active' => 1);
			}
			
		}else{
			
			if(is_numeric($is_active)){
				$params['where'][] = array('a.is_active' => 0);
			}
			
		}
		
		
		//get data -> data, totalCount
		$get_data = $this->m->find_all($params);
		
		
		//cek opt
		$get_opt = get_option_value(array('hide_compliment_order'));
  		$hide_compliment_order = 0;
		if(!empty($get_opt['hide_compliment_order'])){
			$hide_compliment_order = 1;
		}
		
		//GET PROMO
		$dt_promo = array();
		$dt_promo_id = array();
		$promo_diskon_data_product = array();
		
		$this->db->select('*');
		$this->db->from($this->prefix.'discount');
		$this->db->where('(discount_type = 0 OR discount_type = 2) AND is_promo = 1');
		$this->db->where('is_active = 1');
		$this->db->where('is_deleted = 0');
		
		$today_date = date("Y-m-d H:i:s");
		$this->db->where("(discount_date_type = 'unlimited_date' OR (discount_date_type = 'limited_date' AND ('".$today_date."' BETWEEN date_start AND date_end)))");
			
		$get_promo = $this->db->get();
		
		$today_in_no = date("N");
		if($get_promo->num_rows() > 0){
			foreach($get_promo->result() as $dt){
				
				$allowed_promo = false;
				//check in day
				if($dt->discount_allow_day >= 1 AND $dt->discount_allow_day <= 7){
					if($today_in_no == $dt->discount_allow_day){
						$allowed_promo = true;
					}
				}else
				if($dt->discount_allow_day == 8){
					//weekday
					if($today_in_no >= 1 AND $today_in_no <= 5){
						$allowed_promo = true;
					}
				}else
				if($dt->discount_allow_day == 9){
					//weekend
					if($today_in_no >= 6 AND $today_in_no <= 7){
						$allowed_promo = true;
					}
				}else
				{
					//every day
					$allowed_promo = true;
				}
				
				if($allowed_promo){
					
					$allowed_time = true;
					if($dt->use_discount_time == 1){
						
						$allowed_time = false;
						
						if($dt->discount_time_end == '12:00 AM'){
							$dt->discount_time_end = '11:59 PM';
						}
						
						$time_from = date("d-m-Y")." ".$dt->discount_time_start;
						$time_till = date("d-m-Y")." ".$dt->discount_time_end;
						
						$time_from_mk = strtotime($time_from);
						$time_till_mk = strtotime($time_till);
						
						$time_now = strtotime(date("d-m-Y H:i:s"));
						
						
						
						if($time_now >= $time_from_mk AND $time_now <= $time_till_mk){
							$allowed_time = true;
						}
						
						//echo "allowed_time=".$allowed_time.", $time_from_mk=".$time_from_mk.", $time_till_mk=".$time_till_mk.", $time_now=".$time_now;
						//die();
						
					}
					
					if($allowed_time){
						if(!in_array($dt->id, $dt_promo_id)){
							$dt_promo_id[] = $dt->id;
							$dt_promo[$dt->id] = $dt;
							
							if(empty($promo_diskon_data_product[$dt->id]) AND $dt->discount_type == 0){
								$promo_diskon_data_product[$dt->id] = array();
							}
							
						}
					}
					
				}
				
			}
		}
		
		
		//DISKON PRODUCT
		$promo_diskon_product_id = array();
		$promo_diskon_product = array();
		$all_on_promo = false;
		$all_on_promo_id = 0;
		
		if(!empty($dt_promo_id)){
			$dt_promo_id_sql = implode(",", $dt_promo_id);
			$this->db->select('*');
			$this->db->from($this->prefix.'discount_product');
			$this->db->where('discount_id IN ('.$dt_promo_id_sql.')');
			$get_promo_diskon = $this->db->get();
			
			if($get_promo_diskon->num_rows() > 0){
				foreach($get_promo_diskon->result() as $dt){
					if(!in_array($dt->product_id, $promo_diskon_product_id)){
						$promo_diskon_product_id[] = $dt->product_id;
						$promo_diskon_product[$dt->product_id] = $dt->discount_id;
						
						$promo_diskon_data_product[$dt->discount_id][] = $dt->product_id;
						
					}
				}
				
			}
			
		}
		
		if(!empty($promo_diskon_data_product)){
			foreach($promo_diskon_data_product as $disc_id => $dt_prod){
				if(empty($dt_prod) AND $all_on_promo == false){
					//$all_on_promo = true;
					//$all_on_promo_id = $disc_id;
				}
			}
		}
		
		//echo '<pre>'.$all_on_promo.' == '.$all_on_promo_id;
		//print_r($promo_diskon_data_product);
		//die();
		
		//DISKON BUY & GET
		/*
		$promo_buyget_product_id = array();
		$promo_buyget_product = array();
		
		if(!empty($dt_promo_id)){
			$dt_promo_id_sql = implode(",", $dt_promo_id);
			$this->db->select('*');
			$this->db->from($this->prefix.'discount_buyget');
			$this->db->where('id IN ('.$dt_promo_id_sql.')');
			$get_promo_buyget = $this->db->get();
			
			if($get_promo_buyget->num_rows() > 0){
				foreach($get_promo_buyget->result() as $dt){
					if(!in_array($dt->product_id, $promo_diskon_product_id)){
						if(!in_array($dt->product_id, $promo_buyget_product_id)){
							$promo_buyget_product_id[] = $dt->product_id;
							$promo_buyget_product[$dt->product_id] = $dt;
						}
					}
				}
			}
			
		}
		*/
		
  		$newData = array();
		if(!empty($get_data['data'])){
			foreach ($get_data['data'] as $s){
				
				if(empty($s['product_image'])){
					$s['product_image'] = 'no-image.jpg';
				}
				if(empty($s['normal_price'])){
					$s['normal_price'] = $s['product_price'];
					$s['normal_price'] = $s['product_price'];
				}
				$s['product_image_show'] = '<img src="'.$this->product_img_url.$s['product_image'].'" style="max-width:80px; max-height:60px;"/>';
				$s['product_image_src'] = $this->product_img_url.$s['product_image'];
				$s['is_active_text'] = ($s['is_active'] == '1') ? '<span style="color:green;">Active</span>':'<span style="color:red;">Inactive</span>';
				$s['product_price_show'] = priceFormat($s['product_price']);
				$s['normal_price_show'] = priceFormat($s['normal_price']);
				
				$s['hide_compliment_order'] = $hide_compliment_order;
				$s['product_name_show'] = $s['product_name'];
				
				$s['product_id'] = $s['id'];
				$s['product_price_hpp'] = $s['product_hpp'];
				$s['product_normal_price'] = $s['normal_price'];
				
				//SET PROMO
				$s['promo_tipe'] = 0; //1 product, 2 buy and get
				$s['promo_id'] = 0;
				$s['is_promo'] = 0;
				$s['promo_percentage'] = 0;
				$s['promo_price'] = 0;
				$s['promo_desc'] = '';
				$no_promo = true;
				$usePromoID = 0;
				
				if(!empty($promo_diskon_product[$s['id']])){
					$usePromoID = $promo_diskon_product[$s['id']];
					$no_promo = false;
				}
				
				if($no_promo == true AND $all_on_promo){
					$usePromoID = $all_on_promo_id;
				}
				
				if(!empty($dt_promo[$usePromoID])){
					
					$s['promo_id'] = $usePromoID;
					
					$s['promo_tipe'] = 1;
					$s['is_promo'] = 1;
					$s['promo_percentage'] = $dt_promo[$usePromoID]->discount_percentage;
					$s['promo_desc'] = $dt_promo[$usePromoID]->discount_name;
					
					$promo_price = ($s['product_price'] * ($s['promo_percentage']/100));
					$product_price = $s['product_price'] - $promo_price;
					$s['product_price'] = $product_price;
					$s['promo_price'] = $promo_price;
					$s['promo_price_show'] = priceFormat($s['promo_price']);
					$s['product_name_show'] = $s['product_name'].' <font color="orange">Promo</font>';
					$s['product_price_show'] = '<strike>'.$s['product_price_show'].'</strike> <font color="orange">'.priceFormat($s['product_price']).'</font>';
					
				}	
				
				$s['is_kerjasama_text'] = ($s['is_kerjasama'] == '1') ? '<span style="color:green;">Yes</span>':'<span style="color:red;">No</span>';
				$s['total_bagi_hasil_show'] = priceFormat($s['total_bagi_hasil']);
				
				array_push($newData, $s);
			}
		}
		
		$get_data['data'] = $newData;
		
      	die(json_encode($get_data));
	}
	
	/*SERVICES*/
	public function save()
	{
		$this->table = $this->prefix.'product';				
		$this->table2 = $this->prefix.'product_package';				
		$session_user = $this->session->userdata('user_username');
		
		
		$product_name = $this->input->post('product_name');
		$product_chinese_name = $this->input->post('product_chinese_name');
		$product_desc = $this->input->post('product_desc');
		$product_price = $this->input->post('product_price');
		$normal_price = $this->input->post('normal_price');
		$product_hpp = $this->input->post('product_hpp');
		$category_id = $this->input->post('category_id');
		$product_type = $this->input->post('product_type');
		$old_product_type = $this->input->post('old_product_type');
		$product_image = $this->input->post('product_image');
		$product_group = $this->input->post('product_group');
		//$use_tax = $this->input->post('use_tax');
		//$use_service = $this->input->post('use_service');
		
		/*CONTENT IMAGE UPLOAD SIZE*/		
		$this->product_img_url = RESOURCES_URL.'product/';		
		$this->product_img_path_big = RESOURCES_PATH.'product/big/';
		$this->product_img_path_thumb = RESOURCES_PATH.'product/thumb/';
		$this->product_img_path_tiny = RESOURCES_PATH.'product/tiny/';
		
		$big_size_width = 1024;
		$big_size_height = 768;
		$thumb_size_width = 375;
		$thumb_size_height = 250;
		$tiny_size_width = 160;
		$tiny_size_height = 120;
		
		$opt_var = array('big_size_width','big_size_height','big_size_real',
		'thumb_size_width','thumb_size_height',
		'tiny_size_width','tiny_size_height');
		$get_opt = get_option_value($opt_var);
		
		$big_size_real = 0;
		if(!empty($get_opt['big_size_real'])){
			$big_size_real = $get_opt['big_size_real'];
		}
		if(!empty($get_opt['big_size_width'])){
			$big_size_width = $get_opt['big_size_width'];
		}
		if(!empty($get_opt['big_size_height'])){
			$big_size_height = $get_opt['big_size_height'];
		}
		if(!empty($get_opt['thumb_size_width'])){
			$thumb_size_width = $get_opt['thumb_size_width'];
		}
		if(!empty($get_opt['thumb_size_height'])){
			$thumb_size_height = $get_opt['thumb_size_height'];
		}
		if(!empty($get_opt['tiny_size_width'])){
			$tiny_size_width = $get_opt['tiny_size_width'];
		}
		if(!empty($get_opt['tiny_size_height'])){
			$tiny_size_height = $get_opt['tiny_size_height'];
		}
		
		
		$is_upload_file = false;		
		if(!empty($_FILES['upload_image']['name'])){
						
			$config['upload_path'] = $this->product_img_path_big;
			$config['allowed_types'] = 'gif|jpg|png';
			$config['max_size']	= '1024000';

			$this->load->library('upload', $config);

			if(!$this->upload->do_upload("upload_image"))
			{
				$data = $this->upload->display_errors();
				$r = array('success' => false, 'info' => $data );
				die(json_encode($r));
			}
			else
			{
				$is_upload_file = true;
				$data_upload_temp = $this->upload->data();
				$r = array('success' => true, 'info' => $data_upload_temp); 
			}
		}
		
		
		if(empty($product_name)){
			$r = array('success' => false);
			die(json_encode($r));
		}		
		
		$is_active = $this->input->post('is_active');
		if(empty($is_active)){
			$is_active = 0;
		}
		
		$use_tax = $this->input->post('use_tax');
		if(empty($use_tax)){
			$use_tax = 0;
		}
		
		$use_service = $this->input->post('use_service');
		if(empty($use_service)){
			$use_service = 0;
		}
		
		if(empty($normal_price)){
			$normal_price = $product_price;
		}
			
		$r = '';
		if($this->input->post('form_type_masterProduct', true) == 'add')
		{
			$var = array(
				'fields'	=>	array(
				    'product_name'  => 	$product_name,
				    'product_chinese_name'  => 	$product_chinese_name,
					'product_desc'	=>	$product_desc,
					'product_price'	=>	$product_price,
					'normal_price'	=>	$normal_price,
					'product_hpp'	=>	$product_hpp,
					'product_type'	=>	$product_type,
					'product_group'	=>	$product_group,
					'use_tax'		=>	$use_tax,
					'use_service'	=>	$use_service,
					'category_id'	=>	$category_id,
					'created'		=>	date('Y-m-d H:i:s'),
					'createdby'		=>	$session_user,
					'updated'		=>	date('Y-m-d H:i:s'),
					'updatedby'		=>	$session_user,
					'is_active'	=>	$is_active
				),
				'table'		=>  $this->table
			);				
			
			
			if($is_upload_file){
				
				if(!empty($big_size_real)){
					$var['fields']['product_image'] = $data_upload_temp['file_name'];
				}else{
					$get_file = do_thumb($data_upload_temp, $this->product_img_path_big, $this->product_img_path_big, '', $big_size_width, $big_size_height, TRUE, 'height');
					$var['fields']['product_image'] = $get_file;
				}
				
				
				
			}
			
			//SAVE
			$insert_id = false;
			$this->lib_trans->begin();
				$q = $this->m->add($var);
				$insert_id = $this->m->get_insert_id();
			$this->lib_trans->commit();			
			if($q)
			{  
				if($is_upload_file){					
					//thumb width 
					do_thumb($data_upload_temp, $this->product_img_path_big, $this->product_img_path_thumb, '', $thumb_size_width, $thumb_size_height, TRUE, 'height');
					
					//tiny
					do_thumb($data_upload_temp, $this->product_img_path_big, $this->product_img_path_tiny, '', $tiny_size_width, $tiny_size_height, TRUE, 'height');
				}
				
				$r = array('success' => true, 'id' => $insert_id); 				
			}  
			else
			{  
				if($is_upload_file){
					//unset upload file
					@unlink($this->product_img_path_big.$data_upload_temp['file_name']);
					
				}
				
				$r = array('success' => false);
			}
      		
		}else
		if($this->input->post('form_type_masterProduct', true) == 'edit'){
			$var = array('fields'	=>	array(
				    'product_name'  => 	$product_name,
				    'product_chinese_name'  => 	$product_chinese_name,
					'product_desc'	=>	$product_desc,
					'product_price'	=>	$product_price,
					'normal_price'	=>	$normal_price,
					'product_hpp'	=>	$product_hpp,
					'product_type'	=>	$product_type,
					'product_group'	=>	$product_group,
					'use_tax'		=>	$use_tax,
					'use_service'	=>	$use_service,
					'category_id'	=>	$category_id,
					'updated'		=>	date('Y-m-d H:i:s'),
					'updatedby'		=>	$session_user,
					'is_active'		=>	$is_active
				),
				'table'			=>  $this->table,
				'primary_key'	=>  'id'
			);
						
			if($is_upload_file){
				
				if(!empty($big_size_real)){
					$var['fields']['product_image'] = $data_upload_temp['file_name'];
				}else{
					$get_file = do_thumb($data_upload_temp, $this->product_img_path_big, $this->product_img_path_big, '', $big_size_width, $big_size_height, TRUE, 'height');
					$var['fields']['product_image'] = $get_file;
				}
				
			}
			
			//UPDATE
			$id = $this->input->post('id', true);
			$this->lib_trans->begin();
				$update = $this->m->save($var, $id);
			$this->lib_trans->commit();
			
			if($update)
			{  
				if($old_product_type == 'package' AND $old_product_type != $product_type){	
					//remove all package item					
					$this->db->where("package_id", $id);
					$del_package = $this->db->delete($this->table2);
				}
				
				if($is_upload_file){					
					//thumb width 200pixel
					do_thumb($data_upload_temp, $this->product_img_path_big, $this->product_img_path_thumb, '', $thumb_size_width, $thumb_size_height, TRUE, 'height');
					
					//tiny
					do_thumb($data_upload_temp, $this->product_img_path_big, $this->product_img_path_tiny, '', $tiny_size_width, $tiny_size_height, TRUE, 'height');
					
					//unset old file
					if(!empty($product_image) AND $product_image != 'no-image.jpg'){
						@unlink($this->product_img_path_big.$product_image);
						@unlink($this->product_img_path_thumb.$product_image);
						@unlink($this->product_img_path_tiny.$product_image);
					}
				}
				
				$r = array('success' => true, 'id' => $id);
			}  
			else
			{  
				if($is_upload_file){
					//unset upload file
					@unlink($this->product_img_path_big.$data_upload_temp['file_name']);					
				}
				
				$r = array('success' => false);
			}
		}
		
		die(json_encode(($r==null or $r=='')? array('success'=>false) : $r));
	}
	
	public function delete()
	{
		$this->table = $this->prefix.'product';
		$this->table2 = $this->prefix.'product_package';
		$this->product_img_path_big = RESOURCES_PATH.'product/big/';
		$this->product_img_path_thumb = RESOURCES_PATH.'product/thumb/';
		$this->product_img_path_tiny = RESOURCES_PATH.'product/tiny/';
		
		$get_id = $this->input->post('id', true);		
		$id = json_decode($get_id, true);
		//old data id
		$sql_Id = $id;
		if(is_array($id)){
			$sql_Id = implode(',', $id);
		}
		
		//Delete
		$this->db->where("id IN (".$sql_Id.")");
		$get_product = $this->db->get($this->table);
		
		$data_update = array(
			"is_deleted" => 1
		);
		$q = $this->db->update($this->table, $data_update, "id IN (".$sql_Id.")");
		
		$r = '';
		if($q)  
        {  
			if($get_product->num_rows() > 0){
							
				$all_product_package = array();
				
				foreach($get_product->result() as $dtP){
					if(!empty($dtP->product_image)){
						@unlink($this->product_img_path_big.$dtP->product_image);
						@unlink($this->product_img_path_thumb.$dtP->product_image);
						@unlink($this->product_img_path_tiny.$dtP->product_image);
					}
					
					if($dtP->product_type == 'package'){
						if(!in_array($dtP->product_id, $all_product_package)){
							$all_product_package[] = $dtP->product_id;
						}
					}
					
				}
				
				if(!empty($all_product_package)){		
					$all_product_package_txt = implode(",", $all_product_package);
					$del_package = $this->db->update($this->table2, $data_update, "package_id IN (".$all_product_package_txt.") OR product_id IN (".$all_product_package_txt.")");
				}
			}
            $r = array('success' => true); 
        }  
        else
        {  
            $r = array('success' => false, 'info' => 'Delete Product Failed!'); 
        }
		die(json_encode($r));
	}
	
	public function importHarga()
	{
		$this->table = $this->prefix.'product';		
		$session_user = $this->session->userdata('user_username');
		
		$this->file_harga_menu_path = RESOURCES_PATH.'harga_menu/';
		
		$r = ''; 
		$is_upload_file = false;		
		if(!empty($_FILES['upload_file']['name'])){
						
			$config['upload_path'] = $this->file_harga_menu_path;
			$config['allowed_types'] = 'xls';
			$config['max_size']	= '1024';

			$this->load->library('upload', $config);

			if(!$this->upload->do_upload("upload_file"))
			{
				$data = $this->upload->display_errors();
				$r = array('success' => false, 'info' => $data );
				die(json_encode($r));
			}
			else
			{
				$is_upload_file = true;
				$data_upload_temp = $this->upload->data();
				
				
				// Load the spreadsheet reader library
				$this->load->library('spreadsheet_Excel_Reader');
				$xls = new Spreadsheet_Excel_Reader();
				$xls->setOutputEncoding('CP1251'); 
				$file =  $this->file_harga_menu_path.$data_upload_temp['file_name']."" ;
				$xls->read($file);
				//echo '<pre>';
				//print_r($xls->sheets);die();
				
				error_reporting(E_ALL ^ E_NOTICE);
				
				$nr_sheets = count($xls->sheets);    
				
				$this->lib_trans->begin();
				for($i=0; $i<$nr_sheets; $i++) {
					//echo $xls->boundsheets[$i]['name'];
					//print_r($xls->sheets[$i]);
					
					for ($row_num = 2; $row_num <= $xls->sheets[$i]['numRows']; $row_num++) {	
						
						//echo '<pre>';
						//print_r($xls->sheets[$i]['cells'][$row_num]);
						//die();
						
						//id	product_name	product_desc	product_price	product_hpp	product_type	product_group	category_id
						
						$id = $xls->sheets[$i]['cells'][$row_num][1];								
						$product_name = $xls->sheets[$i]['cells'][$row_num][2];								
						$product_desc = $xls->sheets[$i]['cells'][$row_num][3];																
						$normal_price = $xls->sheets[$i]['cells'][$row_num][4];								
						$product_price = $xls->sheets[$i]['cells'][$row_num][5];														
						$product_hpp = $xls->sheets[$i]['cells'][$row_num][6];							
						$product_type = $xls->sheets[$i]['cells'][$row_num][7];									
						$product_group = $xls->sheets[$i]['cells'][$row_num][8];									
						$category_id = $xls->sheets[$i]['cells'][$row_num][9];		
						$use_tax = $xls->sheets[$i]['cells'][$row_num][10];		
						$use_service = $xls->sheets[$i]['cells'][$row_num][11];		
						
						if(empty($product_type)){
							$product_type = 'item';							
						}
						
						if(empty($product_group)){
							$product_group = 'food';							
						}
						
						$update_date = date('Y-m-d H:i:s');
						
						if(!empty($product_name) AND !empty($normal_price) AND !empty($product_price)){
							if(empty($id)){
								//INSERT									
								$var = array(
									'fields'	=>	array(
										'product_name'	=> 	$product_name,
										'product_desc'	=>	$product_desc,
										'product_price'	=>	$product_price,
										'normal_price'	=>	$normal_price,
										'product_hpp'	=>	$product_hpp,
										'product_type'	=>	$product_type,
										'product_group' =>	$product_group,
										'use_tax'		=>	$use_tax,
										'use_service'	=>	$use_service,
										'category_id'	=>	$category_id,
										'created'		=>	$update_date,
										'createdby'		=>	$session_user,
										'updated'		=>	$update_date,
										'updatedby'		=>	$session_user,
									),
									'table'		=>  $this->table
								);	
								
								$q = $this->m->save($var);
							}else{
								//UPDATE
								$var = array(
									'fields'	=>	array(
										'product_name'	=> 	$product_name,
										'product_desc'	=>	$product_desc,
										'product_price'	=>	$product_price,
										'normal_price'	=>	$normal_price,
										'product_hpp'	=>	$product_hpp,
										'product_type'	=>	$product_type,
										'product_group' =>	$product_group,
										'use_tax'		=>	$use_tax,
										'use_service'	=>	$use_service,
										'category_id'	=>	$category_id,
										'updated'		=>	$update_date,
										'updatedby'		=>	$session_user,
									),
									'table'			=>  $this->table,
									'primary_key'	=>  'id'
								);	
								
								$q = $this->m->save($var, $id);
							}
						}
						
						
						
					}
					
				}    
				$this->lib_trans->commit();	
				
				if($q)
				{ 
					$r = array('success' => true); 				
				}  
				else
				{  				
					$r = array('success' => false);
				}
				
				
			}
		}
		
		die(json_encode(($r==null or $r=='')? array('success'=>false) : $r));	
 
	}
	
	public function print_masterProduct(){
		
		$this->table = $this->prefix.'product';
		$data_post['table'] = $this->table;
				
		$this->load->view('../../master_pos/views/print_masterProduct', $data_post);
		
	}
	
}