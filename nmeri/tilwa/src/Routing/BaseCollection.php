<?php

	namespace Tilwa\Routing;

	use Tilwa\Response\Format\AbstractRenderer;

	use Tilwa\Auth\TokenStorage;

	use Tilwa\Contracts\RouteCollection;

	abstract class BaseCollection implements RouteCollection {

		protected $canaryValidator, $routerConfig, $authStorage, $middlewareRegistry;

		private $utilities = ["_mirrorBrowserRoutes", "_authenticatedPaths", "_handlingClass", "_crud", "_register", "_setAllow", "_canaryEntry", "_setLocalPrefix", "_whenUnauthorized"
		],

		$mirroring = false, $crudMode = false, $localPrefix, $prefixClass;

		/**
		* overwrite in your routes file
		*	
		* will be treated specially in the matcher, when path is empty i.e. /, cart/
		*/
		public function _index ():array; // register a route here

		/**
		* @description: should be called only in the API first version's _index method
		* Assumes that _index method is defined last so subsequent methods found within the same scope can overwrite methods from the nested browser route search
		*/
		public function _mirrorBrowserRoutes (TokenStorage $tokenStorage):void {

			$this->authStorage = $tokenStorage;

			$this->mirroring = true;

			$this->_prefixFor($this->routerConfig->browserEntryRoute());
		}
		
		public function _prefixCurrent():string {
			
			return "";
		}
		
		// crud routes must be anchored by either a preceding collection group name, or the current one. So, we make that assertion from this property set externally by the manager
		public function _setLocalPrefix(string $prefix):void {
			
			$this->localPrefix = $prefix;
		}

		protected function _crud (string $viewPath):CrudBuilder {

			if (!empty($this->localPrefix)) { // confirm setting neither creates no crud routes

				$this->crudMode = true;

				return new CrudBuilder($this, $viewPath, $this->routerConfig->getModelRequestParameter()); // you must call `save` in the invoking method
			}
		}

		public function __call ($method, $renderer) {

			if (in_array($method, ["_get", "_post", "_delete", "_put"]))

				return $this->_register($renderer, $method);
		}

		protected function _register(AbstractRenderer $renderer, string $method):array {

			$renderer->setRouteMethod(ltrim($method, "_"));

			return [$renderer];
		}

		public function _prefixFor (string $routeClass):void {

			$this->prefixClass = $routeClass;
		}

		# filter off methods that belong to this base
		public function getPatterns():array {

			return array_diff(get_class_methods($this), $this->utilities);
		}

		public function _authenticatedPaths():array {}

		public function _authorizePaths():void {}

		public function _assignMiddleware():void {}

		public function _getAuthenticator ():AuthStorage {

			return $this->authStorage;
		}

		protected function _only(array $include):array {
			
			return array_intersect($this->getPatterns(), $include);
		}

		protected function _except(array $exclude):array {
			
			return array_diff($this->getPatterns(), $exclude);
		}

		protected function _canaryEntry(array $canaries):void {

			$validEntries = $this->canaryValidator->validate($canaries);
			
			foreach ($validEntries as $canary)
				
				if ($canary->willLoad() )

					return $this->_prefixFor($canary->entryClass());
		}

		public function getPrefixCollection ():string {

			return $this->prefixClass;
		}

		public function isMirroring ():bool {

			return $this->mirroring;
		}

		public function expectsCrud ():bool {

			return $this->crudMode;
		}

		public function getLocalPrefix ():string {

			return $this->localPrefix;
		}
	}
?>