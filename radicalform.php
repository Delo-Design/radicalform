<?php
// No direct access
defined('_JEXEC') or die;

/**
 *
 * @package       Joomla.Plugin
 * @subpackage    System.Radicalform
 * @since         3.7+
 * @author        Progreccor
 * @copyright     Copyright 2020 Progreccor
 * @license       GNU General Public License version 2 or later; see LICENSE.txt
 */

use Joomla\CMS\Factory;
use Joomla\String\StringHelper;
use Joomla\CMS\HTML\HTMLHelper;

class plgSystemRadicalform extends JPlugin
{
	private $logPath;

	protected $autoloadLanguage = true;
	protected $db;
	protected $app;
	protected $maxDirSize;

	public function __construct(& $subject, $config)
	{
		parent::__construct($subject, $config);

		JLoader::register('JFile', JPATH_LIBRARIES . '/joomla/filesystem/file.php');
		JLoader::register('JFolder', JPATH_LIBRARIES . '/joomla/filesystem/folder.php');
		$this->maxDirSize = $this->params->get('maxfile');

		$this->logPath = str_replace('\\', '/', Factory::getConfig()->get('log_path')).'/plg_system_radicalform.php';

		JLog::addLogger(
			array(
				// Sets file name
				'text_file' => 'plg_system_radicalform.php',
				// Sets the format of each line
				'text_entry_format' => "{DATETIME}\t{CLIENTIP}\t{MESSAGE}\t{PRIORITY}"
			),
			// Sets all but DEBUG log level messages to be sent to the file
			JLog::ALL & ~JLog::DEBUG,
			array('plg_system_radicalform')
		);

	}

	private function return_bytes($size_str)
	{
		switch (substr($size_str, -1))
		{
			case 'M':
			case 'm':
				return (int) $size_str * 1048576;
			case 'K':
			case 'k':
				return (int) $size_str * 1024;
			default:
				return $size_str;
		}
	}

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

	public function onBeforeCompileHead()
	{
		if($this->params->get('keepalive'))
		{
			HTMLHelper::_('behavior.keepalive');
		}
	}

	public function onAfterRender()
	{

		if ($this->app->isClient('administrator'))
		{
			return false;
		}

		$data = $this->app->input->getArray();
		if(isset($data['tmpl']) && $data['tmpl'] === 'component')
		{
			return false;
		}

		$body  = $this->app->getBody();
		$lnEnd = Factory::getDocument()->_getLineEnd();
		if (strpos($body, 'rf-button-send') !== false)
		{
			$mtime = filemtime(JPATH_ROOT . "/media/plg_system_radicalform/js/script.min.js");
			$jsParams = array(
				'DangerClass'         => $this->params->get('dangerclass'),
				'ErrorFile'           => $this->params->get('errorfile'),
				'thisFilesWillBeSend' => JText::_('PLG_RADICALFORM_THIS_FILES_WILL_BE_SEND'),
				'waitingForUpload'    => $this->params->get('waitingupload'),
				'WaitMessage'         => $this->params->get('rfWaitMessage'),
				'ErrorMax'            => JText::_('PLG_RADICALFORM_FILE_TO_LARGE_THAN_PHP_INI_ALLOWS'),
				'MaxSize'             => min($this->return_bytes(ini_get('post_max_size')),
					$this->return_bytes(ini_get("upload_max_filesize"))),
				'Base'                => JUri::base(true),
				'AfterSend'           => $this->params->get('aftersend'),
				'Jivosite'            => $this->params->get('jivosite'),
				'Verbox'              => $this->params->get('verbox'),
				'Subject'             => $this->params->get('rfSubject'),
				'Token'               => JHtml::_('form.token'),
			);
			if ($this->params->get('insertip'))
			{
				$jsParams['IP'] = json_encode(array('ip' => $_SERVER['REMOTE_ADDR']));
			}
			$js = "<script src=\"" . JURI::base(true) . "/media/plg_system_radicalform/js/script.min.js?$mtime\" async></script>" . $lnEnd
				. "<script>"
				. "var RadicalForm=" . json_encode($jsParams) . ";";

			if (!empty($this->params->get('rfCall_0')))
			{
				$js .= "function rfCall_0(here) { try { " . $this->params->get('rfCall_0') . " } catch (e) { console.error('Radical Form JS Code: ', e); } }; ";
			}
			if (!empty($this->params->get('rfCall_1')))
			{
				$js .= "function rfCall_1(rfMessage, here) { try { " . $this->params->get('rfCall_1') . " } catch (e) { console.error('Radical Form JS Code: ', e); } }; ";
			}
			if (!empty($this->params->get('rfCall_2')))
			{
				$js .= "function rfCall_2(rfMessage, here) { try { " . $this->params->get('rfCall_2') . " } catch (e) { console.error('Radical Form JS Code: ', e); } }; ";
			}
			if (!empty($this->params->get('rfCall_3')))
			{
				$js .= "function rfCall_3(rfMessage, here) { try { " . $this->params->get('rfCall_3') . " } catch (e) { console.error('Radical Form JS Code: ', e); } }; ";
			}
			$js .= " </script>" . $lnEnd;

			$body = str_replace("</body>", $js . "</body>", $body);
			$this->app->setBody($body);

		}
		else
		{
			if( $this->params->get('insertip')  )
			{
				$js    = "<script>"
					. "var RadicalForm={"
					. "IP:{ip: '" . $_SERVER['REMOTE_ADDR'] . "'} "
					. "}; </script>";
				$body = str_replace("</body>", $js . "</body>", $body);
				$this->app->setBody($body);
			}

		}
	}

