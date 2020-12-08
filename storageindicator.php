<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  System.Radicalform
 *
 * @copyright   Copyright 2020 Progreccor
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */
defined('_JEXEC') or die('Restricted access');

class JFormFieldStorageindicator extends JFormField {
	function getDirectorySize($path)
	{
		$fileSize = 0;
		$dir = scandir($path);

		foreach($dir as $file)
		{
			if (($file!='.') && ($file!='..'))
				if(is_dir($path . '/' . $file))
					$fileSize += $this->getDirectorySize($path.'/'.$file);
				else
					$fileSize += filesize($path . '/' . $file);
		}

		return $fileSize;
	}

	function getInput() {
		$totalfree=(int)(disk_free_space(JPATH_ROOT)/1048576);
		$params=$this->form->getData()->get("params");
		if(isset($params->maxstorage))
		{
			$totalstorage=$params->maxstorage;
		}
		else
		{
			$totalstorage=1000;
		}
		$totaldirectory=(int)($this->getDirectorySize(JPATH_ROOT)/1048576);
		$storagedirectory=(int)($this->getDirectorySize($params->uploadstorage)/1048576);
		$html='    <div class="progress" style="max-width: 800px"> <div class="bar" style="width: '.(int)(($storagedirectory/$totalstorage)*100).'%;"></div> </div>';
		$total=(int)(disk_total_space(JPATH_ROOT)/1048576);

		$upfree=(int)((($totalstorage-$storagedirectory)/$totalfree)*100);
		// when the storage more than free space on the system
		if(($totalstorage-$storagedirectory)>$totalfree)
		{
			$html.='<div class="alert alert-error" style="max-width: 750px;"> '.JText::_('PLG_RADICALFORM_TOTALDANGER').' </div>';
		}

		$class="progress-success";
		if($upfree>70)
		{
			$class="progress-warning";
		}
		if($upfree>90)
		{
			$class="progress-danger";
		}
		$html.=JText::sprintf('PLG_RADICALFORM_TOTALFREE',($totalstorage-$storagedirectory),$totalfree).'<div class="progress '.$class.'" style="max-width: 800px"> <div class="bar" style="width: '.$upfree.'%;"></div> </div>';
		return JText::sprintf('PLG_RADICALFORM_TOTALSIZE',$totalstorage,$storagedirectory).$html;
	}

}