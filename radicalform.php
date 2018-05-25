<?php
// No direct access
defined('_JEXEC') or die;

/**
 *
 * @package       Joomla.Plugin
 * @subpackage    System.Radicalform
 * @since         3.5+
 * @author        Progreccor
 * @copyright     Copyright 2018 Progreccor
 * @license       GNU General Public License version 2 or later; see LICENSE.txt
 */
use Joomla\String\StringHelper;
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

		$this->logPath = str_replace('\\', '/', JFactory::getConfig()->get('log_path')).'/plg_system_radicalform.php';

		JLog::addLogger(
			array(
				// Sets file name
				'text_file' => 'plg_system_radicalform.php',
				// Sets the format of each line
				'text_entry_format' => "{TIME}\t{DATE}\t{CLIENTIP}\t{MESSAGE}\t{PRIORITY}"
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

	public function onAfterRender()
	{

		if ($this->app->isClient('administrator'))
		{
			return false;
		}

		$body = $this->app->getBody();
		$lnEnd = JFactory::getDocument()->_getLineEnd();
		if (strpos($body, 'rf-button-send') !== false)
		{
			$mtime=filemtime(JPATH_ROOT."/media/plg_system_radicalform/js/script.js");
			$js = "<script src=\"" . JURI::base(true) . "/media/plg_system_radicalform/js/script.js?$mtime\"></script>" . $lnEnd
				. "<script>"
				. "var RadicalForm={"
				. "DangerClass:'" . $this->params->get('dangerclass') . "', "
				. "ErrorFile:'" . $this->params->get('errorfile') . "', "
				. "thisFilesWillBeSend:'" . JText::_('PLG_RADICALFORM_THIS_FILES_WILL_BE_SEND') . "', "
				. "waitingForUpload:'" . $this->params->get('waitingupload') . "', "
				. "WaitMessage:'" . $this->params->get('rfWaitMessage') . "', "
				. "ErrorMax:'" . JText::_('PLG_RADICALFORM_FILE_TO_LARGE_THAN_PHP_INI_ALLOWS') . "', "
				. "MaxSize:'" . min($this->return_bytes(ini_get('post_max_size')), $this->return_bytes(ini_get("upload_max_filesize"))) . "', "
				. "IP:{ip: '" . $_SERVER['REMOTE_ADDR'] . "'}, "
				. "Base: '" . JUri::base(true) . "', "
				. "AfterSend:'" . $this->params->get('aftersend') . "'"
				. "};" ;

			if(!empty($this->params->get('rfCall_0')))
			{
				$js .= "function rfCall_0(here) { try { " . $this->params->get('rfCall_0') . " } catch (e) { console.error('Radical Form JS Code: ', e); } }; " ;
			}
			if(!empty($this->params->get('rfCall_1')))
			{
				$js .= "function rfCall_1(rfMessage, here) { try { " . $this->params->get('rfCall_1') . " } catch (e) { console.error('Radical Form JS Code: ', e); } }; " ;
			}
			if(!empty($this->params->get('rfCall_2')))
			{
				$js .= "function rfCall_2(rfMessage, here) { try { " . $this->params->get('rfCall_2') . " } catch (e) { console.error('Radical Form JS Code: ', e); } }; " ;
			}
			if(!empty($this->params->get('rfCall_3')))
			{
				$js .= "function rfCall_3(rfMessage, here) { try { " . $this->params->get('rfCall_3') . " } catch (e) { console.error('Radical Form JS Code: ', e); } }; " ;
			}
			$js .= "var rfToken='" . JHtml::_('form.token') . "'; </script>" . $lnEnd;

			$body = str_replace("</body>", $js . "</body>", $body);
			$this->app->setBody($body);

		}
	}

	public function onAjaxRadicalform()
	{
		$r     = $this->app->input;
		$input = $r->post->getArray();
		$get   = $r->get->getArray();
		$files = $r->files->getArray();


		if (isset($get['admin']) && $get['admin'] == 1 )
		{
			if ($this->app->isClient('administrator'))
			{
				// тут проверка телеграма на предмет обновлений диалогов (ловим chat_id)

				$qv="https://api.telegram.org/bot".$this->params->get('telegramtoken')."/getUpdates";
				$ch = curl_init();


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
					if(!in_array($chat["message"]["chat"]["id"],$chatIDs["chat_id"]))
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
				}


				return $chatIDs;
			} else
			{
				return false;
			}
		}

		if (isset($get['admin']) && $get['admin'] == 2 )
		{
			unlink($this->logPath);
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

				$lang = JFactory::getLanguage();

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

		if (!JFactory::getSession()->checkToken())
		{
			return JText::_('PLG_RADICALFORM_INVALID_TOKEN');
		};

		$mailer = JFactory::getMailer();
		$config = JFactory::getConfig();
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
				}

			}
		}

		if (isset($input["rfTarget"]) && (!empty($input["rfTarget"])))
		{
			$target=$input["rfTarget"];
			unset($input["rfTarget"]);
		}

		unset($input["uniq"]);
		unset($input["needToSendFiles"]);
		unset($input[JSession::getFormToken()]);
		$url=$input["url"];
		$resolution=$input["resolution"];
		$ref=$input["reffer"];

		if(file_exists($this->logPath))
		{
			if($this->params->get('maxlogfile')<filesize($this->logPath))
			{
				unlink($this->logPath);
			}
		}

		$input = array_diff($input, array('')); // delete empty fields in input array

		JLog::add(json_encode($input), JLog::NOTICE, 'plg_system_radicalform');

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


		$mainbody="";
		$subject=StringHelper::strtoupper($subject);
		$telegram="<b>".$subject."</b><br /><br />";
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



		$footer =  JText::_('PLG_RADICALFORM_IP_ADDRESS') . "<a href='http://whois.domaintools.com/" . $_SERVER['REMOTE_ADDR'] . "'><strong>" . $_SERVER['REMOTE_ADDR'] . "</strong></a><br>";
		$footer.= JText::_('PLG_RADICALFORM_URL') .$url."<br>";
		if($ref)
		{
			$footer.= JText::_('PLG_RADICALFORM_REFFER') .$ref." <br>";
		};

		$footer.= JText::_('PLG_RADICALFORM_RESOLUTION') .$resolution;

		$body = <<<EOT
