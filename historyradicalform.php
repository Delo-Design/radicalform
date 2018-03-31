<?php
/**
 * @package     ${NAMESPACE}
 * @subpackage
 *
 * @copyright   A copyright
 * @license     A "Slug" license name e.g. GPL2
 */
defined('_JEXEC') or die('Restricted access');

class JFormFieldHistoryradicalform extends JFormField {

	private function getCSV($file, $delimiter = ';')
	{
		$a = [];
		if (($handle = fopen($file, 'r')) !== false)
		{
			while (($data = fgetcsv($handle, 10000, $delimiter)) !== false)
			{
				$a[] = $data;
			}
			fclose($handle);
		}
		return $a;
	}

	function getInput() {

		$log_path = str_replace('\\', '/', JFactory::getConfig()->get('log_path'));

		$data = $this->getCSV($log_path . '/plg_system_radicalform.php', "\t");
		for ($i = 0; $i < 6; $i++)
		{
			if (count($data[$i]) < 4 || $data[$i][0][0] == '#')
			{
				unset($data[$i]);
			}
		}
		$data = array_reverse($data);

		$cnt = count($data);

		if ($cnt)
		{
			$html= "<p>".JText::_('PLG_RADICALFORM_HISTORY_SIZE')."<span style='color: green; font-weight: bold'>".filesize($log_path . '/plg_system_radicalform.php')."</span> ".JText::_('PLG_RADICALFORM_HISTORY_BYTE')."</p>";
			$html.="<p><button class='btn btn-danger' id='historyclear'>".JText::_('PLG_RADICALFORM_HISTORY_CLEAR')."</button></p>";
			$html.= '<table class="table table-striped table-bordered adminlist" style="max-width: 900px"><thead><tr>';
			$html.= '<th width="5%">' . JText::_('PLG_RADICALFORM_HISTORY_TIME') . '</th>';
			$html.= '<th width="5%">' . JText::_('PLG_RADICALFORM_HISTORY_DATE') . '</th>';
			$html.= '<th width="5%">' . JText::_('PLG_RADICALFORM_HISTORY_IP') . '</th>';
			$html.= '<th>' . JText::_('PLG_RADICALFORM_HISTORY_MESSAGE') . '</th>';
			$html.= '</tr></thead><tbody>';
			foreach ($data as $i => $item)
			{
				$json = json_decode($item[3],true);
				$json_result = json_last_error() === JSON_ERROR_NONE;

				$itog="";
				foreach ($json as $key=>$record) {
					$itog.=JText::_($key). ": <b>" . $record ."</b><br />";
				}
				$html.= '<tr class="row' . ($i % 2) . '">' .
					'<td class="nowrap">' . $item[0] . '</td>' .
					'<td>' . $item[1] . '</td>' .
					'<td>' . $item[2] . '</td>' .
					'<td>' . ($json_result ? '' . $itog . '' : htmlspecialchars($item[3])) . '</td>' .
					'</tr>';
			}
			$html.= '</tbody></table>';
		}
		else
		{
			$html = '<div class="alert">' . JText::_('PLG_RADICALFORM_HISTORY_EMPTY') . '</div>';
		}



		return $html;
	}


}