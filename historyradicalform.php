<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  System.Radicalform
 *
 * @copyright   Copyright 2018 Progreccor
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */
defined('_JEXEC') or die('Restricted access');

class JFormFieldHistoryradicalform extends JFormField {

	private function getCSV($file, $delimiter = ';')
	{
		$a = [];

		if (file_exists($file) && ($handle = fopen($file, 'r')) !== false)
		{
			while (($data = fgetcsv($handle, 200000, $delimiter)) !== false)
			{
				$a[] = $data;
			}
			fclose($handle);
		}
		return $a;
	}

	function getInput() {


		$params=$this->form->getData()->get("params");
		$config = JFactory::getConfig();

		$site_offset = $config->get('offset'); //get offset of joomla time like asia/kolkata

		$log_path = str_replace('\\', '/', JFactory::getConfig()->get('log_path'));

		$data = $this->getCSV($log_path . '/plg_system_radicalform.php', "\t");
		if(count($data)>0)
		{
			for ($i = 0; $i < 6; $i++)
			{
				if (count($data[$i]) < 4 || $data[$i][0][0] == '#')
				{
					unset($data[$i]);
				}
			}
		}
		$data = array_reverse($data);

		$cnt = count($data);

		if ($cnt)
		{
			$html= "<p>".JText::_('PLG_RADICALFORM_HISTORY_SIZE')."<span style='color: green; font-weight: bold'>".filesize($log_path . '/plg_system_radicalform.php')."</span> ".JText::_('PLG_RADICALFORM_HISTORY_BYTE')."</p>";
			$html.="<p><button class='btn btn-danger' id='historyclear'>".JText::_('PLG_RADICALFORM_HISTORY_CLEAR')."</button></p>";
			$html.="<br><br>";
			$html.= '<table class="table table-striped table-bordered adminlist" style="max-width: 960px"><thead><tr>';
			$html.= '<th width="">' . JText::_('PLG_RADICALFORM_HISTORY_TIME') . '</th>';
			if ($params->showtarget) {
				$html.= '<th width="">' . JText::_('PLG_RADICALFORM_HISTORY_TARGET') . '</th>';
			}
			if ($params->showformid) {
				$html.= '<th width="">' . JText::_('PLG_RADICALFORM_HISTORY_FORMID') . '</th>';
			}
			$html.= '<th width="">' . JText::_('PLG_RADICALFORM_HISTORY_IP') . '</th>';
			$html.= '<th>' . JText::_('PLG_RADICALFORM_HISTORY_MESSAGE') . '</th>';
			$html.= '</tr></thead><tbody>';
			foreach ($data as $i => $item)
			{
				$json = json_decode($item[2], true);
				if(is_array($json))
				{
					// new format of log file
					$json_result = json_last_error() === JSON_ERROR_NONE;

					$itog = "";
					$extrainfo="<div class='rfMarginTop muted small'>";
					if ($params->hiddeninfo)
					{
						if(isset($json["url"]))
						{
							$extrainfo.=JText::_('PLG_RADICALFORM_URL').'<b>'.$json["url"]."</b><br>";
						}
						if(isset($json["reffer"]))
						{
							$extrainfo.=JText::_('PLG_RADICALFORM_REFFER').'<b>'.$json["reffer"]."</b><br>";
						}
						if(isset($json["resolution"]))
						{
							$extrainfo.=JText::_('PLG_RADICALFORM_RESOLUTION').'<b>'.$json["resolution"]."</b><br>";
						}
						if(isset($json["pagetitle"]))
						{
							$extrainfo.=JText::_('PLG_RADICALFORM_PAGETITLE').'<b>'.$json["pagetitle"]."</b><br>";
						}
						if(isset($json["rfUserAgent"]))
						{
							$extrainfo.=JText::_('PLG_RADICALFORM_USERAGENT').'<b>'.$json["rfUserAgent"]."</b><br>";
						}

					}
					$extrainfo.="</div>";
					if(isset($json["url"]))
					{
						unset($json["url"]);
					}
					if(isset($json["reffer"]))
					{
						unset($json["reffer"]);
					}
					if(isset($json["resolution"]))
					{
						unset($json["resolution"]);
					}
					if(isset($json["pagetitle"]))
					{
						unset($json["pagetitle"]);
					}
					if(isset($json["rfUserAgent"]))
					{
						unset($json["rfUserAgent"]);
					}

					if ($params->showtarget) {
						if (isset($json["rfTarget"]) && (!empty($json["rfTarget"])))
						{
							$target="<td>".JText::_($json["rfTarget"])."</td>";
							unset($json["rfTarget"]);
						}
						else
						{
							$target="<td></td>";
						}
					}
					else
					{
						$target="";
					}

					if ($params->showformid) {
						if (isset($json["rfFormID"]) && (!empty($json["rfFormID"])))
						{
							$formid="<td>".JText::_($json["rfFormID"])."</td>";
							unset($json["rfFormID"]);
						}
						else
						{
							$formid="<td></td>";
						}
					}
					else
					{
						$formid="";
					}

					if(isset($json[""]))
					{
						$extrainfo.=JText::_('PLG_RADICALFORM_USERAGENT').'<b>'.$json["rfUserAgent"]."</b><br>";
					}


					foreach ($json as $key => $record)
					{
						if (is_array($record))
						{
							$record = implode($params->glue, $record);
						}
						$itog .= JText::_($key) . ": <b>" . $record . "</b><br />";
					}

					$jdate=JFactory::getDate($item[0]);
					$timezone = new DateTimeZone( $site_offset );
					$jdate->setTimezone($timezone);

					$html .= '<tr class="row' . ($i % 2) . '">' .
						'<td class="nowrap">'. $jdate->format('H:i:s',true) . '<br><br>' . $jdate->format('d.m.Y',true) .'</td>' .
						$target .
						$formid.
						'<td><a href="http://whois.domaintools.com/' . $item[1] . '" target="_blank">' . $item[1] . '</a></td>';
					if (isset($item[3]) && $item[3] == "WARNING")
					{
						$html .= '<td style="max-width: 700px; overflow: hidden; color: #9f2620;">' . ($json_result ? '' . $itog . '' : htmlspecialchars($item[2])) . '</td>' .
							'</tr>';
					}
					else
					{
						$html .= '<td style="max-width: 700px; overflow: hidden;">' . ($json_result ? '' . $itog . '' : htmlspecialchars($item[2])) . $extrainfo.'</td>' .
							'</tr>';
					}
				}
				else
				{
					// old log file format
					$json        = json_decode($item[3], true);
					$json_result = json_last_error() === JSON_ERROR_NONE;

					$itog = "";
					if (!$params->hiddeninfo)
					{
						unset($json["reffer"]);
						unset($json["resolution"]);
						unset($json["url"]);
					}
					foreach ($json as $key => $record)
					{
						if (is_array($record))
						{
							$record = implode($params->glue, $record);
						}
						$itog .= JText::_($key) . ": <b>" . $record . "</b><br />";
					}
					$html .= '<tr class="row' . ($i % 2) . '">' .
						'<td class="nowrap">' . $item[0] . '</td>' .
						'<td>' . $item[1] . '</td>' .
						'<td><a href="http://whois.domaintools.com/' . $item[2] . '" target="_blank">' . $item[2] . '</a></td>';
					if (isset($item[4]) && $item[4] == "WARNING")
					{
						$html .= '<td style="max-width: 700px; overflow: hidden; color: #9f2620;">' . ($json_result ? '' . $itog . '' : htmlspecialchars($item[3])) . '</td>' .
							'</tr>';
					}
					else
					{
						$html .= '<td style="max-width: 700px; overflow: hidden;">' . ($json_result ? '' . $itog . '' : htmlspecialchars($item[3])) . '</td>' .
							'</tr>';
					}
				}

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