	public function onAjaxRadicalform()
	{
		$r     = $this->app->input;
		$input = $r->post->getArray();
		$get   = $r->get->getArray();
		$files = $r->files->getArray();
		$source = $input;

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

		if (isset($get['admin']) && ( $get['admin'] == 4 || $get['admin'] == 5 ))
		{
			// 5 зарезервировано для другого вида экспорта
			// это экспорт csv
			if ($this->app->isClient('administrator'))
			{
				$config = Factory::getConfig();
				$site_offset = $config->get('offset'); //get offset of joomla time like asia/kolkata

				$jdate=JFactory::getDate('now');
				$timezone = new DateTimeZone( $site_offset );
				$jdate->setTimezone($timezone);
				$filename="rfexport_".$jdate->format('d-m-Y_H-i_s',true).".csv";

				header("Content-disposition: attachment; filename=${filename}");
				header("Content-Type: text/csv");
				header('Expires: 0');
				header('Cache-Control: no-cache');
				$BOM = "\xEF\xBB\xBF";
				echo $BOM;
				$csv = "#;";
				$csv.= JText::_('PLG_RADICALFORM_HISTORY_TIME') . ';';
				if ($this->params->get('showtarget')) {
					$csv.= JText::_('PLG_RADICALFORM_HISTORY_TARGET') . ';';
				}
				if ($this->params->get('showformid')) {
					$csv.= JText::_('PLG_RADICALFORM_HISTORY_FORMID') . ';';
				}
				$csv.=  JText::_('PLG_RADICALFORM_HISTORY_IP') . ';';
				$csv.= JText::_('PLG_RADICALFORM_HISTORY_MESSAGE') . ';';
				if ($this->params->get('hiddeninfo'))
				{
					$csv.= JText::_('PLG_RADICALFORM_HISTORY_EXTRA') . ';';
				}
				$csv.="\r\n";


				$log_path = str_replace('\\', '/', JFactory::getConfig()->get('log_path'));

				$data = $this->getCSV($log_path . '/'.$page . 'plg_system_radicalform.php', "\t");
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
				if($cnt>0)
				{
					foreach ($data as $i => $item)
					{
						$json = json_decode($item[2], true);
						$jdate=JFactory::getDate($item[0]);
						$timezone = new DateTimeZone( $site_offset );
						$jdate->setTimezone($timezone);

						$latestNumber="";
						if(isset($json["rfLatestNumber"]))
						{
							$latestNumber=$json["rfLatestNumber"];
							unset($json["rfLatestNumber"]);
						}

						if ($this->params->get('showtarget') )
						{
							if (isset($json["rfTarget"]) && (!empty($json["rfTarget"])))
							{
								$target="\"".JText::_($json["rfTarget"])."\";";
								unset($json["rfTarget"]);
							}
							else
							{
								$target=";";
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

						if ($this->params->get('showformid'))
						{
							if (isset($json["rfFormID"]) && (!empty($json["rfFormID"])))
							{
								$formid="\"".JText::_($json["rfFormID"])."\";";
								unset($json["rfFormID"]);
							}
							else
							{
								$formid=";";
							}
						}
						else
						{
							$formid="";
						}

						$extrainfo="";
						if ($this->params->get('hiddeninfo'))
						{
							$extrainfo="\"";
							if(isset($json["url"]))
							{
								$extrainfo.=JText::_('PLG_RADICALFORM_URL').$json["url"]."\n";
							}
							if(isset($json["reffer"]))
							{
								$extrainfo.=JText::_('PLG_RADICALFORM_REFFER').$json["reffer"]."\n";
							}
							if(isset($json["resolution"]))
							{
								$extrainfo.=JText::_('PLG_RADICALFORM_RESOLUTION').$json["resolution"]."\n";
							}
							if(isset($json["pagetitle"]))
							{
								$extrainfo.=JText::_('PLG_RADICALFORM_PAGETITLE').$json["pagetitle"]."\n";
							}
							if(isset($json["rfUserAgent"]))
							{
								$extrainfo.=JText::_('PLG_RADICALFORM_USERAGENT').$json["rfUserAgent"]."\n";
							}
							$extrainfo.="\"";
						}

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

						$csv.="${latestNumber};\"".$jdate->format('H:i:s',true)."\n".$jdate->format('d.m.Y',true)."\";${target}${formid}${item[1]};";
						$csv.="\"";
						if (is_array($json))
						{
							$delimiter="";
							foreach ($json as $key => $record)
							{
								if (is_array($record))
								{
									$record = implode(", ", $record);
								}
								$csv .= $delimiter.JText::_($key) . ": " . $record ;
								$delimiter= "\n";
							}

						}
						if ($this->params->get('hiddeninfo'))
						{
							$csv.="\";${extrainfo};\r\n";
						}
						else
						{
							$csv.="\";\r\n";
						}
					}

				}


				return  $csv;
			}
		}

		if (isset($get['admin']) && $get['admin'] == 1 )
		{
			if ($this->app->isClient('administrator'))
			{
				// тут проверка телеграма на предмет обновлений диалогов (ловим chat_id)

				$qv="https://api.telegram.org/bot".$this->params->get('telegramtoken')."/getUpdates";
				$ch = curl_init();

				if($this->params->get('proxy'))
				{
					$proxy = $this->params->get('proxylogin').":".$this->params->get('proxypassword')."@".$this->params->get('proxyaddress').":".$this->params->get('proxyport');

					curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5);
					curl_setopt($ch, CURLOPT_PROXY, $proxy);
				}

				curl_setopt($ch, CURLOPT_URL, $qv);
				curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
				curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
				curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
				curl_setopt($ch, CURLOPT_HEADER, 0);
				$output=json_decode(curl_exec($ch),true);
				curl_close($ch);
				$output=$output["result"];

				$chatIDs = [];


				// проверяем все сообщения присланные боту и вытаскиваем оттуда chat_id
				foreach ($output as $chat)
				{
					$chatID=$chat["message"]["chat"]["id"];
					$name="";
					if(isset($chat["message"]["chat"]["username"])) {
						$name.=$chat["message"]["chat"]["username"];
					}
					if(isset($chat["message"]["chat"]["first_name"]) || isset($chat["message"]["chat"]["last_name"])) {
						$name.=" (";
					}
					if(isset($chat["message"]["chat"]["first_name"])) {
						$name.=$chat["message"]["chat"]["first_name"];
					}

					if(isset($chat["message"]["chat"]["last_name"])) {
						$name.=" ".$chat["message"]["chat"]["last_name"];
					}
					if(isset($chat["message"]["chat"]["first_name"]) || isset($chat["message"]["chat"]["last_name"])) {
						$name.=")";
					}
					array_push($chatIDs,["name"=>$name,"chatID"=>$chatID]);
				}


				return $chatIDs;
			} else
			{
				return false;
			}
		}

		$config = Factory::getConfig();

		// here we try to load current logfile
		$site_offset = $config->get('offset'); //get offset of joomla time like asia/kolkata

		$log_path = str_replace('\\', '/', Factory::getConfig()->get('log_path'));

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

		// here we get latest serial number from log file
		$latestNumber=1;
		if(count($data)>0)
		{
			$data = array_reverse($data);
			$json = json_decode($data[0][2], true);
			if (is_array($json))
			{
				if (isset($json['rfLatestNumber']))
				{
					$latestNumber = $json['rfLatestNumber'] + 1;
				}
			}
		}


		if (isset($get['admin']) && $get['admin'] == 2 )
		{
			if($page)
			{
				unlink($log_path . '/'.$page.'plg_system_radicalform.php');
			}
			else
			{
				unlink($this->logPath);
				$entry= ['rfLatestNumber' => $latestNumber, 'message' => JText::_('PLG_RADICALFORM_CLEAR_HISTORY') ];
				JLog::add(json_encode($entry), JLog::NOTICE, 'plg_system_radicalform');
			}
			return "ok";
		}

		if (isset($get['admin']) && $get['admin'] == 3 )
		{
			$entry= ['rfLatestNumber' => 0, 'message' => JText::_('PLG_RADICALFORM_RESET_NUMBER') ];
			JLog::add(json_encode($entry), JLog::NOTICE, 'plg_system_radicalform');
			return "ok";
		}

		if (isset($input['uniq']))
		{
			$uniq = (int) $input['uniq'];
		}
		else
		{
			$output["error"] = JText::_('PLG_RADICALFORM_INVALID_TOKEN');

			return $output;
		}
		$uploaddir = JPATH_ROOT . '/tmp/rf-' . $uniq;

		if (isset($get['file']) && $get['file'] == 1)
		{
			// здесь нам передали файл. что же, будем обрабатывать

			// вначале проверим есть ли старые брошенные наши папки
			$folders = JFolder::folders(JPATH_ROOT . '/tmp', "rf-*", false, true);

			foreach ($folders as $folder)
			{
				$dtime = intval(time() - filectime($folder));
				if ($dtime > 86400) // все что старше суток - под нож!
				{
					JFolder::delete($folder);
				}
			}

			$output = [];
			if (!empty($files))
			{

				if (!file_exists($uploaddir))
				{
					mkdir($uploaddir); // создаем папку если ее нет для файлов
				}

				// надо посчитать вначале размер всех файлов в папке
				$totalsize = 0;
				$folders   = JFolder::folders($uploaddir);
				foreach ($folders as $folder)
				{
					foreach (glob($uploaddir . "/" . $folder . "/*.*") as $filename)
					{
						$totalsize += filesize($filename);
					}
				}

				$lang = Factory::getLanguage();

				foreach ($files as $key => $file)
				{
					if ($file['error'] == 4) // ERROR NO FILE
						continue;

					if ($file['error'])
					{
						switch ($file['error'])
						{
							case 1:
								$output["error"] = JText::_('PLG_RADICALFORM_FILE_TO_LARGE_THAN_PHP_INI_ALLOWS');
								break;

							case 2:
								$output["error"] = JText::_('PLG_RADICALFORM_FILE_TO_LARGE_THAN_HTML_FORM_ALLOWS');
								break;

							case 3:
								$output["error"] = JText::_('PLG_RADICALFORM_ERROR_PARTIAL_UPLOAD');
						}
					}
					else
					{

						if (($file['size'] + $totalsize) < $this->maxDirSize)
						{
							if (!$file['name'])
							{
								$output['error'] = JText::_('PLG_RADICALFORM_ERROR_WRONG_TYPE');
							}
							else
							{

								if (!file_exists($uploaddir . "/" . $key))
								{
									mkdir($uploaddir . "/" . $key); // создаем папку если ее нет для файлов
								}
								$uploadedFileName = JFILE::makeSafe($lang->transliterate($file['name']));
								if (JFile::upload($file['tmp_name'], $uploaddir . "/" . $key . "/" . $uploadedFileName))
								{
									$output["name"] = $uploadedFileName;
								}
								else
								{
									$output["error"] = JText::_('PLG_RADICALFORM_ERROR_UPLOAD');
								}
							}
						}
						else
						{

							$output["error"] = JText::_('PLG_RADICALFORM_TOO_MANY_UPLOADS');
						}

					}

				}

			}

			return $output;
		}


		if (Factory::getSession()->isNew() || !Factory::getSession()->checkToken())
		{
			return JText::_('PLG_RADICALFORM_INVALID_TOKEN');
		};

		$mailer = Factory::getMailer();

		$sender = array(
			$config->get('mailfrom'),
			$config->get('fromname')
		);

		$mailer->setSender($sender);


		if (isset($input["rfSubject"]) && (!empty($input["rfSubject"])))
		{
			$subject=$input["rfSubject"];
			unset($input["rfSubject"]);
		}
		else
		{
			$subject=$this->params->get('rfSubject');
		}


		// Expression to search for (positions)
		$regex = '/{(.*?)}/i';

		// Find all instances of fields
		preg_match_all($regex, $subject, $matches, PREG_SET_ORDER);

		// No matches, skip this
		if ($matches)
		{
			foreach ($matches as $match)
			{
				if(isset($input[$match[1]]))
				{
					$set=$input[$match[1]];
					$subject = preg_replace("|$match[0]|", $set, $subject, 1);
				}
			}
		}

		$mailer->setSubject($subject);

		$needToSendFiles = false;
		if (isset($input["needToSendFiles"]) && ($input["needToSendFiles"] == 1))
		{
			// просматриваем все подпапки нашей папки для выгрузки
			$folders = JFolder::folders($uploaddir);
			foreach ($folders as $folder)
			{
				//прикрепляем файлы
				$filesForAttachment = JFolder::files($uploaddir . "/" . $folder, ".", false, true);

				foreach ($filesForAttachment as $file)
				{
					if (isset($input[$folder]))
					{
						$input[$folder] .= ", " . basename($file);
					}
					else
					{
						$input[$folder] = basename($file);
					}
					$mailer->addAttachment($file);
					$needToSendFiles = true;
				}

			}
		}


		unset($input["uniq"]);
		unset($input["needToSendFiles"]);
		unset($input[JSession::getFormToken()]);
		$url=$input["url"];
		$resolution=$input["resolution"];
		$ref=$input["reffer"];
		$pagetitle=$input["pagetitle"];
		$useragent=$input["rfUserAgent"];
		$formID= isset($input["rfFormID"]) ? JText::_($input["rfFormID"]) : '';

		if(file_exists($this->logPath))
		{
			if($this->params->get('maxlogfile')<filesize($this->logPath))
			{
				unlink($this->logPath);
				$entry= ['rfLatestNumber' => $latestNumber, 'message' => JText::_('PLG_RADICALFORM_CLEAR_HISTORY_BY_MAX_LOG') ];
				JLog::add(json_encode($entry), JLog::NOTICE, 'plg_system_radicalform');
				$latestNumber++;
			}
		}

		$input['rfLatestNumber'] = $latestNumber;
		$input = array_filter($input, function($value) { return $value !== ''; }); // delete empty fields in input array

		JLog::add(json_encode($input), JLog::NOTICE, 'plg_system_radicalform');

		if (isset($input["rfTarget"]) && (!empty($input["rfTarget"])))
		{
			$target=$input["rfTarget"];
			unset($input["rfTarget"]);
		}
		else
		{
			$target=false;
		}


		if(isset($input["url"]))
		{
			unset($input["url"]);
		}
		if(isset($input["reffer"]))
		{
			unset($input["reffer"]);
		}
		if(isset($input["resolution"]))
		{
			unset($input["resolution"]);
		}
		if(isset($input["pagetitle"]))
		{
			unset($input["pagetitle"]);
		}
		if(isset($input["rfUserAgent"]))
		{
			unset($input["rfUserAgent"]);
		}
		if(isset($input["rfFormID"]))
		{
			unset($input["rfFormID"]);
		}
		if(isset($input["rfLatestNumber"]))
		{
			unset($input["rfLatestNumber"]);
		}

		$mainbody="";
		$subject=StringHelper::strtoupper($subject);

		if($this->params->get('insertformid'))
		{
			$telegram="<b>".$formID."</b><br /><br />";
		}
		else
		{
			$telegram="<b>".$subject."</b><br /><br />";
		}

		foreach ($input as $key => $record)
		{
			if(is_array($record))
			{
				if($this->params->get('glue')=="<br />" || $this->params->get('glue')=="<br>" )
				{
					array_unshift($record," ");
					$record=implode($this->params->get('glue'), $record);
				}
				else
				{
					$record=implode($this->params->get('glue'), $record);
				}

			}
			if($key=="phone")
			{
				$mainbody .= "<p>".JText::_($key) . ": <strong><a href='tel://". $record ."'>" . $record . "</a></strong></p>";
				$telegram.= JText::_($key) . ': <b>' . $record .'</b><br />';
			}
			else
			{
				$mainbody .= "<p>".JText::_($key) . ": <strong>" . $record . "</strong></p>";
				$telegram.= JText::_($key) . ": <b>" . $record ."</b><br />";

			}
		}

		$header="";
		if($this->params->get('insertformid'))
		{
			$header="<h2>".$formID."<h2>";
		}

		if($this->params->get('extendedinfo'))
		{
			$footer = "# <strong>".$latestNumber."</strong><br>";
			if($formID) {
				$footer .=  JText::_('PLG_RADICALFORM_FORMID')."<strong>".JText::_($formID)."</strong><br>";
			}

			$footer .=  JText::_('PLG_RADICALFORM_IP_ADDRESS') . "<a href='http://whois.domaintools.com/" . $_SERVER['REMOTE_ADDR'] . "'><strong>" . $_SERVER['REMOTE_ADDR'] . "</strong></a><br>";
			$footer .= JText::_('PLG_RADICALFORM_URL') .$url."<br />";
			if($ref)
			{
				$footer.= JText::_('PLG_RADICALFORM_REFFER') ."<a href='".$ref."'>". substr($ref, 0, 64) ." </a> <br />";
			};

			$footer.= JText::_('PLG_RADICALFORM_PAGETITLE') ."<strong>".htmlentities($pagetitle)."</strong> <br />";
			$footer.= JText::_('PLG_RADICALFORM_USERAGENT') ."<strong>".htmlentities($useragent)."</strong> <br />";
			$footer.= JText::_('PLG_RADICALFORM_RESOLUTION') .$resolution;
		}
		else
		{
			$footer = "";
		}

		$path = JPluginHelper::getLayoutPath('system', 'radicalform');

		// Render the email
		ob_start();
		include $path;
		$body = ob_get_clean();

		//execute custom code if we'll find it
		if($this->params->get('customcodeon'))
		{
			$customcodes = (array) $this->params->get('customcodes');
			foreach ($customcodes as $customcode)
			{

				if ( (( $target !== false ) && ( $customcode->target == $target )) or
					( empty(trim($customcode->target)) && ($target ===  false) )
				)
				{
					$template = Factory::getApplication()->getTemplate();
					$tPath = JPATH_THEMES . '/' . $template . '/html/plg_system_radicalform/' . $customcode->layout;

					if (file_exists($tPath) and is_file($tPath))
					{
						include $tPath;
					}
				}
			}
		}

		if($this->params->get('telegram'))
		{

			$chatIDs = (array) $this->params->get('chatids');
			foreach ($chatIDs as $chatID)
			{

				if(
					(( $target !== false ) && ( $chatID->target == $target )) or
					( empty(trim($chatID->target)) && ($target ===  false) )
				)
				{

					$url = "https://api.telegram.org/bot".$this->params->get('telegramtoken')."/sendMessage?"
						.http_build_query([
							'disable_web_page_preview' => true,
							'chat_id' => $chatID->chat_id,
							'parse_mode' => 'HTML',
							'text' => str_replace("<br />","\r\n",$telegram)
						]);

					$ch = curl_init();
					if($this->params->get('proxy'))
					{
						$proxy = $this->params->get('proxylogin').":".$this->params->get('proxypassword')."@".$this->params->get('proxyaddress').":".$this->params->get('proxyport');
						curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5);
						curl_setopt($ch, CURLOPT_PROXY, $proxy);
					}

					curl_setopt($ch, CURLOPT_URL, "$url");
					curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
					curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
					curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
					curl_setopt($ch, CURLOPT_HEADER, 0);
					curl_exec($ch);
					curl_close($ch);

				}
			}

		}

		if($this->params->get('dialog'))
		{
			$data=str_replace("<br />"," \r\n",$telegram);
			$data=str_replace(["<b>","</b>"],"*",$data);
			$data_string = json_encode(array("text" =>$data));
			$ch = curl_init($this->params->get('dialogurl'));
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
			curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_HTTPHEADER, array(
					'Content-Type: application/json',
					'Content-Length: ' . strlen($data_string))
			);

			curl_exec($ch);
		}

