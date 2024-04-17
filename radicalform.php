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
use Joomla\CMS\Log\Log;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Table\Table;
use Joomla\CMS\Uri\Uri;
use Joomla\String\StringHelper;
use Joomla\CMS\HTML\HTMLHelper;

class plgSystemRadicalform extends CMSPlugin
{
	private $logPath;

	protected $autoloadLanguage = true;
	protected $db;
	protected $app;
	protected $maxDirSize; // максимальный размер передаваемых файлов по почте
	protected $maxStorageTime;
	protected $maxStorageSize; // размер хранилища файлов

	public function __construct(& $subject, $config)
	{
		parent::__construct($subject, $config);

		JLoader::register('JFile', JPATH_LIBRARIES . '/joomla/filesystem/file.php');
		JLoader::register('JFolder', JPATH_LIBRARIES . '/joomla/filesystem/folder.php');
		$this->maxDirSize = $this->params->get('maxfile',20)*1048576;
		$this->maxStorageSize = $this->params->get('maxstorage',1000)*1048576;

		$this->logPath = str_replace('\\', '/', Factory::getConfig()->get('log_path')).'/plg_system_radicalform.php';

		Log::addLogger(
			array(
				// Sets file name
				'text_file' => 'plg_system_radicalform.php',
				// Sets the format of each line
				'text_entry_format' => "{DATETIME}\t{CLIENTIP}\t{MESSAGE}\t{PRIORITY}"
			),
			// Sets all but DEBUG log level messages to be sent to the file
			Log::ALL & ~Log::DEBUG,
			array('plg_system_radicalform')
		);

		$this->maxStorageTime = $this->params->get('maxtime',30);

		// if we have empty storage directory or wrong directory - try to fix it
		if(empty($this->params->get('uploadstorage')) || (!file_exists($this->params->get('uploadstorage'))) )
		{
			BaseDatabaseModel::addIncludePath(JPATH_ADMINISTRATOR . '/components/com_plugins/models/');
			Table::addIncludePath(JPATH_ADMINISTRATOR . '/components/com_plugins/tables/');

			$plugin   = PluginHelper::getPlugin('system', 'radicalform');

			// set the directory to safe place

			/* @var PluginsModelPlugin $model */
			$model = BaseDatabaseModel::getInstance('Plugin', 'PluginsModel', array('ignore_request' => true));
			$data  = $model->getItem($plugin->id);

			$data = (array) $data;
			if(empty($this->params->get('uploadstorage')))
			{
				// empty directory
				$data['params']['uploadstorage'] = JPATH_ROOT. '/images/radicalform'.$this->uniqidReal();
				mkdir($data['params']['uploadstorage']);

			}
			else
			{
				// if directory not exist
				//try to find it
				if (file_exists(JPATH_ROOT. '/images/'.basename($this->params->get('uploadstorage'))))
				{
					$data['params']['uploadstorage'] = JPATH_ROOT. '/images/'.basename($this->params->get('uploadstorage'));
				}
				else
				{
					// empty directory
					$data['params']['uploadstorage'] = JPATH_ROOT. '/images/radicalform'.$this->uniqidReal();
					mkdir($data['params']['uploadstorage']);
				}
			}

			$model->save($data);
		}


	}

	function uniqidReal($lenght = 23) {
		if (function_exists("random_bytes")) {
			$bytes = random_bytes(ceil($lenght / 2));
		} elseif (function_exists("openssl_random_pseudo_bytes")) {
			$bytes = openssl_random_pseudo_bytes(ceil($lenght / 2));
		} else {
			throw new Exception("no cryptographically secure random function available");
		}
		return substr(bin2hex($bytes), 0, $lenght);
	}


