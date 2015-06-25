<?php

namespace Application\Provider;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Configuration as DBALConfiguration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Configuration as ORMConfiguration;
use Doctrine\ORM\Mapping\Driver\DriverChain;
use Doctrine\ORM\Mapping\Driver\AnnotationDriver;
use Doctrine\ORM\Mapping\Driver\XmlDriver;
use Doctrine\ORM\Mapping\Driver\YamlDriver;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Cache\ApcCache;
use Doctrine\Common\Cache\ArrayCache;
use Doctrine\Common\EventManager;

use Silex\Application;
use Silex\ServiceProviderInterface;

use Symfony\Component\ClassLoader\UniversalClassLoader;

class ORMServiceProvider implements ServiceProviderInterface
{
    //public $autoloader;

	private $app;

    public function register(Application $app)
    {
    	$this->app = $app;
        $dbal = $this->app['db'];

 
 
        if (!$dbal instanceof \Doctrine\DBAL\Connection) 
        {
            throw new \InvalidArgumentException('$app[\'db\'] must be an instance of \Doctrine\DBAL\Connection'); 
        }

       // $this->autoloader = new UniversalClassLoader();

        $this->loadDoctrineConfiguration();
        
        $this->setOrmDefaults();
        $this->loadDoctrineOrm();

        /*if(isset($app['db.orm.class_path'])) {
            $this->autoloader->registerNamespace('Doctrine\\ORM', $app['db.orm.class_path']);
        }*/
    }

    public function boot(Application $app)
    {
    	// Not being used, but interface requires it
    }

    private function loadDoctrineOrm( )
    {
    	$app=$this->app;
        $app['db.orm.em'] = $app->share(function() use($app) 
        {
            return EntityManager::create($app['db'], $app['db.orm.config']);
        });
    }

    private function setOrmDefaults()
    {
    	$app=$this->app;
        $defaults = array(
            'entities' => array(
                array(
                    'type' => 'annotation', 
                    'path' => 'Entity', 
                    'namespace' => 'Entity',
                )
            ),
            'proxies_dir'           => APP_ROOT.'tmp/cache/doctrine/proxy',
            'proxies_namespace'     => 'DoctrineProxy',
            'auto_generate_proxies' => true,
            'cache'                 => new ArrayCache,
        );
        foreach ($defaults as $key => $value)
        {
            if (!isset($app['db.orm.' . $key])) 
            {
                $app['db.orm.'.$key] = $value;
            }
        }
    }



/* 'db.orm.class_path'            => APP_ROOT.'tmp/cache/doctrine/orm-lib',
		    'db.orm.proxies_dir'           => APP_ROOT.'tmp/cache/doctrine/proxy',
		    'db.orm.proxies_namespace'     => 'DoctrineProxy',
		    'db.orm.auto_generate_proxies' => true,
		    'db.orm.entities'              => 
		    [
		    	array(
		        'type'      => 'annotation',
		        'path'      => __DIR__.'/Entity',
		        'namespace' => '\Application\Entity',
				)
		    ],
		    
		    */
		    
		    
		     
    public function loadDoctrineConfiguration()
    {
    	$app=$this->app;
         $app['db.orm.config'] = $app->share(function() use($app) {

            $cache = $app['db.orm.cache'];
            $config = new ORMConfiguration;
            $config->setMetadataCacheImpl($cache);
            $config->setQueryCacheImpl($cache);

            $chain = new DriverChain;
            foreach((array)$app['db.orm.entities'] as $entity) {
                switch($entity['type']) {
                    case 'default':
                    case 'annotation':
                        $driver = $config->newDefaultAnnotationDriver((array)$entity['path']);
                        $chain->addDriver($driver, $entity['namespace']);
                        break;
                    case 'yml':
                        $driver = new YamlDriver((array)$entity['path']);
                        $driver->setFileExtension('.yml');
                        $chain->addDriver($driver, $entity['namespace']);
                        break;
                    case 'xml':
                        $driver = new XmlDriver((array)$entity['path'], $entity['namespace']);
                        $driver->setFileExtension('.xml');
                        $chain->addDriver($driver, $entity['namespace']);
                        break;
                    default:
                        throw new \InvalidArgumentException(sprintf('"%s" is not a recognized driver', $entity['type']));
                        break;
                }
                //$self->autoloader->registerNamespace($entity['namespace'], $entity['path']);
            }
            $config->setMetadataDriverImpl($chain);

            $config->setProxyDir($app['db.orm.proxies_dir']);
            $config->setProxyNamespace($app['db.orm.proxies_namespace']);
            $config->setAutoGenerateProxyClasses($app['db.orm.auto_generate_proxies']);

            return $config;
        });
    }
}
