<?php

namespace Hcode;

use Rain\Tpl;

class Page
{

	private $tpl;
	private $options = [];
	private $defaults = [
		"header" => true,
		"footer" => true,
		"data"   => []
	];

	public function __construct($opts = array(), $tpl_dir = "/views/")
	{

		// Mescla opções padrão com as opções recebidas
		$this->options = array_merge($this->defaults, $opts);

		// ✅ GARANTE que "data" sempre exista e seja array
		if (!isset($this->options["data"]) || !is_array($this->options["data"])) {
			$this->options["data"] = [];
		}

		// ✅ Defaults das notificações (para o header nunca quebrar)
		$this->options["data"] = array_merge([
			"total" => 0,
			"notificacoes" => [],
			"ultimoBackup" => null,
			"statusBackup" => null
		], $this->options["data"]);

		// Normaliza diretórios (sempre termina com barra)
		$tplBase = defined('VIEW_DIR') ? VIEW_DIR : $_SERVER["DOCUMENT_ROOT"];
		$tplPath = $tplBase . str_replace('/views', '', $tpl_dir);
		$tplPath = rtrim($tplPath, "/\\") . DIRECTORY_SEPARATOR;

		$cacheBase = defined('VIEW_CACHE_DIR') ? VIEW_CACHE_DIR : ($_SERVER["DOCUMENT_ROOT"] . "/views-cache/");
		$cacheBase = rtrim($cacheBase, "/\\") . DIRECTORY_SEPARATOR;

		$config = array(
			"tpl_dir"   => $tplPath,
			"cache_dir" => $cacheBase,
			// ✅ Força extensão dos templates (evita procurar .tpl quando seus arquivos são .html)
			"tpl_ext"   => "html",
			"debug"     => false
		);

		Tpl::configure($config);

		$this->tpl = new Tpl;

		$this->setData($this->options["data"]);

		if ($this->options["header"] === true) $this->tpl->draw("header");
	}

	private function setData($data = array())
	{
		foreach ($data as $key => $value) {
			$this->tpl->assign($key, $value);
		}
	}

	public function setTpl($name, $data = array(), $returnHTML = false)
	{
		$this->setData($data);
		return $this->tpl->draw($name, $returnHTML);
	}

	public function __destruct()
	{
		if ($this->options["footer"] === true) $this->tpl->draw("footer");
	}
}
