<?php

	namespace Tilwa\Routing;

	use SuperClosure\Serializer;

	use Tilwa\Http\Request\BaseRequest;

	use Tilwa\Contracts\HtmlParser;

	class Route {

		public $pattern;

		public $method;

		private $middleware; // array

		public $requestSlug;

		private $rawResponse;

		private $handler;

		private $request;

		private $controller;

		public $contentNegotiable;


		public function getMiddlewares () {

			return $this->middleware;
		}

		public function setPath (string $name):self {

			$this->requestSlug = $name;

			return $this;
		}

		public function getRequest():BaseRequest {
			
			return $this->request;
		}

		public function setRequest(BaseRequest $request):static {
			
			$this->request = $request;

			return $this;
		}

		public function renderResponse ($adapter) {

			return $this->publishJson();
		}

		public function publishHtml(HtmlParser $htmlAdapter) {
			
			// you want to call this->runViewModels somewhere here
			return $htmlAdapter->parseAll();
		}

		protected function publishJson() {

			$request = $this->request;

			if (!$request->isValidated())

				$response = $request->validationErrors();

			else $response = $this->rawResponse;
			
			return json_encode($response);
		}

		public function execute (array $handlerParameters):static {

			$this->rawResponse = call_user_func_array([

				$this->getController(), $this->handler], $handlerParameters
			);

			return $this;
		}

		public function assignMethod($userMethod):self {
			
			$methods = ["get", "post", "put", "delete"];

			$this->method = array_filter($methods, function ($m) use ($userMethod) {
				
				return $m == strtolower($userMethod);
			})[0];

			return $this;
		}

		public function setMiddleware(array $middleware ) {
			
			$this->middleware = $middleware;
		}

		public function setController($class ):void { // put this on the manager
			
			$this->controller = $class;
		}

		public function isContentNegotiable() {
			
			$this->contentNegotiable = true;
		}
	}
?>