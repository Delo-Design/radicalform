<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  System.Radicalform
 *
 * @copyright   Copyright 2018 Progreccor
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */
defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Uri\Uri;
use Joomla\Registry\Registry;

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

		$r = Factory::getApplication();
		$r     = $r->input;
		$get   = $r->get->getArray();

		$l=Uri::getInstance();

		$http = parse_url($l->toString());
		parse_str($http['query'], $output);
		if(isset($output['page'])) {
			unset($output['page']);
		}
		$currentURL = $http["path"] . '?' . http_build_query($output);



		$params=$this->form->getData()->get("params");
		$config = JFactory::getConfig();

		$site_offset = $config->get('offset'); //get offset of joomla time like asia/kolkata

		$log_path = str_replace('\\', '/', JFactory::getConfig()->get('log_path'));

		$page = '';
		if(isset($get['page']))
		{
			if ($get['page'] == "0")
			{
				$page = '';
			}
			else
			{
				$page = $get['page'].".";
			}
		}

		$logFiles=JTEXT::_("PLG_RADICALFORM_FILE_LOGS");

		$logFiles = '<ul class="nav nav-tabs historytable">';
		if($page)
		{
			$logFiles .= " <li class='nav-item'><a href='{$currentURL}&page=0#attrib-list' class='nav-link' >plg_system_radicalform.php</a> </li>";
		}
		else
		{
			$logFiles .= "<li class='nav-item active'><a  class='nav-link active' aria-current='page' >plg_system_radicalform.php</a></li> ";
		}

		foreach (glob($log_path . "/*.plg_system_radicalform.php") as $filename)
		{
			$currentNumber =  strstr ( pathinfo($filename, PATHINFO_BASENAME) , ".", true );
			if($currentNumber == $page)
			{
				$logFiles .= "<li class='nav-item active'><a  class='nav-link active' aria-current='page' >".pathinfo($filename, PATHINFO_BASENAME)."</a></li> ";
			}
			else
			{
				$logFiles .= "<li class='nav-item'><a href='{$currentURL}&page={$currentNumber}#attrib-list' class='nav-link' >".pathinfo($filename, PATHINFO_BASENAME)."</a></li> ";
			}
		}
		$logFiles .= '</ul>';

		$data = $this->getCSV($log_path . '/'.$page.'plg_system_radicalform.php', "\t");
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

		$plugin   = PluginHelper::getPlugin('system', 'logrotation');
		$warningAboutRotation="";
		if($plugin)
		{
			$pars   = new Registry($plugin->params);
			$cache_timeout = (int) $pars->get('cachetimeout', 30);
			$cache_timeout = 24 * 3600 * $cache_timeout;
			$now  = time();
			$last = (int) $pars->get('lastrun', 0);

			$warningAboutRotation = JText::sprintf("PLG_RADICALFORM_WARNING_ABOUT_ROTATION",(abs($cache_timeout - ($now - $last))/(3600*24)));
		}


		if ($cnt)
		{
			$html= "<p class='firstEntry'>".JText::_('PLG_RADICALFORM_HISTORY_SIZE')."<strong>".filesize($log_path .'/'.$page . 'plg_system_radicalform.php')."</strong> ".JText::_('PLG_RADICALFORM_HISTORY_BYTE').$warningAboutRotation."</p>";
			$html.="<p class='historytable'><button class='btn btn-danger' id='historyclear'>".JText::sprintf('PLG_RADICALFORM_HISTORY_CLEAR', $page."plg_system_radicalform.php").
				"</button> <button class='btn btn-outline-danger' id='numberclear'>".JText::_('PLG_RADICALFORM_HISTORY_NUMBER_CLEAR').
				"</button> <span class='pull-right float-end'><a href='index.php?option=com_ajax&plugin=radicalform&format=raw&group=system&admin=4&page=".(($page == "")?"0":strstr($page,".",true))."' class='btn btn-outline-primary' id='exportcsv'>".JText::sprintf('PLG_RADICALFORM_EXPORT_CSV',$page."plg_system_radicalform.php").
				"</a></span></p>";
			$html.="<br><br>".$logFiles;




			$html.= '<table class="table table-striped table-bordered adminlist historytable" ><thead><tr>';
			$html .= "<th>#</th>";
			$html.= '<th width="">' . JText::_('PLG_RADICALFORM_HISTORY_TIME') . '</th>';
			if (isset($params->showtarget) && $params->showtarget) {
				$html.= '<th width="">' . JText::_('PLG_RADICALFORM_HISTORY_TARGET') . '</th>';
			}
			if (isset($params->showformid) && $params->showformid) {
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
                        if(isset($json["rf-time"]))
                        {
                            $extrainfo.=JText::_('PLG_RADICALFORM_USER_TIME').'<b>'.$json["rf-time"]."</b><br>";
                        }
                        if(isset($json["rf-duration"]))
                        {
                            $extrainfo.=JText::sprintf('PLG_RADICALFORM_FORM_DURATION', $json["rf-duration"]);
                        }

					}
					$extrainfo.="</div>";
					if(isset($json["url"]))
					{
						unset($json["url"]);
					}
                    if(isset($json["rf-duration"]))
                    {
                        unset($json["rf-duration"]);
                    }
                    if(isset($json["rf-time"]))
                    {
                        unset($json["rf-time"]);
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
					$latestNumber="";
					if(isset($json["rfLatestNumber"]))
					{
						$latestNumber=$json["rfLatestNumber"];
						unset($json["rfLatestNumber"]);
					}


					if (isset($params->showtarget) && $params->showtarget) {
						if (isset($json["rfTarget"]) && (!empty($json["rfTarget"])))
						{
							$target="<td>".JText::_($json["rfTarget"])."</td>";
							unset($json["rfTarget"]);
						}
						else
						{
							$target="<td></td>";
							if (isset($json["rfTarget"]))
							{
								unset($json["rfTarget"]);
							}
						}
					}
					else
					{
						$target="";
						if (isset($json["rfTarget"]))
						{
							unset($json["rfTarget"]);
						}
					}

					if (isset($params->showformid) && $params->showformid) {
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
						'<td>'.$latestNumber.'</td>'.
						'<td class="nowrap">'. $jdate->format('H:i:s',true) . '<br><span class="muted">' . $jdate->format('d.m.Y',true) .'</span></td>' .
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
			$html = "{$logFiles}<p class='firstEntry'>{$warningAboutRotation}</p>".'<div class="historytable"><div class="alert alert-info  alert-dismissible show">' . JText::sprintf('PLG_RADICALFORM_HISTORY_EMPTY',$page."plg_system_radicalform.php") . '</div></div>';
		}

		$html = preg_replace('/(?<!a href=\'|\")(?<!src=\"|\')((http)+(s)?:\/\/[^<>\s]+)(?<![\.,:])/i', "<a href='$0' target='_blank'>$0</a>", $html);
		return $html;
	}


}
