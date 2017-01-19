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
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Valid;
use Symfony\Component\Validator\Constraints\Count;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Constraints\Email;

class UsuarioType extends AbstractType
{
    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $entity = $options['data'];
        $usuario = $options['usuario'];
        
        $builder
            ->add('login', TextType::class, [
                'label' => 'Nome de usuário',
                'attr' => [
                    'oninput' => "this.value = this.value.replace(/([^\w\d\.])+/g, '')",
                ],
                'constraints' => [
                    new NotBlank(),
                    new Length([ 'min' => 5, 'max' => 20 ]),
                    new Regex("/^[a-zA-Z0-9\.]+$/"),
                ]
            ])
            ->add('nome', TextType::class, [
                'label' => 'Nome',
                'constraints' => [
                    new NotBlank(),
                    new Length([ 'min' => 3, 'max' => 20 ]),
                ]
            ])
            ->add('email', EmailType::class, [
                'label' => 'E-mail',
                'required' => false,
                'constraints' => [
                    new Email(),
                ]
            ])
            ->add('sobrenome', TextType::class, [
                'label' => 'Sobrenome',
                'constraints' => [
                    new NotBlank(),
                    new Length([ 'max' => 100 ]),
                ]
            ])
            ->add('lotacoes', CollectionType::class, [
                'entry_type' => LotacaoType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'error_bubbling' => true,
                'constraints' => [
                    new Valid(),
                    new Count([ 'min' => 1 ]),
                ],
            ]);
        
        if ($entity->getId()) {
            $builder->add('status', CheckboxType::class, [
                'label' => 'Status',
                'required' => false,
                'constraints' => [
                    new NotNull(),
                ]
            ]);
        } else {
            $builder
                ->add('senha', PasswordType::class, [
                    'label' => 'Senha',
                    'constraints' => [

                    ]
                ])
                ->add('confirmacaoSenha', PasswordType::class, [
                    'label' => 'Confirmação da senha',
                    'mapped' => false,
                    'constraints' => [

                    ]
                ]);
            
            $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) {
                $entity = $event->getData();
                $form = $event->getForm();
                $confirmacao = $form->get('confirmacaoSenha');
                
                if ($entity->getSenha() !== $confirmacao->getData()) {
                    $confirmacao->addError(new FormError('A confirmação de senha não confere com a senha.'));
                }
            });
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
                'data_class' => Usuario::class
            ])
            ->setRequired(['usuario'])
            ->setAllowedTypes('usuario', Usuario::class);
    }
}