<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
    <meta charset="utf-8"> <!-- utf-8 works for most cases -->
    <meta name="viewport" content="width=device-width"> <!-- Forcing initial-scale shouldn't be necessary -->
    <meta http-equiv="X-UA-Compatible" content="IE=edge"> <!-- Use the latest (edge) version of IE rendering engine -->
    <meta name="x-apple-disable-message-reformatting">  <!-- Disable auto-scale in iOS 10 Mail entirely -->
    <title></title> <!-- The title tag shows in email notifications, like Android 4.4. -->

    <!-- Web Font / @font-face : BEGIN -->

    <!--[if mso]>
        <style>
            * {
                font-family: sans-serif !important;
            }
        </style>
    <![endif]-->

    <style>

        html,
        body {
            margin: 0 auto !important;
            padding: 0 !important;
            height: 100% !important;
            width: 100% !important;
        }

        * {
            -ms-text-size-adjust: 100%;
            -webkit-text-size-adjust: 100%;
        }

        div[style*="margin: 16px 0"] {
            margin: 0 !important;
        }

        table,
        td {
            mso-table-lspace: 0pt !important;
            mso-table-rspace: 0pt !important;
        }

        table {
            border-spacing: 0 !important;
            border-collapse: collapse !important;
            table-layout: fixed !important;
            margin: 0 auto !important;
        }
        table table table {
            table-layout: auto;
        }

        img {
            -ms-interpolation-mode:bicubic;
        }

        *[x-apple-data-detectors],  /* iOS */
        .x-gmail-data-detectors,    /* Gmail */
        .x-gmail-data-detectors *,
        .aBn {
            border-bottom: 0 !important;
            cursor: default !important;
            color: inherit !important;
            text-decoration: none !important;
            font-size: inherit !important;
            font-family: inherit !important;
            font-weight: inherit !important;
            line-height: inherit !important;
        }

        .a6S {
            display: none !important;
            opacity: 0.01 !important;
        }
        img.g-img + div {
            display: none !important;
        }

        .button-link {
            text-decoration: none !important;
        }

        @media only screen and (min-device-width: 375px) and (max-device-width: 413px) { /* iPhone 6 and 6+ */
            .email-container {
                min-width: 375px !important;
            }
        }

	    @media screen and (max-width: 480px) {
	        div > u ~ div .gmail {
		        min-width: 100vw;
	        }
		}

    </style>
    <style>

    .button-td,
    .button-a {
        transition: all 100ms ease-in;
    }
    .button-td:hover,
    .button-a:hover {
        background: #555555 !important;
        border-color: #555555 !important;
    }

    /* Media Queries */
    @media screen and (max-width: 600px) {

        .email-container p {
            font-size: 17px !important;
        }

    }

    </style>
    <!--[if gte mso 9]>
    <xml>
        <o:OfficeDocumentSettings>
            <o:AllowPNG/>
            <o:PixelsPerInch>96</o:PixelsPerInch>
        </o:OfficeDocumentSettings>
    </xml>
    <![endif]-->

