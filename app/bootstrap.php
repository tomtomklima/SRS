<?php

use \Nette\Application\Routers\Route;
use \Nette\Diagnostics\Debugger;


SetLocale(LC_ALL, "Czech");

// Load Nette Framework or autoloader generated by Composer
require LIBS_DIR . '/autoload.php';

Debugger::timer();
// Configure application
$configurator = new \Nette\Config\Configurator;


\Nella\Console\Config\Extension::register($configurator);
\Nella\Doctrine\Config\Extension::register($configurator);
\Nella\Doctrine\Config\MigrationsExtension::register($configurator);
$t = new \JMS\Serializer\Annotation\Type(array('value' => 'string')); //TRIK, serializer z neznameho duvodu nelze autoloadovat
$n = new \JMS\Serializer\Annotation\SerializedName(array('value' => 'neco'));
$e = new \JMS\Serializer\Annotation\Exclude();
$e = new \JMS\Serializer\Annotation\ExclusionPolicy(array('value' => 'all'));

// Enable Nette Debugger for error visualisation & logging
//$configurator->setDebugMode($configurator::AUTO);


// Enable RobotLoader - this will load all classes automatically
$configurator->setTempDirectory(__DIR__ . '/../temp');
$configurator->createRobotLoader()
	->addDirectory(APP_DIR)
	->addDirectory(LOCAL_LIBS_DIR)
	->register();


$config = \Nette\Utils\Neon::decode(file_get_contents(__DIR__ . '/config/config.neon'));
$isDebug = $config['common']['parameters']['debug'];

if ($isDebug) {
    $configurator->setDebugMode();
    $configurator->enableDebugger(__DIR__ . '/../log');
}
else {
    \Nette\Diagnostics\Debugger::$logDirectory = __DIR__ . '/../log';
    $configurator->setDebugMode($configurator::NONE);
}
$environment = $isDebug == true ? 'development': 'production';


$configurator->addConfig(__DIR__ . '/config/config.neon', $environment);



if (PHP_SAPI == 'cli') {
    $configurator->addConfig(__DIR__ . '/config/config.neon', 'console');
}
$container = $configurator->createContainer();

//ProgramFactory::createBlockDataForTests($container->database);


// Setup router
$container->router[] = new Route('index.php', 'Front:Homepage:default', Route::ONE_WAY);
//$container->router[] = new Route('admin/', 'Back:Dashboard:default');

$container->router[] = new Route('admin/<presenter>/<action>/<id>/<area>', array(
    'module' => 'Back',
    'presenter' => 'Dashboard',
    'action' => 'default',
    'id' => null,
    'area' => null
));

$container->router[] = new Route('install/<presenter>/<action>/<id>/', array(
    'module' => 'Install',
    'presenter' => 'Install',
    'action' => 'default',
    'id' => null
));
$container->router[] = new Route('login/', 'Auth:login');
$container->router[] = new Route('logout/', 'Auth:logout');

$pageRepo = $container->database->getRepository('\SRS\Model\CMS\Page');
$container->router[] = new Route('[!<pageId [a-z-]+>]', array(
    'module' => 'Front',
    'presenter' => 'Page',
    'action' => 'default',
    'pageId' => array(
        Route::FILTER_IN => callback($pageRepo, 'slugToId'),
        Route::FILTER_OUT => callback($pageRepo, "idToSlug")
    )
));

$container->router[] = new Route('<presenter>/<action>[/<id>]', 'Front:Homepage:default');



//
//if (PHP_SAPI != 'cli') {
//    $acl = new \SRS\Security\Acl($container->database);
//    $container->user->setAuthorizator($acl);
//}

\Nette\Diagnostics\Debugger::barDump(Debugger::timer(), 'before application run');
// Configure and run the application!
//$container->application->catchExceptions = false;
if (!defined('CANCEL_START_APP')) {
    $container->application->run();
}
\Nette\Diagnostics\Debugger::barDump(Debugger::timer(), 'po aplikace run');
