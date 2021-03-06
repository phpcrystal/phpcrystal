<?php
namespace PHPCrystal\PHPCrystal\Service\Event\Type\Http;

class Response500 extends AbstractResponse
{
	public function __construct()
	{
		parent::__construct();
		$this->getHttpResponse()->setStatusCode(500);
	}
	
	public function output()
	{
		http_response_code(500);
		parent::output();
	}
}