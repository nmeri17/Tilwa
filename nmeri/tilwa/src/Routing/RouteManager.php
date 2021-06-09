<?php

	namespace Tilwa\Routing;

	use Tilwa\App\Container;

	use Tilwa\Http\Response\Format\Markup;

	use Generator;

	use Tilwa\Contracts\Config\Router as RouterConfig;

	class RouteManager {

		const PREV_RENDERER = 'prev_renderer';

		const PREV_REQUEST = 'prev_request';

		private $config, $activeRenderer, $payload,

		$requestDetails, $fullTriedPath, $container;

		function __construct(RouterConfig $config, Container $container, RequestDetails $requestDetails) {

			$this->config = $config;

			$this->container = $container;

			$this->requestDetails = $requestDetails;
		}

		public function findRenderer ():AbstractRenderer {

			$this->container->whenType(RouteCollection::class)

			->needsArguments($this->getCollectionArguments())

			foreach ($this->entryRouteMap() as $collection) {
				
				$hit = $this->recursiveSearch($collection);

				if (!is_null($hit)) {

					$hit->setPath($this->fullTriedPath);

					return $hit;
				}
			}
		}

		public function loadPatterns(RouteCollection $collection):Generator {

			if ($collection->_passover())
			
				foreach ($collection->getPatterns() as $pattern)
				 	
				 	yield $pattern;
			else yield;
		}

		/**
		* to find from cache, we won't need:
			- to parse our route before matching
			- loadPatterns?
		*/
		private function recursiveSearch(string $patternsCollection, string $routeState = "", string $invokerPrefix = "", bool $fromCache = false):AbstractRenderer {

			$collection = $this->container
			
			->getClass($patternsCollection, true);

			$patternPrefix = $invokerPrefix ?? $collection->_prefixCurrent();

			$collection->_setLocalPrefix($patternPrefix);
			
			foreach ($this->loadPatterns($collection) as $pattern) {

				$rendererList = call_user_func([$collection, $pattern]);
				/*
					- pair empty incoming path with _index method
					- crud methods disregard their method names
				*/
				if (($pattern == "_index") || $collection->expectsCrud) $pattern = "";

				if (!empty($patternPrefix) ) $pattern = "$patternPrefix/$pattern";

				$newRouteState = $invokerPrefix ? "$routeState/$pattern": $pattern;

				$parsed = $this->regexForm($newRouteState);

				if (!is_null($collection->prefixClass) && $this->prefixMatch($parsed)) { // only delve deeper if we're on the right track i.e. if nested path = foo/bar/foobar, and nested method "bar" defines prefix, we only wanna explore its contents if requested route matches foo/bar

					return $this->recursiveSearch($collection->prefixClass, $newRouteState, $pattern); // we don't bother checking whether a route was found or not because if there was none after going downwards, searching sideways won't help either
				}
				else {
					foreach ($rendererList as $path => $renderer) { // we'll usually get one route here, except for CRUD invocations

						if ($collection->expectsCrud)

							$parsed .= $this->regexForm($path);

						if ($this->routeCompare($parsed, $renderer->getRouteMethod())) {

							$this->fullTriedPath = $parsed;

							if ($renderer instanceof Markup && $collection->isMirroring)

								$renderer->contentIsNegotiable();
							
							return $this->bootRenderer($renderer, $collection->_handlingClass());
						}
					}
					$collection->expectsCrud = null; // for subsequent patterns
				}
			}
		}

		private function routeCompare(string $path, string $rendererMethod):bool {
			
			return $this->prefixMatch($path) && $rendererMethod == $this->requestDetails->getMethod();
		}

		/* given hypothetical path: PATH_id_EDIT_id2_EDIT__SAME__OKJh_optionalO_TOMP, clean and return a path similar to a real life path; but still in a regex format so optional segments can be indicated as such
		PATH/id/EDIT/id2/EDIT-SAME-OKJ/TOMP
		*/
		private function regexForm(string $routeState):string {

			$segmentDelimiters = ["h" => "-", "u" => "_"];

			$pattern = "(
				(_)?#literal to literal i.e. no placeholder in between
				(?<one_word>
					[A-Z0-9]+# one word match
					(
						(
							_{2}[A-Z0-9]+)+# chain as many uppercase characters
							(?<merge_delimiter>[hu])?# double underscores with uppercase letters ending with any of these will be replaced with their counterparts
					)?# compound word
				)
			)?# literal match
			(
				(?:_)?# path segments delimited by single underscores
				(?<placeholder>
					[a-z0-9]+
					(?<is_optional>[O])?
				)
				_?# possible trailing slash before next literal
			)?";

			return preg_replace_callback("/$pattern/x", function ($matches) use ( $segmentDelimiters) {

				$builder = "";
				
				if ($default = @$matches["one_word"]) {

					if ($delimiter = @$matches["merge_delimiter"])

						$default = implode(
							$segmentDelimiters[$delimiter], explode(
								"__", rtrim($default, $delimiter) // trailing "h"
							)
						);

					$builder .=  "$default\/"; // the slash here is probably unrequired since the recursive loop adds that already
				}
				$wordPattern = "[a-z0-9]+?\/";

				$hasPlaceholder = @$matches["placeholder"];

				if ($maybe = @$matches["is_optional"]) {

					$hasPlaceholder = rtrim($hasPlaceholder, "O");

					$builder .= "($wordPattern)?";
				}
				elseif ($hasPlaceholder) $builder .= $wordPattern;

				return $builder;
			}, $routeState);
		}

		private function prefixMatch (string $newRouteState):bool {
			
			return preg_match("/^$newRouteState
				?# neutralize trailing slash in replaced path
				/ix", $this->requestDetails->getPath());
		}

		public function setPrevious(AbstractRenderer $renderer , BaseRequest $request):self {

			$_SESSION[self::PREV_RENDERER] = $renderer;

			$_SESSION[self::PREV_REQUEST] = $request;

			return $this;
		}

		public function getPreviousRenderer ():AbstractRenderer {

			return $_SESSION[self::PREV_RENDERER];
		}

		public function getPreviousRequest ():BaseRequest {

			return $_SESSION[self::PREV_REQUEST];
		}

		public function getActiveRenderer ():AbstractRenderer {

			return $this->activeRenderer;
		}

		public function setActiveRenderer (AbstractRenderer $renderer):self {

			$this->activeRenderer = $renderer;

			return $this;
		}

		// @return Strings[]
		private function entryRouteMap():array {
			
			if ($this->requestDetails->isApiRoute()) {

				$this->requestDetails->stripApiPrefix();

				return $this->requestDetails->apiVersionClasses();
			}

			return [$this->config->browserEntryRoute()];
		}

		public function acceptsJson():bool {

			foreach (getallheaders() as $key => $value) {
				
				if (strtolower($key) == "accept" && preg_match("/application\/json/i", $value))

					return true;
			}
			return false;
		}

		private function bootRenderer(AbstractRenderer $renderer, string $controllingClass):AbstractRenderer {

			$dependencyMethod = "setDependencies";

			$parameters = $this->provideRendererDependencies($renderer::class, $controllingClass)
			
			->getMethodParameters($dependencyMethod, $renderer::class);

			return call_user_func_array([$renderer, $dependencyMethod], $parameters);
		}

		private function getCollectionArguments():array {

			return [
				"permissions" => $this->container

				->getClass($this->config->routePermissions()),
				
				"browserEntry" => $this->config->browserEntryRoute()
			];
		}

		private function provideRendererDependencies(string $renderer, string $controller):Container {

			return $this->container->whenType($renderer)

			->needsArguments([
				"controllerClass" => $controller
			]);
		}
	}
?>