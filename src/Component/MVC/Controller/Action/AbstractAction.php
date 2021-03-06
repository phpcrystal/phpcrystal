<?php
namespace PHPCrystal\PHPCrystal\Component\MVC\Controller\Action;

use PHPCrystal\PHPCrystal\Component\MVC\Controller\Input;

use PHPCrystal\PHPCrystal\Component\Http\Request;
use PHPCrystal\PHPCrystal\Component\Http\Uri;
use PHPCrystal\PHPCrystal\Service\Event as Event;
use PHPCrystal\PHPCrystal\Component\Factory as Factory;

abstract class AbstractAction extends Event\AbstractAppListener
{
	/**
	 * URI path pattern to match. Example /user/<d:user_id>/profile/edit/
	 * 
	 * @var string
	 */
	private $uriMatchPattern;
	
	/**
	 * @var array
	 */
	private $allowedHttpMethods = array();
	
	/**
	 * @var string
	 */
	private $controllerMethod;
	
	/**
	 * @var string
	 */
	private $uriMatchRegExp;
	
	/**
	 * If set to false the action didn't match the request
	 * 
	 * @var boolean
	 */
	private $isValid = true;

	/** @var */
	private $validator;

	/**
	 * @var boolean
	 */
	protected $startTransaction = false;
	
	/**
	 * @var string|null
	 */
	protected $transactionLevel;
	
	protected $ctrlInstance;
	protected $ctrlInput;
	
	protected $execResult;
	protected $execSuccess = false;
	
	// Router hostname and URI path prefix. Being used by reverse routing
	protected $routerHostname;
	protected $routerUriPathPrefix;

	/**
	 * Returns the canonical name of an action
	 * 
	 * @return string
	 */
	final public function getName()
	{
		$parts = explode('\\', get_class($this));
		
		return join('\\', array_slice($parts, 3));
	}
	
	/**
	 * @return string 
	 */
	final static public function getControllerName()
	{
		return join('\\', array_slice(explode('\\', static::class), 3, 2));
	}
	
	/**
	 * @return string
	 */
	final static public function getControllerClassName()
	{
		$parts = explode('\\', static::class);
				
		return join('\\', array_merge(array_slice($parts, 0, 2), ['Controller'],
			array_slice($parts, 3, 2)));
	}
	
	/**
	 * @return boolean
	 */
	final public function isValid()
	{
		return $this->isValid;
	}
	
	/**
	 * @return void
	 */
	final public function setValidityFlag($flag)
	{
		$this->isValid = $flag;
	}
	
	/**
	 * @return void
	 */
	final public function setHostname($hostname)
	{
		$this->routerHostname = $hostname;
	}

	/**
	 * @return null
	 */
	protected function getValidator()
	{
		return $this->validator;
	}
	
	/**
	 * @return void
	 */
	protected function setValidator($object)
	{
		$this->validator = $object;
	}
	
	/**
	 * @return boolean
	 */
	public function matchRequest(Request $request)
	{
		$allowedMethods = $this->getAllowedHttpMethods();
		if ( ! empty($allowedMethods) && ! in_array($request->getMethod(), $allowedMethods)) {
			return false;
		}
		
		$regExp = $this->getURIMatchRegExp();
		
		if ( ! empty($regExp)) {
			$matches = null;
			if ( ! $request->getUri()->matchUriPath($regExp, $matches)) {
				return false;
			}

			// Fill the URI input container with pattern matches
			array_shift($matches);
			$tmpArray = [];
			foreach ($matches as $itemKey => $itemValue) {
				if (is_integer($itemKey))  {
					continue;
				}
				$tmpArray[$itemKey] = $itemValue;
			}
			
			$uriInput = $this->getApplication()->getRequest()->getURIInput();
			$uriInput->merge(Input::create($tmpArray));

			// set default placeholder value
			$routeAnnot = $this->getExtendableInstance()->getRouteAnnotation();
			if ($routeAnnot->hasDefaultItemValue()) {
				$itemValue = isset($matches[1]) ? $matches[1] :
					$routeAnnot->getDefaultItemValue();

				$uriInput->set($routeAnnot->getDefaultItemKey(), $itemValue);
			}

			return true;
		} else {
			return false;
		}
	}

