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
use Joomla\CMS\Language\Text;

/**
 * Custom field for storage indicator
 */
class StorageindicatorField extends FormField
{
    /**
     * The field type.
     *
     * @var    string
     */
    protected $type = 'Storageindicator';

    /**
     * Get directory size
     *
     * @param   string  $path  Directory path
     *
     * @return  integer
     */
    private function getDirectorySize($path)
    {
        $fileSize = 0;
        if (!is_dir($path)) return 0;
        $dir = scandir($path);

        foreach ($dir as $file) {
            if ($file != '.' && $file != '..') {
                if (is_dir($path . '/' . $file)) {
                    $fileSize += $this->getDirectorySize($path . '/' . $file);
                } else {
                    $fileSize += filesize($path . '/' . $file);
                }
            }
        }

        return $fileSize;
    }

    /**
     * Method to get the field input markup.
     *
     * @return  string  The field input markup.
     */
    protected function getInput()
    {
        $totalfree = (int) (disk_free_space(JPATH_ROOT) / 1048576);
        $params    = $this->form->getData()->get("params");
        $totalstorage = (int) ($params->maxstorage ?? 1000);
        
        $uploadstorage = $params->uploadstorage ?? (JPATH_ROOT . '/images/radicalform');
        $storagedirectory = (int) ($this->getDirectorySize($uploadstorage) / 1048576);
        
        $percentage = ($totalstorage > 0) ? (int) (($storagedirectory / $totalstorage) * 100) : 0;
        
        $html = '<div class="progress" style="max-width: 800px"> <div class="progress-bar" role="progressbar" style="width: ' . $percentage . '%;" aria-valuenow="' . $percentage . '" aria-valuemin="0" aria-valuemax="100"></div> </div>';
        
        if (($totalstorage - $storagedirectory) > $totalfree) {
            $html .= '<div class="alert alert-danger" style="max-width: 750px;"> ' . Text::_('PLG_RADICALFORM_TOTALDANGER') . ' </div>';
        }

        $class = "bg-success";
        $upfree = ($totalfree > 0) ? (int) ((($totalstorage - $storagedirectory) / $totalfree) * 100) : 0;
        
        if ($upfree > 70) $class = "bg-warning";
        if ($upfree > 90) $class = "bg-danger";
        
        $html .= Text::sprintf('PLG_RADICALFORM_TOTALFREE', ($totalstorage - $storagedirectory), $totalfree) . 
                 '<div class="progress" style="max-width: 800px"> <div class="progress-bar ' . $class . '" role="progressbar" style="width: ' . $upfree . '%;" aria-valuenow="' . $upfree . '" aria-valuemin="0" aria-valuemax="100"></div> </div>';
        
        return Text::sprintf('PLG_RADICALFORM_TOTALSIZE', $totalstorage, $storagedirectory) . $html;
    }
}
