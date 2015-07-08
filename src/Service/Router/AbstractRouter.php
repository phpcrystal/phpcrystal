<?php
namespace PHPCrystal\PHPCrystal\Service\Router;

use PHPCrystal\PHPCrystal\Service\Event as Event;
use PHPCrystal\PHPCrystal\Component\Factory as Factory;
use PHPCrystal\PHPCrystal\Component\Http\Request;
use PHPCrystal\PHPCrystal\Component\Http\Uri;
use PHPCrystal\PHPCrystal\Component\Service\AbstractService;

abstract class AbstractRouter extends AbstractService
{
	protected $protocol;
	protected $hostname;
	protected $uriPathPrefix;

	protected $frontController;
	protected $controller;
	protected $action;
	
	private $actions = array();
	
	public function __construct()
	{
		parent::__construct();
	}
	
	/**
	 * @return boolean
	 */
	final protected function matchRequest(Request $request)
	{
		if (isset($this->hostname) && $this->hostname != $request->getHostname()) {
			return false;
		} else if (isset($this->uriPathPrefix) &&
			strpos($request->getUri()->getPath(), $this->uriPathPrefix) !== 0)
		{
			return false;
		} else {
			return true;
		}
	}
	
	/**
	 * @return void
	 */
	public function init()
	{
		$context = $this->getApplication()->getContext();
		$this->protocol = 'http';
		$this->hostname = $context->get('app.hostname');
		$this->uriPathPrefix = '/';
	}
	

	/**
	 * @return void
	 */
	protected function triggerResponse404(Event\Type\Http\Request $event, $resEvent)
	{
		$event->setAutoTriggerEvent($resEvent);
		$event->discard();
	}	
	
	/**
	 * All routers are being initialized after application was boostraped
	 * 
	 * @return boolean
	 */
	final public static function hasLazyInit()
	{
		return true;
	}
	
	/**
	 * @return null
	 */
	final public function addAction($instance)
	{
		$this->actions[] = $instance;
	}
	
	/**
	 * @return array
	 */
	final protected function getAllActions()
	{
		return $this->actions;
	}

	/**
	 * @return
	 */
	final public function getFrontController()
	{
		return $this->frontController;
	}
	
	/**
	 * @return
	 */
	final public function getController()
	{
		return $this->controller;
	}
	
	/**
	 * @return
	 */
	final public function getAction()
	{
		return $this->action;
	}
	
	/**
	 * @return string
	 */
	final public function getHostname()
	{
		return $this->hostname;
	}
	
	/**
	 * @return string
	 */
	final public function getUriPathPrefix()
	{
		return $this->uriPathPrefix;
	}
	
	/**
	 * @return string
	 */
	final public function getProtocol()
	{
		return $this->protocol;
	}
	
	/**
	 * @return Uri
	 */
	public function getBaseUri()
	{
		$baseUriStr = $this->getProtocol() . '://' . $this->getHostname() .
			$this->getUriPathPrefix();
		
		return new Uri($baseUriStr);
	}

	/**
	 * @return boolean
	 */
	abstract public function handle(Event\Type\Http\Request $event);	
}
