<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  System.Radicalform
 *
 * @copyright   Copyright 2020 Progreccor
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace RadicalForm\Plugin\System\RadicalForm\Field;

defined('_JEXEC') or die;

use Joomla\CMS\Form\FormField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

/**
 * Custom field for RadicalForm button in admin
 */
class ButtonradicalformField extends FormField
{
    /**
     * The field type.
     *
     * @var    string
     */
    protected $type = 'Buttonradicalform';

    /**
     * Method to get the field input markup.
     *
     * @return  string  The field input markup.
     */
    protected function getInput()
    {
        HTMLHelper::_('script', 'plg_system_radicalform/adminscript.min.js', ['version' => 'auto', 'relative' => true]);
        HTMLHelper::_('stylesheet', 'plg_system_radicalform/adminscript.css', ['version' => 'auto', 'relative' => true]);

        return '<button type="button" id="' . $this->id . '" class="btn btn-secondary control-group">
            <span class="icon-refresh" aria-hidden="true"></span>
            ' . Text::_($this->element['value']) . '</button><div id="radicalformresult"></div>';
    }
}