    /**
     * Make filename safe
     * @param $file - filename
     * @return string
     * @since
     */
    public function makeSafe($file)
	{
		// Remove any trailing dots, as those aren't ever valid file names.
		$file = rtrim($file, '.');

		$regex = array('#(\.){2,}#', '#[^A-Za-z0-9\.\_\-]#', '#^\.#');

		$repl = array('.','','');

		return trim(preg_replace($regex, $repl, $file));
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

	/**
	 * clear input array from extra info about user (like resolution and other info)
	 *
	 * @param array $input
	 *
	 * cleared input array - only values from  the form
	 * @return array
	 *
	 * @since version
	 */
	private function clearInput(array $input)
	{

		if (isset($input["rfTarget"]) )
		{
			unset($input["rfTarget"]);
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
		if(isset($input["uniq"]))
		{
			unset($input["uniq"]);
		}
		if(isset($input["needToSendFiles"]))
		{
			unset($input["needToSendFiles"]);
		}
        if(isset($input["rf-time"]))
        {
            unset($input["rf-time"]);
        }
        if(isset($input["rf-duration"]))
        {
            unset($input["rf-duration"]);
        }
		if(isset($input[JSession::getFormToken()]))
		{
			unset($input[JSession::getFormToken()]);
		}

		return $input;
	}


	public function onAfterRender()
	{

		if ($this->app->isClient('administrator'))
		{
			return false;
		}

		// для всяких модальных окон, очищенных от постороннего мусора. себя мы тоже не выводим
		$data = $this->app->input->getArray();
		if(isset($data['tmpl']) && $data['tmpl'] === 'component')
		{
			return false;
		}

		$body  = $this->app->getBody();
		$lnEnd = Factory::getDocument()->_getLineEnd();
		if (strpos($body, 'rf-button-send') !== false)
		{
            $mtime = filemtime(JPATH_SITE . HTMLHelper ::_('script', 'plg_system_radicalform/script.min.js', ['relative' => true, 'pathOnly' => true ]));
            $session = \JFactory::getSession();
            $lifeTime    = $session->getExpire();
            $refreshTime = $lifeTime <= 60 ? 45 : $lifeTime - 60;

            // The longest refresh period is one hour to prevent integer overflow.
            if ($refreshTime > 3600 || $refreshTime <= 0)
            {
                $refreshTime = 3600;
            }
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
				'KeepAlive'           => $this->params->get('keepalive'),
				'TokenExpire'          => $refreshTime*1000,
 				'DeleteColor'         => $this->params->get('buttondeletecolor', "#fafafa"),
				'DeleteBackground'    => $this->params->get('buttondeletecolorbackground', "#f44336")
			);
			if ($this->params->get('insertip'))
			{
				$jsParams['IP'] = json_encode(array('ip' => $_SERVER['REMOTE_ADDR']));
			}
            $js = "<script src=\"" .  HTMLHelper ::_('script', 'plg_system_radicalform/script.min.js', ['relative' => true, 'pathOnly' => true ])."?$mtime\" async></script>" . $lnEnd
				. "<script>"
				. "var RadicalForm=" . json_encode($jsParams) . ";";

			if (!empty($this->params->get('rfCall_0')))
			{
				$js .= "function rfCall_0(here, needReturn) { try { " . $this->params->get('rfCall_0') . " } catch (e) { console.error('Radical Form JS Code: ', e); } }; ";
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
			if (!empty($this->params->get('rfCall_9on')))
			{
				// here we have individual code for rfCall_9
				$js .= "function rfCall_9(rfMessage, here) { try { " . $this->params->get('rfCall_9') . " } catch (e) { console.error('Radical Form JS Code: ', e); } }; ";
			}
			else
			{
				// here we set standard output
				if (!empty($this->params->get('rfCall_2')))
				{
					$js .= "function rfCall_9(rfMessage, here) { try { " . $this->params->get('rfCall_2') . " } catch (e) { console.error('Radical Form JS Code: ', e); } }; ";
				}
				elseif (!empty($this->params->get('rfCall_1')))
				{
					$js .= "function rfCall_9(rfMessage, here) { try { " . $this->params->get('rfCall_1') . " } catch (e) { console.error('Radical Form JS Code: ', e); } }; ";
				}
				elseif (!empty($this->params->get('rfCall_3')))
				{
					$js .= "function rfCall_9(rfMessage, here) { try { " . $this->params->get('rfCall_3') . " } catch (e) { console.error('Radical Form JS Code: ', e); } }; ";
				}
				else
				{
					$js .= "function rfCall_9(rfMessage, here) { try { alert(rfMessage); } catch (e) { console.error('Radical Form JS Code: ', e); } }; ";
				}
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

	/**
	 * @param $path
	 *
	 * Directory size
	 *
	 * @return int total directory size
	 *
	 * @since version
	 */
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


	/**
	 *  here we process uploaded files
	 *
	 * @param $files - uploaded file
	 *
	 * @param $uniq - uniq mark for this form
	 *
	 * @return array
	 *
	 * @since version 2.6
	 */
	private function processUploadedFiles($files, $uniq)
	{


		$uploaddir = $this->params->get('uploadstorage') . '/rf-' . $uniq;

		if(!file_exists($this->params->get('uploadstorage')))
		{
			mkdir($this->params->get('uploadstorage'));
		}
		// вначале проверим есть ли папки,подлежащие удалению по старости
		$folders = JFolder::folders($this->params->get('uploadstorage') , "rf-*", false, true);

		$maxtime = $this->params->get('maxtime',30) * 86400;

		foreach ($folders as $folder)
		{
			// we use name of the directory as a time of creation of the directory
			$t2=explode("-",basename($folder));
			$dtime = intval(time() - intval($t2[1]/1000));
			if ($dtime > ( $maxtime)) // все что старше указанного срока - под нож!
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
			$totalsize = $this->getDirectorySize($this->params->get('uploadstorage'));

			$lang = Factory::getLanguage();

			foreach ($files as $key => $file)
			{
				if ($file['name'])
				{
					$mime= $this->mimetype($file['tmp_name']);
					$mimetype=explode('/',  $mime);

					if($mimetype[0]=="text")
					{
						$output["error"] = JText::_('PLG_RADICALFORM_ERROR_WRONG_TYPE');
						continue;
					}
					if(strpos($mime,"svg")!==false)
					{
						$output["error"] = JText::_('PLG_RADICALFORM_ERROR_WRONG_TYPE');
						continue;
					}
				}
				else
				{
					$output["error"] = JText::_('PLG_RADICALFORM_ERROR_WRONG_TYPE');
					continue;
				}


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

					if (($file['size'] + $totalsize) < $this->maxStorageSize)
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
							$uploadedFileName = $this->makeSafe($lang->transliterate($file['name']));
							if (JFile::upload($file['tmp_name'], $uploaddir . "/" . $key . "/" . $uploadedFileName))
							{
								$output["name"] = $uploadedFileName;
								$output["key"] = $key;
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

	/** Обрабатываем пути для выкачки файлов
	 *
	 * @since
	 */
	public function onAfterInitialise()
	{
		$uri    = Uri::getInstance();
		$path   = $uri->getPath();
		$root   = Uri::root(true);
		$entry  = $root . "/".$this->params->get('downloadpath') ;

		if (preg_match('#' . $entry . '#', $path))
		{
			$folder = basename(dirname($path));
			$uniq = basename(dirname(dirname($path)));
			$filename = basename($path);

			$this->showImage($uniq, $folder, $filename);
		}

	}


	/**
	 *  Check file mime type and return it
	 * @param $filepath
	 *
	 * @return mixed|string
	 *
	 * @since version
	 */
	private function mimetype($filepath)
	{
		if (function_exists('finfo_open'))
		{
			$finfo    = finfo_open(FILEINFO_MIME_TYPE);
			$mimetype = finfo_file($finfo, $filepath);
			finfo_close($finfo);
		}
		else
		{
			$mimetype = mime_content_type($filepath);
		}
		return $mimetype;
	}

	/**
	 * Show image
	 *
	 * @param $uniq - uniq number of the uploaded form
	 * @param $folder - name of the file field
	 * @param $name - name of the uploaded file
	 *
	 *
	 * @since version
	 */
	private function showImage($uniq,$folder,$name)
	{
		$filepath=$this->params->get('uploadstorage').DIRECTORY_SEPARATOR."rf-".$uniq.DIRECTORY_SEPARATOR.$folder.DIRECTORY_SEPARATOR.$name;
		if(file_exists($filepath))
		{
			$mimetype=explode('/',  $this->mimetype($filepath));

			if(($mimetype[0]=="text") || (strpos($mimetype[1],"svg")))
			{
				$this->renderFileNotFound();
			}
			else
			{
				header("Content-Type: ".$this->mimetype($filepath));

				header('Expires: 0');
				header('Cache-Control: no-cache');
				header("Content-Length: " .(string)(filesize($filepath)) );
				echo  file_get_contents($filepath);

			}
		}
		else
		{
			$this->renderFileNotFound();
		}

		$this->app->close(200);
	}

    /**
     * Delete uploaded by user file
     * @param $name - name of the file to delete
     * @param $uniq - uniq id for current form
     *
     * @return string - status of deleting file
     * @since
     */
    public function deleteUploadedFile($catalog, $name, $uniq)
    {
        $name = $this->makeSafe(basename($name));
        $catalog = $this->makeSafe(basename($catalog));
        $uniq =  (int) $uniq;

        $filename = $this->params->get('uploadstorage') . '/rf-' . $uniq."/".$catalog."/".$name;
        if(file_exists($filename))
        {
            unlink($filename);
        }
        return "ok";
    }

	/**
	 * Render image for 404 and error file
	 *
	 *  @since
	 */
	public function renderFileNotFound()
	{
		$filenotfound=JPATH_ROOT.HTMLHelper ::_('image', 'plg_system_radicalform/filenotfound.svg', '', null, true, 1);
		header('HTTP/1.1 404 Not Found');
		header("Content-Type: ".$this->mimetype($filenotfound));
		header('Expires: 0');
		header('Cache-Control: no-cache');
		header("Content-Length: " .(string)(filesize($filenotfound)) );
		echo  file_get_contents($filenotfound);
	}

	public function onAjaxRadicalform()
	{
		$uri    = Uri::getInstance();

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


		if (isset($get['deletefile']) && isset($get['catalog']) && isset($get['uniq']))
        {
            return $this->deleteUploadedFile($get['catalog'], $get['deletefile'], $get['uniq']);
        }

        if (isset($input['gettoken']) )
        {
            return JSession::getFormToken();
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

				header("Content-disposition: attachment; filename={$filename}");
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
                            if(isset($json["rf-time"]))
                            {
                                $extrainfo.=JText::_('PLG_RADICALFORM_USER_TIME').$json["rf-time"]."\n";
                            }
                            if(isset($json["rf-duration"]))
                            {
                                $extrainfo.=JText::sprintf('PLG_RADICALFORM_FORM_DURATION', $json["rf-duration"])."\n";
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
                        if(isset($json["rf-time"]))
                        {
                            unset($json["rf-time"]);
                        }
                        if(isset($json["rf-duration"]))
                        {
                            unset($json["rf-duration"]);
                        }

						$csv.="{$latestNumber};\"".$jdate->format('H:i:s',true)."\n".$jdate->format('d.m.Y',true)."\";{$target}{$formid}{$item[1]};";
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
							$csv.="\";{$extrainfo};\r\n";
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
				$result=curl_exec($ch);
				$output=json_decode($result,true);
				if (curl_getinfo($ch, CURLINFO_RESPONSE_CODE)!='200')
				{

					return $output;
				}
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


				return ["ok"=>true,"chatids"=>$chatIDs];
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
			if ($this->app->isClient('administrator'))
			{
				// очищаем текущий файл или удаляем, если он архивный (1-plg_system_radicalform.php и т.д.)
				if($page)
				{
					unlink($log_path . '/'.$page.'plg_system_radicalform.php');
				}
				else
				{
					unlink($this->logPath);
					$entry= ['rfLatestNumber' => $latestNumber, 'message' => JText::_('PLG_RADICALFORM_CLEAR_HISTORY') ];
					Log::add(json_encode($entry), Log::NOTICE, 'plg_system_radicalform');
				}
				return "ok";
			} else
			{
				return false;
			}

		}

		if (isset($get['admin']) && $get['admin'] == 3 )
		{
			if ($this->app->isClient('administrator'))
			{
				// сбрасываем нумерацию
				$entry = ['rfLatestNumber' => 0, 'message' => JText::_('PLG_RADICALFORM_RESET_NUMBER')];
				Log::add(json_encode($entry), Log::NOTICE, 'plg_system_radicalform');

				return "ok";
			} else
			{
				return false;
			}
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


		if (isset($get['file']) && $get['file'] == 1)
		{
			// здесь нам передали файл. что же, будем обрабатывать

			return $this->processUploadedFiles($files, $uniq);

		}


		if (Factory::getSession()->isNew() || !Factory::getSession()->checkToken())
		{
            $input = ['rfLatestNumber' => $latestNumber, 'message' => JText::_('PLG_RADICALFORM_INVALID_TOKEN') ];
            Log::add(json_encode($input), Log::WARNING, 'plg_system_radicalform');
			return JText::_('PLG_RADICALFORM_INVALID_TOKEN');
		};

		$mailer = Factory::getMailer();

		$sender = array(
			$config->get('mailfrom'),
			$config->get('fromname')
		);

		$mailer->setSender($sender);

		// вызов внешнего плагина
		PluginHelper::importPlugin('radicalform');
		$params = $this->params;
		$params->set('uploaddir', $this->params->get('uploadstorage') . '/rf-' . $uniq);
		$params->set('rfLatestNumber',$latestNumber);

		try
		{
			$this->app->triggerEvent('onBeforeSendRadicalForm', array($this->clearInput($input), &$input,$params));
		}
		catch(Throwable $e)
		{
			// TODO лог
		}


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
					if(is_array($set))
                    {
                        $set = implode(", ", $set);
                    }
					$subject = preg_replace("|$match[0]|", $set, $subject, 1);
				}
			}
		}

		$mailer->setSubject($subject);

		// формируем поле загруженных файлов
		$url = ((!empty($_SERVER['HTTPS'])) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
		$downloadPath=$this->params->get('downloadpath');
		$totalsize=0;
		if (isset($input["needToSendFiles"]) && ($input["needToSendFiles"] == 1))
		{
			// просматриваем все подпапки нашей папки для выгрузки
			$folders = JFolder::folders($this->params->get('uploadstorage') . '/rf-' . $uniq);
			foreach ($folders as $folder)
			{
				//прикрепляем файлы
				$filesForAttachment = JFolder::files($this->params->get('uploadstorage') . '/rf-' . $uniq . "/" . $folder, ".", false, true);

				foreach ($filesForAttachment as $file)
				{
					if (isset($input[$folder]))
					{
						$input[$folder] .= $this->params->get('delimiter',"<br />")."{$url}/{$downloadPath}/{$uniq}/{$folder}/".basename($file);
					}
					else
					{
						$input[$folder] = "{$url}/{$downloadPath}/{$uniq}/{$folder}/".basename($file);
					}
					if($this->params->get('attachfiles',0))
					{
						if(($totalsize+filesize($file)) < $this->maxDirSize)
						{
							$totalsize=$totalsize+filesize($file);
							$mailer->addAttachment($file);
						}
					}
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

        $rfTime=isset($input["rf-time"]) ? $input["rf-time"] : '';
        $rfDuration=isset($input["rf-duration"]) ? $input["rf-duration"]  : '';
		$formID= isset($input["rfFormID"]) ? JText::_($input["rfFormID"]) : '';

		if(file_exists($this->logPath))
		{
			if($this->params->get('maxlogfile')<filesize($this->logPath))
			{
				unlink($this->logPath);
				$entry= ['rfLatestNumber' => $latestNumber, 'message' => JText::_('PLG_RADICALFORM_CLEAR_HISTORY_BY_MAX_LOG') ];
				Log::add(json_encode($entry), Log::NOTICE, 'plg_system_radicalform');
				$latestNumber++;
			}
		}

		$input['rfLatestNumber'] = $latestNumber;
		$input = array_filter($input, function($value) { return $value !== ''; }); // delete empty fields in input array

		Log::add(json_encode($input), Log::NOTICE, 'plg_system_radicalform');

		if (isset($input["rfTarget"]) && (!empty($input["rfTarget"])))
		{
			$target=$input["rfTarget"];
		}
		else
		{
			$target=false;
		}

		$input = $this->clearInput($input);


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
                }
                $record=implode($this->params->get('glue'), $record);

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
            $footer.= JText::_('PLG_RADICALFORM_RESOLUTION') ."<strong>".$resolution."</strong> <br />";
            $footer.= JText::_('PLG_RADICALFORM_USER_TIME') ."<strong>".$rfTime."</strong> <br />";
            $footer.= JText::sprintf('PLG_RADICALFORM_FORM_DURATION', $rfDuration);
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
						try
						{
							include $tPath;
						}
						catch(Throwable $e)
						{
							// TODO лог
						}
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
					Log::add(json_encode($input), Log::WARNING, 'plg_system_radicalform');
					return JText::_('PLG_RADICALFORM_MAIL_DISABLED');
				}

				if ($send !== true)
				{
					$input=[];
					$input["message"]=$send->getMessage();
					Log::add(json_encode($input), Log::WARNING, 'plg_system_radicalform');
					return $send->getMessage();
				}
				else
				{

					return ['ok',$textOutput];
				}
			}
			return ['ok', $textOutput];
		}
		else
		{
			return ['ok',$textOutput];
		}

	}

}
