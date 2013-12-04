<?php
/**	\file
 *	FVAL PHP Framework for Web Applications
 *
 *	\copyright Copyright (c) 2007-2013 FVAL Consultoria e Informática Ltda.\n
 *	\copyright Copyright (c) 2011-2013 Lucas Cardozo
 *
 *	\brief		Classe de construção e tratamento de objetos JSON
 *	\warning	Este arquivo é parte integrante do framework e não pode ser omitido
 *	\version	0.1.1
 *  \author		Lucas Cardozo - lucas.cardozo@gmail.com
 *	\ingroup	framework
 */

class JSON {
	private $dados = array();
	private $headerStatus = 200;

	public function __construct() {
		Kernel::setConf('system', 'ajax', true);
		header('Content-type: application/json; charset=' . $GLOBALS['SYSTEM']['CHARSET'], true, $this->headerStatus);
	}

	/**
	 *  \brief Adiciona um dado ao JSON
	 */
	public function add($dados) {
		$this->dados = array_merge($this->dados, $dados);
	}

	/**
	 *  \brief Pega todos os dados do JSON
	 */
	public function getDados() {
		return $this->dados;
	}

	/**
	 *  \brief Inicializa o HTTP Header para objeto JSON
	 */
	public function setHeaderStatus($status) {
		$this->headerStatus = $status;
		header('Content-type: application/json; charset=' . $GLOBALS['SYSTEM']['CHARSET'], true, $this->headerStatus);
		header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
		header('Last-Modified: ' . gmdate( 'D, d M Y H:i:s' ) . ' GMT');
		header('Cache-Control: no-store, no-cache, must-revalidate');
		header('Cache-Control: post-check=0, pre-check=0', false);
		header('Pragma: no-cache');
	}

	/**
	 *  \brief Codifica o objeto JSON
	 */
	public function fetch() {
		foreach (JSON_Static::getDefaultVars() as $name => $value) {
			if (!isset($this->dados[$name])) {
				$this->dados[$name] = $value;
			}
		}

		return json_encode($this->dados);
	}

	/**
	 *  \brief Imprime o objeto JSON
	 */
	public function printJ($andDie=true) {
		if (Kernel::getConf('system', 'debug')) {
			$this->dados['debug'] = Kernel::getDebugContent();
		}

		echo $this->fetch();

		if ($andDie) {
			die;
		}
	}

	/**
	 *  \brief Converte o objeto JSON para String
	 */
	public function __toString() {
		$this->printJ();
	}
}