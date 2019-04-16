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
        $ignore  = $options['ignore'];
        $usuario = $options['usuario'];
        
        $builder
            ->add('unidade', EntityType::class, [
                'class' => Unidade::class,
                'placeholder' => '',
                'query_builder' => function (EntityRepository $er) use ($usuario, $ignore) {
                    $qb = $er
                        ->createQueryBuilder('e')
                        ->where('e.deletedAt IS NULL')
                        ->orderBy('e.nome', 'ASC');
                            
                    if (!$usuario->isAdmin()) {
                        $qb
                            ->join(Lotacao::class, 'l', 'WITH', 'l.unidade = e')
                            ->andWhere('l.usuario = :usuario')
                            ->andWhere('e.deletedAt IS NULL')
                            ->setParameter('usuario', $usuario);
                    }
                    
                    if (count($ignore)) {
                        $qb
                            ->andWhere('e.id NOT IN (:ignore)')
                            ->setParameter('ignore', $ignore);
                    }
                    
                    return $qb;
                },
                'label' => 'form.lotacao.unidade',
                'translation_domain' => 'NovosgaUsersBundle',
            ])
            ->add('perfil', EntityType::class, [
                'class' => Perfil::class,
                'placeholder' => '',
                'query_builder' => function (EntityRepository $er) {
                    return $er
                        ->createQueryBuilder('e')
                        ->orderBy('e.nome', 'ASC');
                },
                'label' => 'form.lotacao.perfil',
                'translation_domain' => 'NovosgaUsersBundle',
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
            ])
            ->setRequired(['usuario', 'ignore'])
            ->setAllowedTypes('usuario', [\Novosga\Entity\Usuario::class]);
    }
}
