<?php

if (!defined('_PS_VERSION_'))
	exit;

require_once dirname(__FILE__).'/vendor/cssin/cssin.php';
require_once dirname(__FILE__).'/vendor/cssin/vendor/simple_html_dom/simple_html_dom.php';
require_once dirname(__FILE__).'/vendor/html_to_text/Html2Text.php';

class EmailGenerator extends Module
{
	public function __construct()
	{
		$this->name = 'emailgenerator';
		$this->version = '0.5';
		$this->author = 'fmdj';
		$this->bootstrap = true;

		$this->displayName = 'Email Generator';
		$this->description = 'Generate HTML and TXT emails for PrestaShop from php templates.';

		parent::__construct();
	}

	public function install()
	{
		return parent::install() && $this->installTab();
	}

	public function uninstall()
	{
		return $this->uninstallTab() && parent::uninstall();
	}

	public function installTab()
	{
		$tab = new Tab();
		$tab->active = 1;
		$tab->class_name = "AdminEmailGenerator";
		$tab->name = array();
		foreach (Language::getLanguages(true) as $lang)
			$tab->name[$lang['id_lang']] = "AdminEmailGenerator";
		$tab->id_parent = -1;
		$tab->module = $this->name;
		return $tab->add();
	}

	public function uninstallTab()
	{
		$id_tab = (int)Tab::getIdFromClassName('AdminEmailGenerator');
		if ($id_tab)
		{
			$tab = new Tab($id_tab);
			return $tab->delete();
		}
		else
			return false;
	}

	public function getContent()
	{
		Tools::redirectAdmin($this->context->link->getAdminLink('AdminEmailGenerator'));
	}

	public static function humanizeString($str)
	{
		return implode(' ', array_map('ucfirst',  preg_split('/[_\-]/', $str)));
	}

	public static function relativePath($path)
	{
		return substr($path, strlen(dirname(__FILE__))+1);
	}

	public static function listEmailTemplates()
	{
		static $templates = null;

		if ($templates !== null)
			return $templates;

		$templates = array('core' => array(), 'modules' => array());

		if(is_dir(dirname(__FILE__).'/templates/core'))
			foreach (scandir(dirname(__FILE__).'/templates/core') as $entry)
			{
				$path = dirname(__FILE__).'/templates/core/'.$entry;

				if (preg_match('/\.php$/', $entry))
				{
					$templates['core'][] = array(
						'path' => self::relativePath($path),
						'name' => self::humanizeString(basename($entry,'.php'))
					);
				}
			}
		
		if(is_dir(dirname(__FILE__).'/templates/modules'))
			foreach (scandir(dirname(__FILE__).'/templates/modules') as $module)
			{
				$dir = dirname(__FILE__).'/templates/modules/'.$module;

				if (!preg_match('/^\./', $module) && is_dir($dir))
				{
					$templates['modules'][$module] = array();

					foreach (scandir($dir) as $entry)
					{
						$path = $dir.'/'.$entry;
						if (preg_match('/\.php$/', $entry))
						{
							$templates['modules'][$module][] = array(
								'path' => self::relativePath($path),
								'name' => self::humanizeString(basename($entry,'.php'))
							);
						}
					}
				}
			}

		return $templates;
	}

	public function quote($str)
	{
		return '\''.str_replace("\n", '', preg_replace('/\\\*\'/', '\\\'', $str)).'\'';
	}

	public function dictionaryToArray($name, $data, $global=true)
	{
		$str = "<?php\n\n";
		if ($global)
			$str .= 'global $'.$name.";\n";
		$str .= '$'.$name." = array();\n\n";

		foreach ($data as $key => $value)
			if (trim($value) != '')
				$str .= '$'.$name.'['.$this->quote($key).'] = '.$this->quote($value).";\n";

		$str .= "\n\nreturn ".'$'.$name.";\n";

		return $str;
	}

	public function writeTranslations($data)
	{
		foreach ($data as $relFilePath => $newTranslations)
		{
			if (($absPath = $this->isValidTranslationFilePath($relFilePath)) !== false)
			{
				$currentTranslations = $this->getTranslations($absPath);
				$translations = array_merge($currentTranslations, $newTranslations);
				$phpArray = $this->dictionaryToArray('_LANGMAIL', $translations);
				$dir = dirname($absPath);
				if (!is_dir($dir))
					if (!@mkdir($dir, 0777, true))
						return false;
				$fh = fopen($absPath, 'w');
				if (!$fh)
					return false;
				if (flock($fh, LOCK_EX))
				{
					fwrite($fh, $phpArray);
					fflush($fh);
					flock($fh, LOCK_UN);
					fclose($fh);
				}
				else
				{
					return false;
				}
			}
			else
				return false;
		}
		return true;
	}

