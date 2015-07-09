<?php
namespace PHPCrystal\PHPCrystal;

use PHPCrystal\PHPCrystal\Component\Filesystem\PathResolver;
use PHPCrystal\PHPCrystal\Facade as Facade;

$this->set('env', 'prod');

$this->addPathAlias('cache', '@app/cache');
$this->addPathAlias('web', '@app/public_html');
$this->addPathAlias('template', '@app/resources/template');
$this->addPathAlias('tmp', '@app/tmp');

$this->openSection('app');
	$this->set('hostname', 'locahost');
$this->closeSection();


$this->openSection('phpcrystal.core');

	$this->set('twig.debug', true);
	$this->set('twig.auto_reaload', true);
	$this->set('twig.cache', PathResolver::create('@cache'));
	$this->set('twig.templates', PathResolver::create('@template'));
	$this->set('twig.autoescape', true);

$this->closeSection();

// Default session settings
$this->serviceSection('phpcrystal.session');
	$this->set('storage', Facade\Filesystem::create());
	// if set to true does not accept uninitialized session ID
	$this->set('use_strict_mode', true);
	$this->set('use_trans_sid', false);
	$this->set('save_path', sys_get_temp_dir());
	$this->set('name', 'SID');
	$this->set('cookie_lifetime', 0);
	$this->set('cookie_path', '/');
	$this->set('cookie_domain', null);
	$this->set('cookie_httponly', true);
	$this->set('auto_start', true);
	$this->set('gc_probability', 1);
	$this->set('gc_divisor', 100);
	$this->set('gc_maxlifetime', 3600);
$this->closeSection();

$this->openSection('phpcrystal.core.cache');

	$this->set('driver', Facade\Memcached::create())
		->addServer('localhost')
		->setOption(\Memcached::OPT_LIBKETAMA_COMPATIBLE, true)
	;

$this->closeSection();

// Database common settings.
$this->openSection('phpcrystal.core.database');

	$this->set('driver', 'pdo_mysql');
	$this->set('user', 'root');
	$this->set('password', '');
	$this->set('dbname', '');
	$this->set('charset', 'UTF8');

$this->closeSection();

// Doctrine ORM default setup
$this->openSection('phpcrystal.core.doctrine');

	$this->set('proxyDir', PathResolver::create('@cache/doctrine/proxy')); // directory for proxy class files
	$this->set('proxyNamespace', 'Model\\Doctrine\\Proxy\\');
	$this->set('modelNamespace', 'Model\\Doctrine\\');
	$this->set('modelPaths', [PathResolver::create('@app/Model/Physical')]);
	$this->set('entityPaths', [PathResolver::create('@app/Model/Physical/Entity')]);
	$this->set('dbal.autocommit', false);
	
$this->closeSection();


