<?php
/*
Project Name: IonicEcommerce
Project URI: http://ionicecommerce.com
Author: VectorCoder Team
Author URI: http://vectorcoder.com/
Version: 2.2
*/
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;

//validator is builtin class in laravel
use Validator;
use App;
use Lang;
use File;
use DB;
//for password encryption or hash protected
use Hash;
use App\Administrator;
use Mail;
//for authenitcate login data
use Auth;

//use Illuminate\Foundation\Auth\ThrottlesLogins;
//use Illuminate\Foundation\Auth\AuthenticatesAndRegistersUsers;

//for requesting a value 
use Illuminate\Http\Request;
//use Illuminate\Routing\Controller;


class AdminAlertController extends Controller
{
	
	//listingDevices
	public function orderStatusChange($data){
		
		//alert setting
		$myVar = new AdminSiteSettingController();
		$alertSetting = $myVar->getAlertSetting();
		
		$title	  = Lang::get("labels.OrderStatusNotificationTitle");
		$message  = Lang::get("labels.OrderStatusNotficationMessagePart1").' '.$data['orders_id'].' '.Lang::get("labels.OrderStatusNotificationMessagePart2").' '.$data['status'].'.';
				
		$devices = DB::table('devices')
			->LeftJoin('customers','customers.customers_id','=','devices.customers_id')
			->where('devices.is_notify','=', '1')
			->where('devices.customers_id', $data['customers_id'])->get();
			
			
		$temp['devices'] = $devices;
		$temp['message'] = $message;
		
		//status change email 
		if($alertSetting[0]->order_status_email==1){	
		
			Mail::send('/mail/orderStatus', ['data' => $temp], function($m) use ($temp){
				$m->to($temp['devices'][0]->customers_email_address)->subject(Lang::get("labels.OrderStatusEmailTitle"))->getSwiftMessage()
				->getHeaders()
				->addTextHeader('x-mailgun-native-send', 'true');	
			});
		
		}
		
		//notification
		if($alertSetting[0]->order_status_notification==1){	
			
			$sendData = array
				  (
				'body' 	=> $message,
				'title'	=> $title ,
						'icon'	=> 'myicon',/*Default Icon*/
						'sound' => 'mySound',/*Default sound*/
						'image' => ''
				  );
		
			//status change push notifications
			$myVar = new AdminSiteSettingController();
			$setting = $myVar->getSetting();
			
			if($setting[0]->default_notification=='fcm'){
				$functionName = 'fcmNotification';	
			}elseif($setting[0]->default_notification=='onesignal'){
				$functionName = 'onesignalNotification';
			}
	
			foreach($devices as $devices_data){						
				if(!empty($devices_data->device_id)){
					$response = $this->$functionName($devices_data->device_id, $sendData);
				}
			}
		
		}		
		
	}
	
	
	//new product notifications
	public function newProductNotification($products_id){
		$result = array();
		//alert setting
		$myVar = new AdminSiteSettingController();
		$alertSetting = $myVar->getAlertSetting();
		
		$product = DB::table('products_to_categories')
			->leftJoin('categories', 'categories.categories_id', '=', 'products_to_categories.categories_id')
			->leftJoin('categories_description', 'categories_description.categories_id', '=', 'products_to_categories.categories_id')
			->leftJoin('products', 'products.products_id', '=', 'products_to_categories.products_id')
			->leftJoin('products_description','products_description.products_id','=','products.products_id')
			->LeftJoin('manufacturers', function ($join) {
				$join->on('manufacturers.manufacturers_id', '=', 'products.manufacturers_id');
			 })
			->LeftJoin('specials', function ($join) {
				$join->on('specials.products_id', '=', 'products.products_id')->where('status', '=', '1');
			 })
			 
			->select('products_to_categories.*', 'categories_description.categories_name','categories.*', 'products.*','products_description.*', 'specials.specials_id', 'manufacturers.*', 'specials.products_id as special_products_id', 'specials.specials_new_products_price as specials_products_price', 'specials.specials_date_added as specials_date_added', 'specials.specials_last_modified as specials_last_modified', 'specials.expires_date')
			->where('products_description.language_id','=', 1)
			->where('products.products_id','=', $products_id)
			->where('categories_description.language_id','=', 1)->get();
		
		$result['product'] = $product;	
		
		//email 
		if($alertSetting[0]->new_product_email==1){
			
			$customers = DB::table('customers')->get();
			
			$result['customers'] = $customers;
			
			foreach($customers as $customers_data){
				$customers_data->product = $product;
				
				Mail::send('/mail/newProduct', ['customers_data' => $customers_data], function($m) use ($customers_data){
					$m->to($customers_data->customers_email_address)->subject(Lang::get("labels.NewProductEmailTitle"))->getSwiftMessage()
					->getHeaders()
					->addTextHeader('x-mailgun-native-send', 'true');	
				});
				
			}
			
			
		}
		
		//notification 
		if($alertSetting[0]->new_product_notification==1){
			
			$title	  = Lang::get("labels.newProductNotificationTitle");
			$message  = 'newProductNotficationMessagePart1 "'.$product[0]->products_name.'" '.Lang::get("labels.newProductNotficationMessagePart2");
			
			//image			
			$websiteURL =  "http://" . $_SERVER['SERVER_NAME'] .'/'. $product[0]->products_image;	
			
			$sendData = array
				  (
				'body' 	=> $message,
				'title'	=> $title ,
						'icon'	=> 'myicon',/*Default Icon*/
						'sound' => 'mySound',/*Default sound*/
						'image' => $websiteURL
				  );
		
			//status change push notifications
			$myVar = new AdminSiteSettingController();
			$setting = $myVar->getSetting();
			
			
			$devices = DB::table('devices')
						->where('status','=', 1)
						->where('devices.'.$setting[0]->default_notification,'=', '1')	
						->where('devices.is_notify','=', '1')
						->get();
						
			if($setting[0]->default_notification=='fcm'){
				$functionName = 'fcmNotification';	
			}elseif($setting[0]->default_notification=='onesignal'){
				$functionName = 'onesignalNotification';
			}
			
			foreach($devices as $devices_data){					
				$response[] = $this->$functionName($devices_data->device_id, $sendData);
			}
		}
		
	}
	