	public function postGenerateAction()
	{
		$templates = self::listEmailTemplates();

		foreach (Language::getLanguages() as $l)
		{
			$language = $l['iso_code'];

			foreach ($templates['core'] as $file)
			{
				$target_path = _PS_ROOT_DIR_.'/mails/'.$language.'/'.basename($file['path'], '.php');
				$this->generateEmail($file['path'], $target_path, $language);
			}
			foreach ($templates['modules'] as $module => $files)
			{
				foreach ($files as $file)
				{
					$target_path = _PS_MODULE_DIR_.$module.'/mails/'.$language.'/'.basename($file['path'], '.php');
					$this->generateEmail($file['path'], $target_path, $language);
				}
			}
		}

		$this->template = 'index';
		return $this->getIndexAction();
	}

	public function textify($html)
    {
        $html      = str_get_html($html);
        foreach($html->find("//[data-html-only='1']") as $kill)
        {
                $kill->outertext = "";
        }
        $converter = new Html2Text((string)$html);

        $converter->search[]  = "#</p>#";
        $converter->replace[] = "\n";

        $txt = $converter->get_text();

        $txt = preg_replace('/^\s+/m', "\n", $txt);
        $txt = preg_replace_callback('/\{\w+\}/', function($m){
                return strtolower($m[0]);
        }, $txt);

        // Html2Text will treat links as relative to the current host. We don't want that!
        // (because of links like <a href='{shop_url}'></a>)
        if(!empty($_SERVER['HTTP_HOST']))
        {
                $txt = preg_replace('#\w+://'.$_SERVER['HTTP_HOST'].'/#', '', $txt);
        }

        return $txt;
    }

	public function generateEmail($template, $languageCode)
	{
		static $cssin;

		if (!$cssin)
		{
			$cssin = new CSSIN();
		}

		$template_url = $this->getPreviewURL($template, $languageCode);

		$output_basename = $this->getBaseOutputName($template, $languageCode);
		if ($output_basename === false)
			return $this->l('Template name is invalid.');

		$html = $cssin->inlineCSS($template_url);
		$text = $this->textify($html);

		$write = array(
			$output_basename.'.txt' => $text,
			$output_basename.'.html' => $html
		);

		foreach ($write as $path => $data)
		{
			$dir = dirname($path);
			if (!is_dir($dir))
			{
				if(!@mkdir($dir, 0777, true))
					return $this->l('Could not create directory to write email to.');
			}
			if(!@file_put_contents($path, $data))
				return $this->l('Could not write email file.');
		}
		return true;
	}

	public function getPreviewURL($template, $languageCode)
	{
		return Tools::getShopDomain(true).__PS_BASE_URI__
		.'modules/emailgenerator/templates/viewer.php?template='.urlencode($template)
		.'&languageCode='.$languageCode;
	}

	public function currentLanguageCode()
	{
		if (($languageCode = Tools::getValue('languageCode')) && preg_match('/[a-z]{2}/', $languageCode))
			return $languageCode;
		else
			return $this->context->language->iso_code;
	}

	public function getTranslations($path)
	{
		if (file_exists($path))
		{
			global $_LANGMAIL;
			$old = $_LANGMAIL;

			include($path);
			$dictionary = $_LANGMAIL;
			$_LANGMAIL = $old;
			return $dictionary;	
		}
		else
			return array();
	}

	public function getBodyTranslations($languageCode)
	{
		$path = dirname(__FILE__).'/templates_translations/'.$languageCode.'/lang_content.php';
		return $this->getTranslations($path);
	}

	public function getSubjectTranslations($languageCode)
	{
		$path = _PS_MAIL_DIR_.$languageCode.'/lang.php';
		return $this->getTranslations($path);
	}

	public static function unquote($string)
	{
		return preg_replace(array('/(?:^[\'"]|[\'"]$)/', '/\\\+([\'"])/'), array('', '\1'), $string);
	}