	/**
	 * @return void
	 */
	public function init()
	{
		parent::init();

		$this->addEventListener(Event\Type\Http\Request::toType(), function($event) {
			$this->onHttpRequest($event);
		});

		$this->addEventListener(Event\Type\Http\Response200::toType(), function($event) {
			$requestEvent = $event->getLastDispatchedEvent();
			$execResult = $requestEvent->getResult();
			return $this->onResponse200($event, $execResult);
		});

		$extendable = $this->getExtendableInstance();		

		if ($extendable) {
			$routeAnnot = $extendable->getRouteAnnotation();
			$ctrlMethodAnnot = $extendable->getControllerMethodAnnotation();

			$this->setControllerMethod($ctrlMethodAnnot->getMethodName());
			$this->setAllowedHttpMethods($routeAnnot->getAllowedHttpMethods());
			$this->setURIMatchRegExp($routeAnnot->getURIMatchRegExp());		
			$this->setURIMatchPattern($routeAnnot->getMatchPattern());

			$validator = $this->instantiateValidator();			
			$this->setValidator($validator);
		}
	}
	
	private function createControllerInput()
	{
		$ctrlInput = Input::create();
		$request = $this->getApplication()->getRequest();
		if (null === ($extandable = $this->getExtendableInstance()) ||
			null === ($inputAnnot = $extandable->getInputAnnot()))
		{
			$ctrlInput->merge($request->getGetInput());
			$ctrlInput->merge($request->getPostInput());
			$ctrlInput->merge($request->getCookieInput());
			$ctrlInput->merge($request->getURIInput());
		} else {
			foreach ($inputAnnot->getInputChannelList() as $channelName) {
				switch ($channelName) {
					case 'POST':
						$ctrlInput->merge($request->getPostInput());
						break;
					case 'GET':
						$ctrlInput->merge($request->getGetInput());
						break;
					case 'COOKIE':
						$ctrlInput->merge($request->getCookieInput());
						break;
					case 'URI':
						$ctrlInput->merge($request->getURIInput());
						break;
				}
			}
		}
		
		return $ctrlInput;
	}
	
	/**
	 * @return object
	 */
	private function instantiateValidator()
	{
		$validatorAnnot = $this->getExtendableInstance()
			->getValidatorAnnot();
		
		$externalEvent = $this->getApplication()->getCurrentEvent();

		if ( ! $validatorAnnot || ! ($externalEvent instanceof Event\Type\Http\Request)) {
			return;
		}

		$request = $this->getApplication()->getRequest();		
		$requestMethod = $request->getMethod();
		$targetMethods = $validatorAnnot->getTargetMethods();
		if ( ! in_array($requestMethod, $targetMethods) &&
			$targetMethods[0] != 'ALL')
		{
			return;
		}

		$validatorClass = $validatorAnnot->getClassName();
		if (empty($validatorClass)) {
			$name = $validatorAnnot->getDefaultName();
			$parts = explode('\\', static::class);
			array_pop($parts);
			$parts[] = 'Validator';
			$parts[] = $name;
			$validatorClass = join('\\', $parts);
		}

		$validatorInstance = new $validatorClass();
		
		return $validatorInstance;
	}

	/**
	 * @return void
	 */
	final public function redirect(Uri $uri, $code = 302)
	{
		switch ($code) {
			case 302:
				$redirectEvent = Event\Type\Http\Response302::create()
					->setLocationUri($uri);
				break;
			
			case 303:
				$redirectEvent = Event\Type\Http\Response303::create()
					->setLocationUri($uri);
				break;
		}
		
		$this->getApplication()->getCurrentEvent()
			->setAutoTriggerEvent($redirectEvent);
	}
	
	/**
	 * @return void
	 */
	final public function redirectToAction($actionName, $urlParams = [], $code = 303)
	{
		$action = $this->getFactory()->createAction($actionName);
		$uri = $action->getReverseUri($urlParams);
		
		$this->redirect($uri, $code);
	}
	
	/**
	 * @return array
	 */
	public function getAllowedHttpMethods()
	{
		return $this->allowedHttpMethods;
	}
	
	/**
	 * @return void
	 */
	final public function setAllowedHttpMethods(array $methods)
	{
		$this->allowedHttpMethods = $methods;
	}
	