	//new product notifications
	public function newsNotification($news_id){
		$result = array();
		//alert setting
		$myVar = new AdminSiteSettingController();
		$alertSetting = $myVar->getAlertSetting();
		
		$news = DB::table('news_to_news_categories')
			->leftJoin('news_categories', 'news_categories.categories_id', '=', 'news_to_news_categories.categories_id')
			->leftJoin('news', 'news.news_id', '=', 'news_to_news_categories.news_id')
			->leftJoin('news_description','news_description.news_id','=','news.news_id')
			->leftJoin('news_categories_description','news_categories_description.categories_id','=','news_to_news_categories.categories_id')
			
			->select('news_to_news_categories.*', 'news_categories_description.categories_name','news_categories.*', 'news.*','news_description.*')
			->where('news.news_id','=', $news_id)
			->where('news_description.language_id','=', 1)
			->where('news_categories_description.language_id','=', 1)
			->orderBy('news.news_id', 'ASC')
			->get();
		
		$result['news'] = $news;	
		
		//email 
		if($alertSetting[0]->news_email==1){
			
			$customers = DB::table('customers')->get();
			
			$result['customers'] = $customers;
			
			foreach($customers as $customers_data){
				$customers_data->news = $news;
				
				Mail::send('/mail/news', ['customers_data' => $customers_data], function($m) use ($customers_data){
					$m->to($customers_data->customers_email_address)->subject($customers_data->news[0]->news_name)->getSwiftMessage()
					->getHeaders()
					->addTextHeader('x-mailgun-native-send', 'true');	
				});
				
			}			
			
		}
		
		//notification 
		if($alertSetting[0]->news_notification==1){
			
			$title	  = Lang::get("labels.newsNotificationTitle");
			$message  = $news[0]->news_name.' '.Lang::get("labels.newsNotficationMessagePart1");
			
			//image			
			$websiteURL =  "http://" . $_SERVER['SERVER_NAME'] .'/'. $news[0]->news_image;	
			
			$sendData = array
				  (
				'body' 	=> $message,
				'title'	=> $title ,
						'icon'	=> 'myicon',/*Default Icon*/
						'sound' => 'mySound',/*Default sound*/
						'image' => $websiteURL
				  );
		
			//status change push notifications
			$myVar = new AdminSiteSettingController();
			$setting = $myVar->getSetting();
			
			
			$devices = DB::table('devices')
						->where('status','=', 1)
						->where('devices.'.$setting[0]->default_notification,'=', '1')	
						->where('devices.is_notify','=', '1')
						->get();
						
			if($setting[0]->default_notification=='fcm'){
				$functionName = 'fcmNotification';	
			}elseif($setting[0]->default_notification=='onesignal'){
				$functionName = 'onesignalNotification';
			}
			
			foreach($devices as $devices_data){					
				$response[] = $this->$functionName($devices_data->device_id, $sendData);
			}
		}
		
	}
	
	
	//send notifications
	public function fcmNotification($device_id, $sendData){
		
		//get function from other controller
		$myVar = new AdminSiteSettingController();
		$setting = $myVar->getSetting();
		
		#API access key from Google API's Console
		if (!defined('API_ACCESS_KEY')){
			define('API_ACCESS_KEY', $setting[0]->fcm_android);
		}
				
		$fields = array
				(
					'to'		=> $device_id,
					'data'	=> $sendData
				);


		$headers = array
				(
					'Authorization: key=' . API_ACCESS_KEY,
					'Content-Type: application/json'
				);
		#Send Reponse To FireBase Server	
		$ch = curl_init();
		curl_setopt( $ch,CURLOPT_URL, 'https://fcm.googleapis.com/fcm/send' );
		curl_setopt( $ch,CURLOPT_POST, true );
		curl_setopt( $ch,CURLOPT_HTTPHEADER, $headers );
		curl_setopt( $ch,CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $ch,CURLOPT_SSL_VERIFYPEER, true );
		curl_setopt( $ch,CURLOPT_POSTFIELDS, json_encode( $fields ) );
		$result = curl_exec($ch);
		//$data = json_decode($result);
		if($result === false)
		die('Curl failed ' . curl_error());

		curl_close($ch);
		
	}
	
	public function onesignalNotification($device_id, $sendData){
		
		//get function from other controller
		$myVar = new AdminSiteSettingController();
		$setting = $myVar->getSetting();		
		
		$content = array(
		   "en" => $sendData['body']
		   );
		
		$headings = array(
		   "en" => $sendData['title']
		   );
		
		$big_picture = array(
		   "id1" => $sendData['image']
		   );
		
		$fields = array(
		   'app_id' => $setting[0]->onesignal_app_id,
		   'include_player_ids' => array($device_id),
		   'contents' => $content,
		   'headings'=>$headings,
		   'big_picture'=>$sendData['image']
		);
	
		$fields = json_encode($fields);
	
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, "https://onesignal.com/api/v1/notifications");
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json; charset=utf-8',
				   'Authorization: Basic ZTJhZTcwNzItODQ4Ni00Y2FiLWFjZjEtMGY4ODZhZGZlMGZl'));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HEADER, FALSE);
		curl_setopt($ch, CURLOPT_POST, TRUE);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
	
		$result = curl_exec($ch);
		//$data = json_decode($result);
		curl_close($ch);
		
	}
		
}
