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

namespace Novosga\UsersBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class ChangePasswordType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('senha', PasswordType::class, [
                'mapped' => false,
                'constraints' => [
                    new NotNull(),
                    new Length([ 'min' => 6 ]),
                ],
                'label' => 'form.user.password',
            ])
            ->add('confirmacaoSenha', PasswordType::class, [
                'mapped' => false,
                'constraints' => [
                    new Length([ 'min' => 6 ]),
                    new Callback(function ($object, ExecutionContextInterface $context, $payload) {
                        $form = $context->getRoot();
                        $senha = $form->get('senha');
                        $confirmacao = $form->get('confirmacaoSenha');

                        if ($senha->getData() !== $confirmacao->getData()) {
                            $context
                                ->buildViolation('error.password_confirm')
                                ->setTranslationDomain('NovosgaUsersBundle')
                                ->atPath('confirmacaoSenha')
                                ->addViolation();
                        }
                    }),
                ],
                'label' => 'form.user.password_confirm',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'translation_domain' => 'NovosgaUsersBundle',
        ]);
    }

    public function getBlockPrefix(): string
    {
        return '';
    }
}
