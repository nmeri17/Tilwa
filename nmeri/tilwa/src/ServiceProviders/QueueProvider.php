<?php

	namespace Tilwa\ServiceProviders;

	use Tilwa\App\{Bootstrap, ServiceProvider};

	use Illuminate\Queue\Capsule\Manager as QueueManager;

	class QueueProvider extends ServiceProvider {

		public function afterBind(QueueManager $initialized):void {

			$initialized->addConnection([
			    'driver' => 'beanstalkd',
			    'host' => 'localhost',
			    'queue' => 'default',
			]);
		}

		public function concrete():string {

			return QueueManager::class;
		}
	}
?>