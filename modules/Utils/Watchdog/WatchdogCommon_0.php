<?php
/**
 * 
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-utils
 * @subpackage Watchdog
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_WatchdogCommon extends ModuleCommon {
	private static $log = false;

	public static function user_settings() {
		return array(
			__('Watchdog')=>array(
				array('name'=>'email', 'label'=>__('Send e-mail on new events'), 'type'=>'checkbox', 'default'=>false)
			)
		);
	}

	public static function applet_caption() {
		return __('Watchdog');
	}
	public static function applet_info() {
		return __('Helps tracking changes made in the system');
	}
	public static function applet_settings() {
		$methods = DB::GetAssoc('SELECT id,callback FROM utils_watchdog_category');
		$ret = array();
		if (!empty($methods)) {
			$ret[] = array('label'=>__('Categories'),'name'=>'categories_header','type'=>'header');
			foreach ($methods as $k=>$v) { 
				$method = explode('::',$v);
				IF (!is_callable($method)) continue;
				$methods[$k] = call_user_func($method);
				$ret[] = array('label'=>$methods[$k]['category'],'name'=>'category_'.$k,'type'=>'checkbox','default'=>true);
			}
		}
		return $ret;
	}
	public static function get_subscribers($category_name, $id=null) {
		$category_id = self::get_category_id($category_name);
		if ($id!==null) $ret = DB::GetAssoc('SELECT user_id,user_id FROM utils_watchdog_subscription WHERE category_id=%d AND internal_id=%s', array($category_id, $id));
		else $ret = DB::GetAssoc('SELECT user_id,user_id FROM utils_watchdog_category_subscription WHERE category_id=%d', array($category_id));
		return $ret;
	}
	public static function get_category_id($category_name, $report_error=true) {
		static $cache = array();
		if (isset($cache[$category_name])) return $cache[$category_name];
		if (is_numeric($category_name)) return $category_name;  
		$ret = DB::GetOne('SELECT id FROM utils_watchdog_category WHERE name=%s', array(md5($category_name)));
		if ($ret===false || $ret===null) {
//			if ($report_error) trigger_error('Invalid category given: '.$category_name.', category not found.');
			return null;
		}  
		return $cache[$category_name] = $ret;
	}
	public static function category_exists($category_name) {
		static $cache = array();
		if (isset($cache[$category_name])) return $cache[$category_name];
		$ret = DB::GetOne('SELECT id FROM utils_watchdog_category WHERE name=%s', array(md5($category_name)));
		$ret = ($ret!==false && $ret!==null);
		return $cache[$category_name] = $ret;
	}
	private static function check_if_user_subscribes($user, $category_name, $id=null) {
		$category_id = self::get_category_id($category_name);
		if (!$category_id) return;
		if ($id!==null) $last_seen = DB::GetOne('SELECT last_seen_event FROM utils_watchdog_subscription WHERE user_id=%d AND internal_id=%d AND category_id=%d',array($user,$id,$category_id));
		else $last_seen = DB::GetOne('SELECT 1 FROM utils_watchdog_category_subscription WHERE user_id=%d AND category_id=%d',array($user,$category_id));
		return ($last_seen!==false && $last_seen!==null);
	}
	// ****************** registering ************************
	public static function register_category($category_name, $callback) {
		$exists = DB::GetOne('SELECT name FROM utils_watchdog_category WHERE name=%s',array(md5($category_name)));
		if ($exists!==false && $exists!==null) return;
		if (is_array($callback)) $callback = implode('::',$callback);
		DB::Execute('INSERT INTO utils_watchdog_category (name, callback) VALUES (%s,%s)',array(md5($category_name),$callback));
	}

	public static function unregister_category($category_name) {
		$category_id = self::get_category_id($category_name);
		if (!$category_id) return;
		DB::Execute('DELETE FROM utils_watchdog_category_subscription WHERE category_id=%d',array($category_id));
		DB::Execute('DELETE FROM utils_watchdog_subscription WHERE category_id=%d',array($category_id));
		DB::Execute('DELETE FROM utils_watchdog_event WHERE category_id=%d',array($category_id));
		DB::Execute('DELETE FROM utils_watchdog_category WHERE id=%d',array($category_id));
	}
	// *********************************** New event ***************************
	public static function new_event($category_name, $id, $message) {
		$category_id = self::get_category_id($category_name, false);
		if (!$category_id) return;
		DB::Execute('INSERT INTO utils_watchdog_event (category_id, internal_id, message, event_time) VALUES (%d,%d,%s,%T)',array($category_id,$id,$message,time()));
		$event_id = DB::Insert_ID('utils_watchdog_event', 'id');
		Utils_WatchdogCommon::notified($category_name,$id);
		$count = DB::GetOne('SELECT COUNT(*) FROM utils_watchdog_event WHERE category_id=%d AND internal_id=%d', array($category_id,$id));
		if ($count==1) {
			$subscribers = self::get_subscribers($category_id);
			foreach ($subscribers as $s)
				self::user_subscribe($s, $category_name, $id);
		}
		$mail_users = DB::GetAssoc('SELECT user_id, user_id FROM utils_watchdog_subscription AS uws INNER JOIN base_user_settings AS bus ON uws.user_id=bus.user_login_id AND category_id=%d AND internal_id=%s AND bus.module=%s AND bus.variable=%s AND bus.value=%s', array($category_id, $id, 'Utils_Watchdog', 'email', serialize('1')));

		$c_user = Acl::get_user();
		foreach ($mail_users as $m) {
				if ($m==$c_user) continue;
				Acl::set_user($m);
				$email_data = self::display_events($category_id, array($event_id=>$message), $id);
				if (!$email_data) continue;
				$contact = Utils_RecordBrowserCommon::get_id('contact', 'login', $m);
				if (!$contact) continue;
				$email = Utils_RecordBrowserCommon::get_value('contact', $contact, 'email');
				if (!$email) continue;
				Base_MailCommon::send($email,__( 'EPESI notification - %s - %s', array($email_data['category'], strip_tags($email_data['title']))),$email_data['events'], null, null, true);
		}
		Acl::set_user($c_user);
	}
	// *************************** Subscription manipulation *******************
	public static function user_purge_notifications($user_id, $category_name, $time=null) {
		$category_id = self::get_category_id($category_name);
		if (!$category_id) return;
		if ($time===null) $time=time();
		DB::Execute('UPDATE utils_watchdog_subscription AS uws SET last_seen_event=(SELECT MAX(id) FROM utils_watchdog_event AS uwe WHERE uwe.internal_id=uws.internal_id AND uwe.category_id=uws.category_id AND (event_time<=%T OR event_time IS NULL)) WHERE user_id=%d AND category_id=%d', array($time, $user_id, $category_id));
		DB::Execute('UPDATE utils_watchdog_subscription AS uws SET last_seen_event=-1 WHERE last_seen_event IS NULL');
	}
	public static function user_notified($user_id, $category_name, $id) {
		$category_id = self::get_category_id($category_name);
		if (!$category_id) return;
		$last_event = DB::GetOne('SELECT MAX(id) FROM utils_watchdog_event WHERE internal_id=%d AND category_id=%d', array($id,$category_id));
		if ($last_event===null || $last_event===false) $last_event = -1;
		DB::Execute('UPDATE utils_watchdog_subscription SET last_seen_event=%d WHERE user_id=%d AND internal_id=%d AND category_id=%d',array($last_event,$user_id,$id,$category_id));
		DB::Execute('DELETE FROM utils_watchdog_event WHERE internal_id=%d AND category_id=%d AND (id<(SELECT MIN(last_seen_event) FROM utils_watchdog_subscription WHERE internal_id=%d AND category_id=%d) OR event_time<=%T)', array($id,$category_id,$id,$category_id, date('Y-m-d H:i:s', strtotime('-3 month'))));
	}

	public static function user_subscribe($user_id, $category_name, $id) {
		$category_id = self::get_category_id($category_name);
		if (!$category_id) return;
		$lse = DB::GetOne('SELECT MAX(id) FROM utils_watchdog_event WHERE internal_id=%d AND category_id=%d AND id<(SELECT MAX(id) FROM utils_watchdog_event WHERE internal_id=%d AND category_id=%d)', array($id, $category_id, $id, $category_id));
		if ($lse===false || $lse===null) $lse=-1;
		$already_subscribed = DB::GetOne('SELECT last_seen_event FROM utils_watchdog_subscription WHERE user_id=%d AND internal_id=%d AND category_id=%d',array($user_id,$id,$category_id));
		if ($already_subscribed===false || $already_subscribed===null) DB::Execute('INSERT INTO utils_watchdog_subscription (last_seen_event, user_id, internal_id, category_id) VALUES (%d,%d,%d,%d)',array($lse,$user_id,$id,$category_id));
		if ($user_id==Acl::get_user()) self::notified($category_name, $id);
		if (self::$log) error_log('User '.$user_id.' subscribed to '.$category_name.':'.$id."\n",3,'data/subscriptions.log');
	}

	public static function user_change_subscription($user_id, $category_name, $id=null) {
		$category_id = self::get_category_id($category_name);
		if (!$category_id) return;
		$already_subscribed = self::check_if_user_subscribes($user_id,$category_id,$id);
		if ($id===null) {
			if ($already_subscribed!==false && $already_subscribed!==null) DB::Execute('DELETE FROM utils_watchdog_category_subscription WHERE user_id=%d AND category_id=%d',array($user_id,$category_id));
			else DB::Execute('INSERT INTO utils_watchdog_category_subscription (user_id, category_id) VALUES (%d,%d)',array($user_id,$category_id));
		} else {
			if ($already_subscribed!==false && $already_subscribed!==null) DB::Execute('DELETE FROM utils_watchdog_subscription WHERE user_id=%d AND internal_id=%d AND category_id=%d',array($user_id,$id,$category_id));
			else { 
				DB::Execute('INSERT INTO utils_watchdog_subscription (last_seen_event, user_id, internal_id, category_id) VALUES (%d,%d,%d,%d)',array(-1,$user_id,$id,$category_id));
				if ($user_id==Acl::get_user()) self::notified($category_name, $id);
			}
		}
		if (self::$log) error_log('User '.$user_id.' '.($already_subscribed?'un-':'').'watched '.$category_name.':'.$id."\n",3,'data/subscriptions.log');
	}

	public static function user_unsubscribe($user_id, $category_name, $id) {
		$category_id = self::get_category_id($category_name);
		if (!$category_id) return;
		if ($user_id!==null) DB::Execute('DELETE FROM utils_watchdog_subscription WHERE user_id=%d AND internal_id=%d AND category_id=%d',array($user_id,$id,$category_id));
		else DB::Execute('DELETE FROM utils_watchdog_subscription WHERE internal_id=%d AND category_id=%d',array($id,$category_id));
		if (self::$log) error_log('User '.$user_id.' unsubscribed to '.$category_name.':'.$id."\n",3,'data/subscriptions.log');
	}

	public static function user_check_if_notified($user_id, $category_name, $id) {
		$category_id = self::get_category_id($category_name);
		if (!$category_id) return;
		$last_seen = DB::GetOne('SELECT last_seen_event FROM utils_watchdog_subscription WHERE user_id=%d AND internal_id=%d AND category_id=%d',array($user_id,$id,$category_id));
		if ($last_seen===false || $last_seen===null) return null;
		$last_event = DB::GetOne('SELECT MAX(id) FROM utils_watchdog_event WHERE internal_id=%d AND category_id=%d', array($id,$category_id));
		if ($last_event===false || $last_event===null) $last_event=-1;
		if ($last_seen==$last_event || $last_event==-1) return true;
		$ret = array();
		
		$missed_events = DB::Execute('SELECT id,message FROM utils_watchdog_event WHERE internal_id=%d AND category_id=%d AND id>%d ORDER BY id ASC', array($id,$category_id,$last_seen));
		while ($row = $missed_events->FetchRow())
			$ret[$row['id']] = $row['message'];
		return $ret;
	}
	
	public static function user_get_confirm_change_subscr_href($user, $category_name, $id=null) {
		return Module::create_confirm_href(__('Are you sure you want to stop watching this record?'),self::user_get_change_subscr_href_array($user, $category_name, $id));
	}
	public static function user_get_change_subscr_href($user, $category_name, $id=null) {
		return Module::create_href(self::user_get_change_subscr_href_array($user, $category_name, $id));
	}
	public static function user_get_change_subscr_href_array($user, $category_name, $id=null) {
		$category_id = self::get_category_id($category_name);
		if (!$category_id) return;
		if (isset($_REQUEST['utils_watchdog_category']) &&
			isset($_REQUEST['utils_watchdog_user']) &&  
			$_REQUEST['utils_watchdog_category']==$category_id &&
			$_REQUEST['utils_watchdog_user']==$user &&
			((isset($_REQUEST['utils_watchdog_id']) && 
			$_REQUEST['utils_watchdog_id']==$id) || 
			(!isset($_REQUEST['utils_watchdog_id']) &&
			$id===null))) {
			self::user_change_subscription($user, $category_name, $id);
			unset($_REQUEST['utils_watchdog_category']);
			unset($_REQUEST['utils_watchdog_user']);
			unset($_REQUEST['utils_watchdog_id']);
			location(array());	
		}
		return array('utils_watchdog_category'=>$category_id, 'utils_watchdog_user'=>$user, 'utils_watchdog_id'=>$id);
	}
	// **************** Subscription manipulation for logged user *******************
	public static function purge_notifications($category_name, $time=null) {
		self::user_purge_notifications(Acl::get_user(), $category_name, $time);
	}
	public static function notified($category_name, $id) {
		self::user_notified(Acl::get_user(), $category_name, $id);
	}
	public static function subscribe($category_name, $id) {
		self::user_subscribe(Acl::get_user(), $category_name, $id);
	}
	public static function unsubscribe($category_name, $id=null) {
		self::user_unsubscribe(Acl::get_user(), $category_name, $id);
	}
	public static function check_if_notified($category_name, $id) {
		return self::user_check_if_notified(Acl::get_user(), $category_name, $id);
	}
	public static function get_change_subscr_href($category_name, $id=null) {
		return self::user_get_change_subscr_href(Acl::get_user(), $category_name, $id);
	}
	public static function get_confirm_change_subscr_href($category_name, $id=null) {
		return self::user_get_confirm_change_subscr_href(Acl::get_user(), $category_name, $id);
	}
	public static function add_actionbar_change_subscription_button($category_name, $id=null) {
		if (!Base_AclCommon::check_permission('Watchdog - subscribe to categories')) return;
		$category_id = self::get_category_id($category_name);
		if (!$category_id) return;
		$href = self::get_change_subscr_href($category_name, $id);
		$subscribed = self::check_if_user_subscribes(Acl::get_user(), $category_id, $id);
		if ($subscribed) {
			$icon = Base_ThemeCommon::get_template_file('Utils_Watchdog','unwatch_big.png');
			$label = __('Stop Watching');
		} else {
			$icon = Base_ThemeCommon::get_template_file('Utils_Watchdog','watch_big.png');
			$label = __('Watch');
		}
		Base_ActionBarCommon::add($icon,$label,$href);
	}
	public static function display_events($category_name, $changes, $id) {
		if (!is_array($changes)) return '';
		$category_id = self::get_category_id($category_name);
		$method = DB::GetOne('SELECT callback FROM utils_watchdog_category WHERE id=%d', array($category_id));
		$method = explode('::', $method);
		$data = call_user_func($method, $id, $changes);
		if (!isset($data['events'])) return '';
		return $data;
	}
	public static function get_change_subscription_icon($category_name, $id) {
		$tag_id = 'watchdog_sub_button_'.$category_name.'_'.$id;
		return '<span id="'.$tag_id.'">'.self::get_change_subscription_icon_tags($category_name, $id).'</span>';
	}
	public static function get_change_subscription_icon_tags($category_name, $id) {
		$category_id = self::get_category_id($category_name);
		if (!$category_id) return;
		$last_seen = self::check_if_notified($category_name, $id);
		load_js('modules/Utils/Watchdog/subscribe.js');
		$tag_id = 'watchdog_sub_button_'.$category_name.'_'.$id;
		$href = ' onclick="utils_watchdog_set_subscribe('.(($last_seen===null)?1:0).',\''.$category_name.'\','.$id.',\''.$tag_id.'\')" href="javascript:void(0);"';
		if ($last_seen===null) {
			$icon = Base_ThemeCommon::get_template_file('Utils_Watchdog','not_watching_small.png');
			$tooltip = Utils_TooltipCommon::open_tag_attrs(__('Click to watch this record for changes.'));
		} else {
			if ($last_seen===true) {
				$icon = Base_ThemeCommon::get_template_file('Utils_Watchdog','watching_small.png');
				$tooltip = Utils_TooltipCommon::open_tag_attrs(__('You are watching this record, click to stop watching this record for changes.'));
			} else {
				$icon = Base_ThemeCommon::get_template_file('Utils_Watchdog','watching_small_new_events.png');
				$ev = self::display_events($category_id, $last_seen, $id);
				$tooltip = Utils_TooltipCommon::open_tag_attrs(__('You are watching this record, click to stop watching this record for changes.').'<br>'.__('The following changes were made since the last time you were viewing this record:').'<br><br>'.$ev['events']);
			}
		}
		return '<a '.$href.' '.$tooltip.'><img border="0" src="'.$icon.'"></a>';
	} 
	
	public static function tray_notification() {
		$methods = DB::GetAssoc('SELECT id,callback FROM utils_watchdog_category');
		foreach ($methods as $k=>$v) { 
			$methods[$k] = explode('::',$v);
		}
		$only_new = ' AND last_seen_event<(SELECT MAX(id) FROM utils_watchdog_event AS uwe WHERE uwe.internal_id=uws.internal_id AND uwe.category_id=uws.category_id)';
		$records = DB::GetAll('SELECT internal_id,category_id,last_seen_event FROM utils_watchdog_subscription AS uws WHERE user_id=%d '.$only_new, array(Acl::get_user()));
		$ret = array();
		foreach ($records as $v) {			
			$changes = Utils_WatchdogCommon::check_if_notified($v['category_id'], $v['internal_id']);
			if (!is_array($changes)) $changes = array();
			$data = call_user_func($methods[$v['category_id']], $v['internal_id'], $changes, false);
			if ($data==null) continue;
			$ret['watchdog_'.$v['internal_id'].'_'.$v['category_id'].'_'.$v['last_seen_event']] = '<b>'.__('Watchdog - %s', array($data['category'])).':</b> '.$data['title'];
			if (isset($data['events']) && $data['events'])
				$ret['watchdog_'.$v['internal_id'].'_'.$v['category_id'].'_'.$v['last_seen_event']] .= '<br><font size=-5 color=gray>'.$data['events'].'</font>';
		}
		return array('notifications'=>$ret);
	}
}

?>
