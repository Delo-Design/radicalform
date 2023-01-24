<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  System.Radicalform
 *
 * @copyright   Copyright 2020 Progreccor
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */
defined('_JEXEC') or die('Restricted access');

class JFormFieldButtonradicalform extends JFormField {

	function getInput() {
		JHtml::_('script', 'plg_system_radicalform/adminscript.min.js', array('version' => filemtime ( __FILE__ ), 'relative' => true));
		JHtml::_('stylesheet', 'plg_system_radicalform/adminscript.css', array('version' => filemtime ( __FILE__ ), 'relative' => true));
		return "<button onclick=\"\" id='".$this->element['id']."' class=\"btn btn-secondary control-group\">
	<span class=\"icon-refresh\"></span>
	".JText::_($this->element['value'])."</button><div id=\"radicalformresult\"></div>";
	}

}