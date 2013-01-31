<?php
/**
 * @package epesi-libs
 * @subpackage QuickForm
 */

/* vim: set expandtab tabstop=4 shiftwidth=4: */
// +----------------------------------------------------------------------+
// | PHP version 4.0                                                      |
// +----------------------------------------------------------------------+
// | Copyright (c) 1997-2003 The PHP Group                                |
// +----------------------------------------------------------------------+
// | This source file is subject to version 2.0 of the PHP license,       |
// | that is bundled with this package in the file LICENSE, and is        |
// | available at through the world-wide-web at                           |
// | http://www.php.net/license/2_02.txt.                                 |
// | If you did not receive a copy of the PHP license and are unable to   |
// | obtain it through the world-wide-web, please send a note to          |
// | license@php.net so we can mail you a copy immediately.               |
// +----------------------------------------------------------------------+
// | Authors: Alexey Borzov <borz_off@cs.msu.su>                          |
// |          Adam Daniel <adaniel1@eesus.jnj.com>                        |
// |          Bertrand Mansion <bmansion@mamasam.com>                     |
// |          Thomas Schulz <ths@4bconsult.de>                            |
// +----------------------------------------------------------------------+
//
//
// $Id: Array.php,v 1.9 2004/10/15 20:00:48 ths Exp $

require_once 'HTML/QuickForm/Renderer.php';

/**
 * A concrete renderer for HTML_QuickForm, makes an array of form contents
 *
 * Based on old toArray() code.
 *
 * The form array structure is the following:
 * array(
 *   'frozen'           => 'whether the form is frozen',
 *   'javascript'       => 'javascript for client-side validation',
 *   'attributes'       => 'attributes for <form> tag',
 *   'requirednote      => 'note about the required elements',
 *   // if we set the option to collect hidden elements
 *   'hidden'           => 'collected html of all hidden elements',
 *   // if there were some validation errors:
 *   'errors' => array(
 *     '1st element name' => 'Error for the 1st element',
 *     ...
 *     'nth element name' => 'Error for the nth element'
 *   ),
 *   // if there are no headers in the form:
 *   'elements' => array(
 *     element_1,
 *     ...
 *     element_N
 *   )
 *   // if there are headers in the form:
 *   'sections' => array(
 *     array(
 *       'header'   => 'Header text for the first header',
 *       'name'     => 'Header name for the first header',
 *       'elements' => array(
 *          element_1,
 *          ...
 *          element_K1
 *       )
 *     ),
 *     ...
 *     array(
 *       'header'   => 'Header text for the Mth header',
 *       'name'     => 'Header name for the Mth header',
 *       'elements' => array(
 *          element_1,
 *          ...
 *          element_KM
 *       )
 *     )
 *   )
 * );
 *
 * where element_i is an array of the form:
 * array(
 *   'name'      => 'element name',
 *   'value'     => 'element value',
 *   'type'      => 'type of the element',
 *   'frozen'    => 'whether element is frozen',
 *   'label'     => 'label for the element',
 *   'required'  => 'whether element is required',
 *   'error'     => 'error associated with the element',
 *   'style'     => 'some information about element style (e.g. for Smarty)',
 *   // if element is not a group
 *   'html'      => 'HTML for the element'
 *   // if element is a group
 *   'separator' => 'separator for group elements',
 *   'elements'  => array(
 *     element_1,
 *     ...
 *     element_N
 *   )
 * );
 *
 * @access public
 */
class HTML_QuickForm_Renderer_TCMSArray extends HTML_QuickForm_Renderer
{
   /**
    * An array being generated
    * @var array
    */
    var $_ary;

   /**
    * Number of sections in the form (i.e. number of headers in it)
    * @var integer
    */
    var $_sectionCount;

   /**
    * Current section number
    * @var integer
    */
    var $_currentSection;

   /**
    * Array representing current group
    * @var array
    */
    var $_currentGroup = null;

   /**
    * Additional style information for different elements
    * @var array
    */
    var $_elementStyles = array();

