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

use Doctrine\ORM\EntityRepository;
use Novosga\Entity\Perfil;
use Novosga\Entity\Lotacao;
use Novosga\Entity\Unidade;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Mangati\BaseBundle\Form\Type\EntityTextType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;


class LotacaoType extends AbstractType
{
    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('unidade', EntityType::class, [
                'class' => Unidade::class,
                'placeholder' => '',
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('e')
                            ->orderBy('e.nome', 'ASC');
                }
            ])
            ->add('perfil', EntityType::class, [
                'class' => Perfil::class,
                'placeholder' => '',
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('e')
                            ->orderBy('e.nome', 'ASC');
                }
            ])
        ;
    }
    
    /**
     * 
     * @param OptionsResolver $resolver
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver
            ->setDefaults([
                'data_class' => Lotacao::class
            ]);
    }
}