</head>
<body width="100%" bgcolor="#fdfeff" style="margin: 0; mso-line-height-rule: exactly;">
    <center style="width: 100%; background: #fdfeff; text-align: left;">

        <div style="display: none; font-size: 1px; line-height: 1px; max-height: 0px; max-width: 0px; opacity: 0; overflow: hidden; mso-hide: all; font-family: sans-serif;">
           $mainbody
        </div>
        <div style="max-width: 600px; margin: auto;" class="email-container">
            <!--[if mso]>
            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="600" align="center">
            <tr>
            <td>
            <![endif]-->

            <!-- Email Header : BEGIN -->
            <table role="presentation" cellspacing="0" cellpadding="0" border="0" align="center" width="100%" style="max-width: 600px;">
                <tr>
                    <td style="padding: 20px 0; text-align: center">
						<p>&nbsp;</p>
                    </td>
                </tr>
            </table>
            <!-- Email Header : END -->

            <!-- Email Body : BEGIN -->
            <table role="presentation" cellspacing="0" cellpadding="0" border="0" align="center" width="100%" style="max-width: 600px;">


                <!-- 1 Column Text + Button : BEGIN -->
                <tr>
                    <td bgcolor="#f0f0f0">
                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                            <tr>
                                <td style="padding: 40px; font-family: sans-serif; font-size: 15px; line-height: 140%; color: #555555;">
								$mainbody
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <!-- 1 Column Text + Button : END -->


                <!-- Clear Spacer : BEGIN -->
                <tr>
                    <td aria-hidden="true" height="40" style="font-size: 0; line-height: 0;">
                        &nbsp;
                    </td>
                </tr>
                <!-- Clear Spacer : END -->

                <!-- 1 Column Text : BEGIN -->
                <tr>
                    <td bgcolor="#f0f0f0">
                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                            <tr>
                                <td style="padding: 40px; font-family: sans-serif; font-size: 15px; line-height: 140%; color: #555555;">
                                    <p style="margin: 0;">
										$footer
									</p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <!-- 1 Column Text : END -->

            </table>
            <!-- Email Body : END -->


            <!--[if mso]>
            </td>
            </tr>
            </table>
            <![endif]-->
        </div>
<p>&nbsp;</p>
    </center>
</body>
</html>
EOT;

		if($this->params->get('telegram'))
		{

			$chatIDs = (array) $this->params->get('chatids');
			foreach ($chatIDs as $chatID)
			{

				if(isset($target)&& (!empty($target)))
				{
					if($chatID->target == $target)
					{
						$url = "https://api.telegram.org/bot".$this->params->get('telegramtoken')."/sendMessage?"
							.http_build_query([
								'disable_web_page_preview' => true,
								'chat_id' => $chatID->chat_id,
								'parse_mode' => 'HTML',
								'text' => str_replace("<br />","\r\n",$telegram)
							]);

						$ch = curl_init();
						curl_setopt($ch, CURLOPT_URL, "$url");
						curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
						curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
						curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
						curl_setopt($ch, CURLOPT_HEADER, 0);
						curl_exec($ch);
						curl_close($ch);

					}
				}
				else
				{
					$url = "https://api.telegram.org/bot".$this->params->get('telegramtoken')."/sendMessage?"
						.http_build_query([
							'disable_web_page_preview' => true,
							'chat_id' => $chatID->chat_id,
							'parse_mode' => 'HTML',
							'text' => str_replace("<br />","\r\n",$telegram)
						]);

					$ch = curl_init();
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
					JFolder::delete($uploaddir);
					return 'ok';
				}
			}

		}
		else
		{
			return 'ok';
		}

	}

}
