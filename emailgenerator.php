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
		$actionName = Tools::getValue('action');

		if ($actionName)
		{
			$method = strtolower($_SERVER['REQUEST_METHOD']);
			$action = $method.ucfirst($actionName).'Action';
			$this->template = $actionName;
		}
		else
		{
			$actionName = 'index';
			$action = 'getIndexAction';
			$this->template = 'index';
		}

		$data = array();
		if (is_callable(array($this, $action)))
		{
			$values = $this->$action();
			if (is_array($values))
				$data = $values;
		}
		else
		{
			$data['error'] = 'Action not found: '.$actionName;
			$this->template = 'error';
		}

		$data = array_merge($this->getDefaultViewParameters(), $data);

		$this->context->smarty->assign($data);
		return $this->context->smarty->fetch(
			dirname(__FILE__).'/views/templates/admin/'.$this->template.'.tpl'
		);
	}

	public function getDefaultViewParameters()
	{
		$hidden = array(
			'token' => Tools::getValue('token'),
			'configure' => $this->name,
			'controller' => 'AdminModules'
		);

		$inputs = array();
		$params = array();
		foreach ($hidden as $name => $value)
		{
			$inputs[] = "<input type='hidden' name='$name' value='$value'>";
			$params[] = urlencode($name).'='.urlencode($value);
		}
		$stay_here = implode("\n", $inputs);
		$url = '?'.implode('&', $params);

		return array(
			'stay_here' => $stay_here,
			'url' => $url
		);
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

	public function getIndexAction()
	{
		$templates = self::listEmailTemplates();
		return array(
			'templates' => $templates,
			'languages' => Language::getLanguages()
		);
	}

	public static function loadTranslatools()
	{
		if (!class_exists('Translatools'))
		{
			$ttPath = _PS_MODULE_DIR_.'translatools/translatools.php';
			require_once $ttPath;
		}
	}

	public function getTranslationsAction()
	{
		self::loadTranslatools();

		$iso_code = Tools::getValue('language');

		if (!class_exists('Translatools'))
		{
			$ttPath = _PS_MODULE_DIR_.'translatools/translatools.php';
			require_once $ttPath;
		}

		$subjectsExtractor = Translatools::getNewTranslationsExtractor($iso_code);
		$subjectsExtractor->extractMailSubjectsStrings();
		$subjectsExtractor->fill();

		$contentExtractor = Translatools::getNewTranslationsExtractor($iso_code);
		$contentExtractor->extractMailContentStrings();
		$contentExtractor->fill();

		return array(
			'language' => $iso_code,
			'subjects' => $subjectsExtractor->getFiles(),
			'content' => $contentExtractor->getFiles()
		);

		return array();
	}

	public function postTranslationsAction()
	{
		self::loadTranslatools();

		$iso_code = Tools::getValue('language');
		$ttPath = _PS_MODULE_DIR_.'translatools/translatools.php';
		
		$extractor = Translatools::getNewTranslationsExtractor($iso_code);
		foreach (Tools::getValue('translations') as $file => $data)
		{
			$path = _PS_ROOT_DIR_.'/'.str_replace('(lc)', $iso_code, $file);
			$contents = $extractor->dictionaryToArray('_LANGMAIL', $data);
			$dir = dirname($path);
			if (!is_dir($dir))
				mkdir($dir, 0777, true);
			file_put_contents($path, $contents);
		}
		
		return $this->getTranslationsAction();
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

	public function generateEmail($template, $output_basename, $language)
	{
		static $cssin;

		if (!$cssin)
		{
			$cssin = new CSSIN();
		}

		$template_url = Tools::getShopDomain(true).__PS_BASE_URI__
		.'modules/emailgenerator/templates/viewer.php?template='.urlencode($template)
		.'&language='.$language;

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
				mkdir($dir, 0777, true);
			}
			file_put_contents($path, $data);
		}
	}
}