   /**
    * true: collect all hidden elements into string; false: process them as usual form elements
    * @var bool
    */
    var $_collectHidden = false;

   /**
    * true:  render an array of labels to many labels, $key 0 named 'label', the rest "label_$key"
    * false: leave labels as defined
    * @var bool
    */
    var $staticLabels = false;
    var $inline_errors = false;

   /**
    * Constructor
    *
    * @param  bool    true: collect all hidden elements into string; false: process them as usual form elements
    * @param  bool    true: render an array of labels to many labels, $key 0 to 'label' and the oterh to "label_$key"
    * @access public
    */
    function HTML_QuickForm_Renderer_TCMSArray($collectHidden = false, $staticLabels = false)
    {
        $this->HTML_QuickForm_Renderer();
        $this->_collectHidden = $collectHidden;
        $this->_staticLabels  = $staticLabels;
        //print "<div id='asdfre'></div>";
//        $js = "HTML_QuickForm_Renderer_TCMSArray_error=function(err_id, error){terefere = $(err_id);if(terefere) terefere.innerHTML = error;}";
//		eval_js($js);
    } // end constructor

    public function set_inline_errors($inline_errors=true) {
        $this->inline_errors = $inline_errors;
    }

   /**
    * Returns the resultant array
    *
    * @access public
    * @return array
    */
    function toArray()
    {
        return $this->_ary;
    }


    function startForm(&$form)
    {
        $this->_ary = array(
            'frozen'            => $form->isFrozen(),
            'javascript'        => $form->getValidationScript(),
            'attributes'        => $form->getAttributes(true),
            'requirednote'      => $form->getRequiredNote(),
            'errors'            => array()
        );
        if ($this->_collectHidden) {
            $this->_ary['hidden'] = '';
        }
        $this->_elementIdx     = 1;
        $this->_currentSection = null;
        $this->_sectionCount   = 0;
        $this->_formName = $form->getAttribute('name');
	load_js('modules/Libs/QuickForm/Renderer/TCMSDefault.js');
    } // end func startForm


    function renderHeader(&$header)
    {
        $this->_ary['sections'][$this->_sectionCount] = array(
            'header' => $header->toHtml(),
            'name'   => $header->getName()
        );
        $this->_currentSection = $this->_sectionCount++;
    } // end func renderHeader


    function renderElement(&$element, $required, $error)
    {
    	$this->_prepareValue($element);
        $elAry = $this->_elementToArray($element, $required, $error);
        if (!empty($error)) {
            $this->_ary['errors'][$elAry['name']] = $error;
        }
        $this->_storeArray($elAry);
    } // end func renderElement


    function renderHidden(&$element)
    {
		$this->_prepareValue($element);
        if ($this->_collectHidden) {
            $this->_ary['hidden'] .= $element->toHtml() . "\n";
        } else {
            $this->renderElement($element, false, null);
        }
    } // end func renderHidden


    function startGroup(&$group, $required, $error)
    {
        $this->_currentGroup = $this->_elementToArray($group, $required, $error);
        if (!empty($error)) {
            $this->_ary['errors'][$this->_currentGroup['name']] = $error;
        }
    } // end func startGroup


    function finishGroup(&$group)
    {
        $this->_storeArray($this->_currentGroup);
        $this->_currentGroup = null;
    } // end func finishGroup


