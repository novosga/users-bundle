<?php

/*
 * This file is part of the Novo SGA project.
 *
 * (c) Rogerio Lino <rogeriolino@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Novosga\UsersBundle\Form;

use Novosga\Entity\Usuario;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class UsuarioType extends AbstractType
{
    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $entity  = $options['data'];
        $isAdmin = $options['admin'];
        
        $builder
            ->add('login', TextType::class, [
                'attr' => [
                    'maxlength' => 30,
                    'oninput' => "this.value = this.value.replace(/([^\w\d\.])+/g, '')",
                ],
                'constraints' => [
                    new NotBlank(),
                    new Length([ 'min' => 3, 'max' => 30 ]),
                    new Regex("/^[a-zA-Z0-9\.]+$/"),
                ],
                'label' => 'form.user.username',
            ])
            ->add('nome', TextType::class, [
                'constraints' => [
                    new NotBlank(),
                    new Length([ 'min' => 3, 'max' => 20 ]),
                ],
                'label' => 'form.user.name',
            ])
            ->add('email', EmailType::class, [
                'required' => false,
                'constraints' => [
                    new Email(),
                ],
                'label' => 'form.user.email',
            ])
            ->add('sobrenome', TextType::class, [
                'constraints' => [
                    new NotNull(),
                    new Length([ 'max' => 100 ]),
                ],
                'label' => 'form.user.lastname',
            ])
            ->add('lotacoesRemovidas', HiddenType::class, [
                'mapped' => false,
                'required' => false,
            ]);

        if ($isAdmin) {
            $builder->add('admin', CheckboxType::class, [
                'required' => false,
                'label' => 'form.user.admin',
            ]);
        }
        
        if ($entity->getId()) {
            $builder->add('ativo', CheckboxType::class, [
                'required' => false,
                'constraints' => [
                    new NotNull(),
                ],
                'label' => 'form.user.active',
            ]);
        } else {
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
                            $form        = $context->getRoot();
                            $senha       = $form->get('senha');
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
    }
    
    /**
     *
     * @param OptionsResolver $resolver
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver
            ->setDefaults([
                'data_class' => Usuario::class,
                'translation_domain' => 'NovosgaUsersBundle',
            ])
            ->setRequired('admin');
    }
}
