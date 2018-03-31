<?php
/**
 * @package     ${NAMESPACE}
 * @subpackage
 *
 * @copyright   A copyright
 * @license     A "Slug" license name e.g. GPL2
 */
defined('_JEXEC') or die('Restricted access');

class JFormFieldButtonradicalform extends JFormField {

	function getInput() {
		JHtml::_('script', 'plg_system_radicalform/adminscript.js', array('version' => 'auto', 'relative' => true));
		return "<button onclick=\"\" id='".$this->element['id']."' class=\"btn \">
	<span class=\"icon-refresh\"></span>
	".JText::_($this->element['value'])."</button><div id=\"radicalformresult\"></div>";
	}

}