   /**
    * Creates an array representing an element
    *
    * @access private
    * @param  object    An HTML_QuickForm_element object
    * @param  bool      Whether an element is required
    * @param  string    Error associated with the element
    * @return array
    */
    function _elementToArray(&$element, $required, $error) {
		$type = $element->getType();
		$name = $element->getName();
		$err_id = 'error_' . $this->_formName . "_" . $name . "_" . $type;
		
        $ret = array(
            'name'      => $element->getName(),
            'value'     => $element->getValue(),
            'type'      => $element->getType(),
            'frozen'    => $element->isFrozen(),
            'required'  => $required,
           	'error'		=> '<span class="form_error" id="'.htmlspecialchars($err_id).'">'.($this->inline_errors?$error:'').'</span>'
        );
        
        // render label(s)
        $labels = $element->getLabel();
        if (is_array($labels) && $this->_staticLabels) {
            foreach($labels as $key => $label) {
                $key = is_int($key)? $key + 1: $key;
                if (1 === $key) {
                    $ret['label'] = $label;
                } else {
                    $ret['label_' . $key] = $label;
                }
            }
        } else {
            $ret['label'] = $labels;
        }

        // set the style for the element
        if (isset($this->_elementStyles[$ret['name']])) {
            $ret['style'] = $this->_elementStyles[$ret['name']];
        }
        if ('group' == $ret['type']) {
            $ret['separator'] = $element->_separator;
            $ret['elements']  = array();
        } else {
            $ret['html']      = $element->toHtml();
        }
        //*** The New Error JS
//        $js = "HTML_QuickForm_Renderer_TCMSArray_error(\"".htmlspecialchars($err_id)."\", \"".$error."\")";
    if (!$this->inline_errors)
      	eval_js('seterror(\''.$err_id.'\',\''.addslashes($error).'\')');
        return $ret;
    }


   /**
    * Stores an array representation of an element in the form array
    *
    * @access private
    * @param array  Array representation of an element
    * @return void
    */
    function _storeArray($elAry)
    {
        // where should we put this element...
        if (is_array($this->_currentGroup) && ('group' != $elAry['type'])) {
            $this->_currentGroup['elements'][] = $elAry;
        } elseif (isset($this->_currentSection)) {
            $this->_ary['sections'][$this->_currentSection]['elements'][] = $elAry;
        } else {
            $this->_ary['elements'][] = $elAry;
        }
    }


   /**
    * Sets a style to use for element rendering
    *
    * @param mixed      element name or array ('element name' => 'style name')
    * @param string     style name if $elementName is not an array
    * @access public
    * @return void
    */
    function setElementStyle($elementName, $styleName = null)
    {
        if (is_array($elementName)) {
            $this->_elementStyles = array_merge($this->_elementStyles, $elementName);
        } else {
            $this->_elementStyles[$elementName] = $styleName;
        }
    }
    
	function _prepareValue(&$element, $group_name=null) {
		return;
		$type = $element->getType();
    	$name = $element->getName();
    	if ($group_name) $name = $group_name.'['.$name.']';
		$value = '';
		if($element->getType()=='group') {
			foreach ($element->_elements as $e)
				$this->_prepareValue($e, $name);
			return;
		}
		if(!$element->isFrozen()) {
			if($type == 'text' || $type=='textarea' || $type=='hidden') {
				$value = $element->getValue();
        	    $element->setValue('');
        		if($value!==null) {
					eval_js('settextvalue(\''.$this->_formName.'\',\''.$name.'\',"'.str_replace("\n",'\n',addslashes($value)).'")');
    			}
			} elseif($type == 'select') {
				$value = $element->getValue();
  				$element->setValue(array());
				if($element->getMultiple()) $name .= '[]'; 
				if($value!==null)
				foreach($value as $v) {
					eval_js('setselectvalue(\''.$this->_formName.'\',\''.$name.'\',\''.str_replace("\n",'\n',addslashes(addslashes($v))).'\')');
				}
			} elseif($type == 'checkbox' || $type=='radio') {
	    		$value = $element->getAttribute('checked');
	        	$element->removeAttribute('checked');
    			if($value!==null) {
					if($type=='checkbox')
						eval_js('setcheckvalue(\''.$this->_formName.'\',\''.$name.'\',\''.addslashes(addslashes($value)).'\')');
					else
						eval_js('setradiovalue(\''.$this->_formName.'\',\''.$name.'\',\''.str_replace("\n",'\n',addslashes(addslashes($element->getValue()))).'\')');
    			}
			} else {
				$value = $element->getValue();
        		if ($value!==null && !is_array($value)) eval_js('settextvalue(\''.$this->_formName.'\',\''.$name.'\',"'.str_replace("\n",'\n',addslashes($value)).'")');
			}
		}
	}
   
}
?>