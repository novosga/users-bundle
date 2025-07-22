<?php

declare(strict_types=1);

/*
 * This file is part of the Novo SGA project.
 *
 * (c) Rogerio Lino <rogeriolino@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Novosga\UsersBundle\Tests\Form;

use Novosga\Entity\UsuarioInterface;
use Novosga\UsersBundle\Form\UsuarioType;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\Form\FormInterface;

class UsuarioTypeTest extends TypeTestCase
{
    public function testSubmitValidData(): void
    {
        $formData = [
            'login' => 'test.user-name_123',
            'nome' => 'Test',
            'sobrenome' => 'User',
            'email' => 'test@example.com',
        ];

        $model = $this->createMockUsuario();
        $form = $this->factory->create(UsuarioType::class, $model, ['admin' => false]);

        $form->submit($formData);

        $this->assertTrue($form->isSynchronized());
        $this->assertTrue($form->isValid());

        $this->assertEquals($formData['login'], $form->get('login')->getData());
        $this->assertEquals($formData['nome'], $form->get('nome')->getData());
        $this->assertEquals($formData['sobrenome'], $form->get('sobrenome')->getData());
        $this->assertEquals($formData['email'], $form->get('email')->getData());
    }

    /**
     * @dataProvider loginValidationDataProvider
     */
    public function testLoginValidation(string $login, bool $shouldBeValid, string $message): void
    {
        $formData = [
            'login' => $login,
            'nome' => 'Test',
            'sobrenome' => 'User',
        ];

        $model = $this->createMockUsuario();
        $form = $this->factory->create(UsuarioType::class, $model, ['admin' => false]);

        $form->submit($formData);

        $this->assertTrue($form->isSynchronized());

        if ($shouldBeValid) {
            $this->assertTrue($form->isValid(), $message);
            $this->assertCount(0, $form->get('login')->getErrors(), $message);
        } else {
            $this->assertFalse($form->isValid(), $message);
            $this->assertGreaterThan(0, $form->get('login')->getErrors()->count(), $message);
        }
    }

    public function loginValidationDataProvider(): array
    {
        return [
            // Valid usernames
            ['username', true, 'Simple username should be valid'],
            ['user123', true, 'Username with numbers should be valid'],
            ['user.name', true, 'Username with periods should be valid'],
            ['user-name', true, 'Username with hyphens should be valid'],
            ['user_name', true, 'Username with underscores should be valid'],
            ['test.user-name_123', true, 'Username with all allowed characters should be valid'],
            ['123', true, 'Username with only numbers should be valid'],
            ['a.b-c_d', true, 'Username with mixed separators should be valid'],

            // Invalid usernames - too short
            ['ab', false, 'Username too short should be invalid'],
            ['a', false, 'Single character username should be invalid'],
            ['', false, 'Empty username should be invalid'],

            // Invalid usernames - too long
            [str_repeat('a', 31), false, 'Username longer than 30 characters should be invalid'],

            // Invalid usernames - invalid characters
            ['user name', false, 'Username with spaces should be invalid'],
            ['user@domain', false, 'Username with @ should be invalid'],
            ['user#hash', false, 'Username with # should be invalid'],
            ['user$money', false, 'Username with $ should be invalid'],
            ['user%percent', false, 'Username with % should be invalid'],
            ['user&and', false, 'Username with & should be invalid'],
            ['user*star', false, 'Username with * should be invalid'],
            ['user+plus', false, 'Username with + should be invalid'],
            ['user=equal', false, 'Username with = should be invalid'],
            ['user?question', false, 'Username with ? should be invalid'],
            ['user!exclamation', false, 'Username with ! should be invalid'],
            ['user(parens)', false, 'Username with parentheses should be invalid'],
            ['user[brackets]', false, 'Username with brackets should be invalid'],
            ['user{braces}', false, 'Username with braces should be invalid'],
            ['user|pipe', false, 'Username with pipe should be invalid'],
            ['user\\backslash', false, 'Username with backslash should be invalid'],
            ['user/slash', false, 'Username with forward slash should be invalid'],
            ['user:colon', false, 'Username with colon should be invalid'],
            ['user;semicolon', false, 'Username with semicolon should be invalid'],
            ['user"quote', false, 'Username with quote should be invalid'],
            ["user'apostrophe", false, 'Username with apostrophe should be invalid'],
            ['user<less', false, 'Username with less than should be invalid'],
            ['user>greater', false, 'Username with greater than should be invalid'],
            ['user,comma', false, 'Username with comma should be invalid'],
            ['user~tilde', false, 'Username with tilde should be invalid'],
            ['user`backtick', false, 'Username with backtick should be invalid'],
        ];
    }

    public function testRequiredFields(): void
    {
        $model = $this->createMockUsuario();
        $form = $this->factory->create(UsuarioType::class, $model, ['admin' => false]);

        $form->submit([]);

        $this->assertTrue($form->isSynchronized());
        $this->assertFalse($form->isValid());

        // Login field should have errors (required)
        $this->assertGreaterThan(0, $form->get('login')->getErrors()->count());
        
        // Nome field should have errors (required)
        $this->assertGreaterThan(0, $form->get('nome')->getErrors()->count());
    }

    public function testEmailValidation(): void
    {
        $formData = [
            'login' => 'testuser',
            'nome' => 'Test',
            'sobrenome' => 'User',
            'email' => 'invalid-email',
        ];

        $model = $this->createMockUsuario();
        $form = $this->factory->create(UsuarioType::class, $model, ['admin' => false]);

        $form->submit($formData);

        $this->assertTrue($form->isSynchronized());
        $this->assertFalse($form->isValid());
        $this->assertGreaterThan(0, $form->get('email')->getErrors()->count());
    }

    public function testAdminFieldWhenAdminOptionIsTrue(): void
    {
        $model = $this->createMockUsuario();
        $form = $this->factory->create(UsuarioType::class, $model, ['admin' => true]);

        $this->assertTrue($form->has('admin'));
    }

    public function testAdminFieldWhenAdminOptionIsFalse(): void
    {
        $model = $this->createMockUsuario();
        $form = $this->factory->create(UsuarioType::class, $model, ['admin' => false]);

        $this->assertFalse($form->has('admin'));
    }

    public function testNewUserHasPasswordField(): void
    {
        $model = $this->createMockUsuario();
        $model->method('getId')->willReturn(null);

        $form = $this->factory->create(UsuarioType::class, $model, ['admin' => false]);

        $this->assertTrue($form->has('senha'));
        $this->assertFalse($form->has('ativo'));
    }

    public function testExistingUserHasAtivoFieldButNotPassword(): void
    {
        $model = $this->createMockUsuario();
        $model->method('getId')->willReturn(123);

        $form = $this->factory->create(UsuarioType::class, $model, ['admin' => false]);

        $this->assertFalse($form->has('senha'));
        $this->assertTrue($form->has('ativo'));
    }

    private function createMockUsuario(): UsuarioInterface
    {
        $mock = $this->createMock(UsuarioInterface::class);
        $mock->method('getId')->willReturn(null);
        
        return $mock;
    }
}