	/**
	 * @return string
	 */
	public function getURIMatchRegExp()
	{
		return $this->uriMatchRegExp;
	}
	
	/**
	 * @return void
	 */
	final public function setUriMatchRegExp($regExp)
	{
		$this->uriMatchRegExp = $regExp;
	}
	
	/**
	 * @return string
	 */
	public function getControllerMethod(Request $request = null)
	{
		return $this->controllerMethod;
	}
	
	/**
	 * @return void
	 */
	final public function setControllerMethod($name)
	{
		$this->controllerMethod = $name;
	}

	/**
	 * @return Controller
	 */
	final public function getController()
	{
		return $this->controller;
	}
	
	/**
	 * @return
	 */
	final public function execute($event)
	{
		try {
			$this->onPreExec($event);
			
			$methodName = $this->getControllerMethod($event->getRequest());
			$ctrlMethodServices = $this->getFactory()
				->getMethodInjectedServices($this->ctrlInstance, $methodName);
			
			$ctrlArgs = array_merge([$this->getInput()], $ctrlMethodServices);
			$this->execResult = call_user_func_array([$this->ctrlInstance, $methodName],
				$ctrlArgs);

			if ($this->execResult === false) {		
				return $this->onGracefulFail($event);
			}
		} catch (\Exception $e) {
			$this->onHardFail($event, $e);
			throw $e;
		}
	
		// the value returned by this method will be assigned to the request
		// event result.
		return $this->onPostExec($event);
	}

	final public function getInput()
	{
		return $this->ctrlInput;
	}
	
	/**
	 * @return tring
	 */
	final public function getURIMatchPattern()
	{
		return $this->uriMatchPattern;
	}
	
	/**
	 * @return void
	 */
	final public function setURIMatchPattern($pattern)
	{
		$this->uriMatchPattern = $pattern;
	}
	
	/**
	 * @return string
	 */
	public function getReverseURI(...$params)
	{
		$uriString = $this->getURIMatchPattern();
		if ( ! empty($uriString)) {
			$uriString = preg_replace_callback('|{[^}]+}|', function() use($params) {
				return array_shift($params);
			}, $uriString);
		}
		
		return $uriString;
	}

	//
	// Event hooks
	//

	/**
	 * @return mixed
	 */
	final protected function onHttpRequest($event)
	{
		if ($event->getPhase() == Event\PHASE_DOWN) {
			// Set controller instance
			$this->ctrlInstance = $event->getTarget()
				->getPropagationPathPrevNode($this);

			// Do data validation if necessary
			$validator = $this->getValidator();
			if (null !== $validator) {
				$this->ctrlInput = $this->createControllerInput();
				$validator->setInput($this->ctrlInput);
				$result = $validator->run();
				if ( ! $result) {
					$event->discard();
					$this->onDataValidationFail($event, $validator);
				}
			}

		} else if ($event->getPhase() == Event\PHASE_UP && $this->execResult !== false) {
			$this->onSuccess($event);
		}
	}
	
	/**
	 * @return void
	 */
	public function onResponse200($event, $execResult = null)
	{
		
	}
		
	/**
	 * @return void
	 */
	protected function onPreExec($event)
	{
		if ($this->startTransaction) {
			$this->ctrlInstance->getDbAdapter()
				->startTransaction($this->transactionLevel);
		}
	}
	
	/**
	 * @return mixed
	 */
	protected function onPostExec($event)
	{
		if ($this->startTransaction) {
			$this->ctrlInstance->getDbAdapter()->commit();
		}
		
		return $this->execResult;
	}
	
	/**
	 * @return void
	 */
	protected function onSuccess($event) {  }
	
	/**
	 * @return void
	 */
	protected function onGracefulFail($event)
	{
		if ($this->startTransaction) {
			$this->ctrlInstance->getDbAdapter()->rollback();
		}	
	}
	
	/**
	 * @return void
	 */
	protected function onHardFail($event, \Exception $e)
	{
		if ($this->startTransaction) {
			$this->ctrlInstance->getDbAdapter()->rollback();
		}		
	}
	
	/**
	 * @return void
	 */
	protected function onDataValidationFail($event, $validator) {  }
}
