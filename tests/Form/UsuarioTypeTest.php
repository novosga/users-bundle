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
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\FormExtensionInterface;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\Validator\Validation;

class UsuarioTypeTest extends TypeTestCase
{
    /** @return FormExtensionInterface[] */
    protected function getExtensions(): array
    {
        $validator = Validation::createValidator();

        return [
            new ValidatorExtension($validator),
        ];
    }

    public function testSubmitValidData(): void
    {
        $formData = $this->buildFormData();

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
     * @dataProvider validLoginDataProvider
     */
    public function testLoginValidationValidUsernames(string $login, string $message): void
    {
        $formData = $this->buildFormData();
        $formData['login'] = $login;

        $model = $this->createMockUsuario();
        $form = $this->factory->create(UsuarioType::class, $model, ['admin' => false]);

        $form->submit($formData);

        $this->assertTrue($form->isSynchronized());
        $this->assertTrue($form->isValid(), $message);
        $this->assertCount(0, $form->get('login')->getErrors(), $message);
    }

    /**
     * @dataProvider invalidLoginDataProvider
     */
    public function testLoginValidationInvalidUsernames(string $login, string $message): void
    {
        $formData = $this->buildFormData();
        $formData['login'] = $login;

        $model = $this->createMockUsuario();
        $form = $this->factory->create(UsuarioType::class, $model, ['admin' => false]);

        $form->submit($formData);

        $this->assertTrue($form->isSynchronized());
        $this->assertFalse($form->isValid(), $message);
        $this->assertGreaterThan(0, $form->get('login')->getErrors()->count(), $message);
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
        // Mock is already configured to return null for getId() in createMockUsuario()

        $form = $this->factory->create(UsuarioType::class, $model, ['admin' => false]);

        $this->assertTrue($form->has('senha'));
        $this->assertFalse($form->has('ativo'));
    }

    public function testExistingUserHasAtivoFieldButNotPassword(): void
    {
        $model = $this->createMock(UsuarioInterface::class);
        $model->method('getId')->willReturn(123);

        $form = $this->factory->create(UsuarioType::class, $model, ['admin' => false]);

        $this->assertFalse($form->has('senha'));
        $this->assertTrue($form->has('ativo'));
    }

    private function createMockUsuario(): MockObject&UsuarioInterface
    {
        $mock = $this->createMock(UsuarioInterface::class);
        $mock->method('getId')->willReturn(null);

        return $mock;
    }

    /** @return array<string,mixed> */
    private function buildFormData(): array
    {
        return [
            'login' => 'test.user-name_123',
            'nome' => 'Test',
            'sobrenome' => 'User',
            'email' => 'test@example.com',
            'lotacoesRemovidas' => '',
            'senha' => [
                'first' => 'password123',
                'second' => 'password123',
            ],
        ];
    }

    /** @return array<array{string, string}> */
    public function validLoginDataProvider(): array
    {
        return [
            // Valid usernames
            ['username', 'Simple username should be valid'],
            ['user123', 'Username with numbers should be valid'],
            ['user.name', 'Username with periods should be valid'],
            ['user-name', 'Username with hyphens should be valid'],
            ['user_name', 'Username with underscores should be valid'],
            ['test.user-name_123', 'Username with all allowed characters should be valid'],
            ['123', 'Username with only numbers should be valid'],
            ['a.b-c_d', 'Username with mixed separators should be valid'],
        ];
    }

    /** @return array<array{string, string}> */
    public function invalidLoginDataProvider(): array
    {
        return [
            // Invalid usernames - too short
            ['ab', 'Username too short should be invalid'],
            ['a', 'Single character username should be invalid'],
            ['', 'Empty username should be invalid'],

            // Invalid usernames - too long
            [str_repeat('a', 31), 'Username longer than 30 characters should be invalid'],

            // Invalid usernames - invalid characters
            ['user name', 'Username with spaces should be invalid'],
            ['user@domain', 'Username with @ should be invalid'],
            ['user#hash', 'Username with # should be invalid'],
            ['user$money', 'Username with $ should be invalid'],
            ['user%percent', 'Username with % should be invalid'],
            ['user&and', 'Username with & should be invalid'],
            ['user*star', 'Username with * should be invalid'],
            ['user+plus', 'Username with + should be invalid'],
            ['user=equal', 'Username with = should be invalid'],
            ['user?question', 'Username with ? should be invalid'],
            ['user!exclamation', 'Username with ! should be invalid'],
            ['user(parens)', 'Username with parentheses should be invalid'],
            ['user[brackets]', 'Username with brackets should be invalid'],
            ['user{braces}', 'Username with braces should be invalid'],
            ['user|pipe', 'Username with pipe should be invalid'],
            ['user\\backslash', 'Username with backslash should be invalid'],
            ['user/slash', 'Username with forward slash should be invalid'],
            ['user:colon', 'Username with colon should be invalid'],
            ['user;semicolon', 'Username with semicolon should be invalid'],
            ['user"quote', 'Username with quote should be invalid'],
            ["user'apostrophe", 'Username with apostrophe should be invalid'],
            ['user<less', 'Username with less than should be invalid'],
            ['user>greater', 'Username with greater than should be invalid'],
            ['user,comma', 'Username with comma should be invalid'],
            ['user~tilde', 'Username with tilde should be invalid'],
            ['user`backtick', 'Username with backtick should be invalid'],
        ];
    }
}