		$textOutput=str_replace("<br />"," \r\n",$telegram);
		$textOutput=str_replace(["<b>","</b>"],"",$textOutput);
		if($this->params->get('emailon'))
		{
			// if we need to send email
			$mailer->isHtml(true);
			$mailer->Encoding = 'base64';
			$mailer->setBody($body);

			$needToSendEmail=false;
			if(isset($target)&& (!empty($target)))
			{
				// if we need to send to alternative emails
				$emailalt = (array)$this->params->get('emailalt');
				foreach($emailalt as $item) {
					if($target==$item->target) {
						$mailer->addRecipient($item->email);
						$needToSendEmail=true;
					}
				}
			}
			else
			{
				//traditional send
				$mailer->addRecipient($this->params->get('email'));
				if (!empty($this->params->get('emailcc')))
				{
					$mailer->addCc($this->params->get('emailcc'));
				}
				if (!empty($this->params->get('emailbcc')))
				{
					$mailer->addBcc($this->params->get('emailbcc'));
				}
				$needToSendEmail=true;
			}

			if((!empty($this->params->get('replyto'))) && isset($input[$this->params->get('replyto')]))
			{
				$mailer->addReplyTo($input[$this->params->get('replyto')]);
			}

			if($needToSendEmail)
			{
				$send = $mailer->Send();
				if($send===false)
				{
					$input=[];
					$input["message"]=JText::_('PLG_RADICALFORM_MAIL_DISABLED');
					JLog::add(json_encode($input), JLog::WARNING, 'plg_system_radicalform');
					return JText::_('PLG_RADICALFORM_MAIL_DISABLED');
				}

				if ($send !== true)
				{
					$input=[];
					$input["message"]=$send->getMessage();
					JLog::add(json_encode($input), JLog::WARNING, 'plg_system_radicalform');
					return $send->getMessage();
				}
				else
				{
					if($needToSendFiles)
					{
						JFolder::delete($uploaddir);
					}
					return ['ok',$textOutput];
				}
			}
			return ['ok', $textOutput];
		}
		else
		{
			if($needToSendFiles)
			{
				JFolder::delete($uploaddir);
			}
			return ['ok',$textOutput];
		}

	}

}
