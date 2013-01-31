<?php
/** 
 * @author Kuba Slawinski <kslawinski@telaxus.com> and Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC 
 * @version 1.0
 * @license MIT 
 * @package epesi-utils 
 * @subpackage tooltip
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_TooltipCommon extends ModuleCommon {
	public static function user_settings(){
		return array(__('Misc')=>array(
			array('name'=>'help_tooltips','label'=>__('Show help tooltips'),'type'=>'checkbox','default'=>1)
			));
	}

	private static $help_tooltips;
	private static function show_help() {
		if(!isset(self::$help_tooltips))
			self::$help_tooltips = Base_User_SettingsCommon::get('Utils/Tooltip','help_tooltips');
	}
	
	public static function init_tooltip_div(){
		if(!isset($_SESSION['client']['utils_tooltip']['div_exists'])) {
			$smarty = Base_ThemeCommon::init_smarty();
			$smarty->assign('tip','<span id="tooltip_text"></span>');
			ob_start();
			@Base_ThemeCommon::display_smarty($smarty,'Utils_Tooltip');
			$tip_th = ob_get_clean();
			eval_js('Utils_Tooltip__create_block(\''.Epesi::escapeJS($tip_th,false).'\')',false);
			$_SESSION['client']['utils_tooltip']['div_exists'] = true;
		}
		on_exit(array('Utils_TooltipCommon', 'hide_tooltip'),null,false);
	}
	
	public static function hide_tooltip() {
		eval_js('Utils_Tooltip__hideTip()');
	}

	/**
	 * Returns string that when placed as tag attribute
	 * will enable tooltip when placing mouse over that element.
	 *
	 * @param string tooltip text
	 * @param boolean help tooltip? (you can turn off help tooltips)
	 * @return string HTML tag attributes
	 */
	public static function open_tag_attrs( $tip, $help=true, $max_width=500 ) {
		if(MOBILE_DEVICE) return '';
		self::show_help();
		if($help && !self::$help_tooltips) return '';
		return ' onMouseMove="if(typeof(Utils_Tooltip__showTip)!=\'undefined\')Utils_Tooltip__showTip(this,event,'.$max_width.')" tip="'.htmlspecialchars($tip).'" onMouseOut="if(typeof(Utils_Tooltip__hideTip)!=\'undefined\')Utils_Tooltip__hideTip()" onMouseUp="if(typeof(Utils_Tooltip__hideTip)!=\'undefined\')Utils_Tooltip__hideTip()" ';
	}

	/**
	 * Returns string that when placed as tag attribute
	 * will enable ajax request to set a tooltip when placing mouse over that element.
	 *
	 * @param callback method that will be called to get tooltip content
	 * @param array parameters that will be passed to the callback
	 * @return string HTML tag attributes
	 */
	public static function ajax_open_tag_attrs( $callback, $args, $max_width=300 ) {
		if(MOBILE_DEVICE) return '';
		static $tooltip_id = 0;
		static $tooltip_cleared = false;
		if (isset($_REQUEST['__location']) && $tooltip_cleared!=$_REQUEST['__location']) {
			$tooltip_cleared = $_REQUEST['__location'];
			$tooltip_id = 0;
		}
		$tooltip_id++;
		$_SESSION['client']['utils_tooltip']['callbacks'][$tooltip_id] = array('callback'=>$callback, 'args'=>$args);
		$loading_message = '<center><img src='.Base_ThemeCommon::get_template_file('Utils_Tooltip','loader.gif').' /><br/>'.__('Loading...').'</center>';
		return ' onMouseMove="if(typeof(Utils_Tooltip__showTip)!=\'undefined\')Utils_Tooltip__load_ajax_Tip(this,event,'.$max_width.')" tip="'.$loading_message.'" tooltip_id="'.$tooltip_id.'" onMouseOut="if(typeof(Utils_Tooltip__hideTip)!=\'undefined\')Utils_Tooltip__hideTip()" onMouseUp="if(typeof(Utils_Tooltip__hideTip)!=\'undefined\')Utils_Tooltip__hideTip()" ';
	}

	/**
	 * Returns string that if displayed will create text with tooltip.
	 *
	 * @param string text
	 * @param string tooltip text
	 * @param boolean help tooltip? (you can turn off help tooltips)
	 * @return string text with tooltip
	 */
	public static function create( $text, $tip, $help=true, $max_width=300) {
		self::show_help();
		if((!$help || self::$help_tooltips) && is_string($tip) && $tip!=='')
			return '<span '.self::open_tag_attrs($tip,$help,$max_width).'>'.$text.'</span>';
		else
			return $text;
	}

	/**
	 * Returns string that if displayed will create text with tooltip loaded via ajax.
	 *
	 * @param string text
	 * @param string tooltip text
	 * @param boolean help tooltip? (you can turn off help tooltips)
	 * @return string text with tooltip
	 */
	public static function ajax_create( $text, $callback, $args=array(), $max_width=300) {
		return '<span '.self::ajax_open_tag_attrs($callback,$args,$max_width).'>'.$text.'</span>';
	}

	/**
	* Returns a 2-column formatted table
	*
	* @param array keys are captions, values are values
	*/
	public static function format_info_tooltip( $arg) {
		$table='<TABLE WIDTH="280" cellpadding="2">';
		foreach ($arg as $k=>$v){
			$table.='<TR><TD WIDTH="90"><STRONG>';
			$table.=$k.'</STRONG></TD><TD bgcolor="white" style="word-wrap: break-word;">';
			$table.= $v; // Value
			$table.='</TD></TR>';
		}
		$table.='</TABLE>';
		return $table;
	}

	public static function tooltip_leightbox_mode() {
		static $init = null;
		if (!isset($_REQUEST['__location'])) $loc = true;
		else $loc = $_REQUEST['__location'];
		if ($init!==$loc) {
			Base_ThemeCommon::load_css('Utils/Tooltip','leightbox_mode');
			Libs_LeightboxCommon::display('tooltip_leightbox_mode', '<center><span id="tooltip_leightbox_mode_content" /></center>');
			$init = $loc;
		}
		return Libs_LeightboxCommon::get_open_href('tooltip_leightbox_mode').' onmousedown="Utils_Tooltip__leightbox_mode(this)" ';
	}
	
}

load_js('modules/Utils/Tooltip/js/Tooltip.js');
Utils_TooltipCommon::init_tooltip_div();

?>