	public static function recListPSPHPFiles($dir)
	{
		foreach (array(_PS_CACHE_DIR_, _PS_TOOL_DIR_) as $skip)
		{
			if (preg_replace('#/$#', '', $skip) === preg_replace('#/$#', '', $dir))
			{
				return array();
			}
		}

		$paths = array();

		if (is_dir($dir))
		{
			foreach (scandir($dir) as $entry)
			{
				if (!preg_match('/^\./', basename($entry)))
				{
					$path = $dir.'/'.$entry;
					if (is_dir($path))
						$paths = array_merge($paths, self::recListPSPHPFiles($path));
					else if (preg_match('/\.php$/', $entry))
						$paths[] = $path;
				}
			}
		}

		return $paths;
	}

	public function extractEmailStrings($template, $language)
	{
		$id = 0;

		require_once dirname(__FILE__).'/vendor/simple_parsers/classes/SimplePHPFunctionCallParser.php';
		$data = file_get_contents(dirname(__FILE__).'/'.$template);
		$parser = new SimplePHPFunctionCallParser('t');
		
		$body_strings = $parser->parse($data);
		$body_translations = $this->getBodyTranslations($language);

		$body = array();
		foreach($body_strings as $str)
		{
			$str = self::unquote($str[0]);
			if (isset($body_translations[$str]))
				$translation = $body_translations[$str];
			else
				$translation = '';

			$body[$str] = array(
				'translation' => $translation,
				'id' => ++$id,
				'file' => 0
			);
		}


		$subject_strings = array();
		$potential_subject_strings = array();

		foreach (self::recListPSPHPFiles(_PS_ROOT_DIR_) as $path)
		{
			$sendParser = new SimplePHPFunctionCallParser('Mail\s*::\s*Send');
			$sends = $sendParser->parse(file_get_contents($path));
			if (count($sends) > 0)
			{
				foreach ($sends as $send)
				{
					if (!isset($send[1]) || !isset($send[2]))
						continue;

					$templateName = self::unquote($send[1]);
					if ($templateName === basename($template, '.php'))
					{
						$subjectParser = new SimplePHPFunctionCallParser('l');
						$subjects = $subjectParser->parse($send[2]);
						if (count($subjects) === 1)
							$subject_strings[] = self::unquote($subjects[0][0]);
					}
					else if(!preg_match('/^\w/', $templateName))
					{
						$subjectParser = new SimplePHPFunctionCallParser('l');
						$subjects = $subjectParser->parse($send[2]);
						if (count($subjects) === 1)
							$potential_subject_strings[] = self::unquote($subjects[0][0]);
					}
				}
			}
		}

		$subject_translations = $this->getSubjectTranslations($language);
		
		$subjects = array();
		foreach($subject_strings as $str)
		{
			if (isset($subject_translations[$str]))
				$translation = $subject_translations[$str];
			else
				$translation = '';

			$subjects[$str] = array(
				'translation' => $translation, 
				'id' => ++$id,
				'file' => 1
			);
		}

		$potential_subjects = array();
		foreach($potential_subject_strings as $str)
		{
			if (isset($subject_translations[$str]))
				$translation = $subject_translations[$str];
			else
				$translation = '';

			$potential_subjects[$str] = array(
				'translation' => $translation, 
				'id' => ++$id,
				'file' => 1
			);
		}

		return array(
			'body' => $body,
			'subjects' => $subjects,
			'potential_subjects' => $potential_subjects,
			'files' => array(
				0 => 'modules/emailgenerator/templates_translations/'.$language.'/lang_content.php',
				1 => 'mails/'.$language.'/lang.php'
			)
		);
	}

	public function isValidTemplatePath($template)
	{
		return preg_match('#^templates/(?:core|modules/[^/]+)/[^/]+\.php$#', $template)
			&& file_exists(dirname(__FILE__).'/'.$template);
	}

	public function getBaseOutputName($template, $languageCode)
	{
		$m = array();
		if (preg_match('#^templates/core/[^/]+\.php$#', $template))
			return _PS_ROOT_DIR_.'/mails/'.$languageCode.'/'.basename($template, '.php');
		else if (preg_match('#^templates/modules/([^/]+)/(?:[^/]+)\.php$#', $template, $m))
			return _PS_MODULE_DIR_.$m[1].'/mails/'.$languageCode.'/'.basename($template, '.php');
		else
			return false;
	}

	public function isValidTranslationFilePath($path)
	{
		$absPath = realpath(_PS_ROOT_DIR_.'/'.$path);
		$path = substr($absPath, strlen(_PS_ROOT_DIR_)+1);
		return 
			preg_match('#^(?:mails/[a-z]{2}/lang\.php|modules/emailgenerator/templates_translations/[a-z]{2}/lang_content\.php)$#', $path)
			? $absPath
			: false;
	}
}