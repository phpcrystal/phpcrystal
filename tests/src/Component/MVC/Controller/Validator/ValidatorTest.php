<?php
namespace PHPCrystal\PHPCrystalTest\Component\MVC\Controller\Validator;

use PHPCrystal\PHPCrystal\Component\MVC\Controller\Validator\AbstractValidator;
use PHPCrystal\PHPCrystal\Component\MVC\Controller\Input\Input;
use PHPCrystal\PHPCrystal\Component\MVC\Controller\Validator\Rule as Rule;

use PHPCrystal\PHPCrystalTest\TestCase;

class ValidatorTest extends TestCase
{
	public function testInput()
	{
		$input = Input::createFromArray(['arr1' => [0 => 'zero', 'arr2' => ['test' => 1]]]);
		$this->assertEquals('zero', $input->get('arr1.0'));
		$this->assertEquals(1, $input->get('arr1.arr2.test'));
		$this->assertNull($input->get('arr1.undefined'));
	}
	
	public function testInputSetter()
	{
		$input = Input::create();
		$input->set('foo.bar', 'baz');
		$this->assertEquals('baz', $input->toArray()['foo']['bar']);
		$input->set('config.db.adapter', 'PDO');
		$this->assertEquals('PDO', $input->toArray()['config']['db']['adapter']);
	}
	
	public function testValidateEmail()
	{
//		$validator = new Validator();
//		$input = Input::create('post', ['email' => 'phpcrystal@gmail.com']);
//		$validator->setInput($input);
//		$result = $validator->run();
//		$this->assertTrue($result);
	}
}
