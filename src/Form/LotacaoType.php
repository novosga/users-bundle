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

use Novosga\Entity\LotacaoInterface;
use Novosga\Entity\PerfilInterface;
use Novosga\Entity\UnidadeInterface;
use Novosga\Entity\UsuarioInterface;
use Novosga\Repository\PerfilRepositoryInterface;
use Novosga\Repository\UnidadeRepositoryInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class LotacaoType extends AbstractType
{
    public function __construct(
        private readonly UnidadeRepositoryInterface $unidadeRepository,
        private readonly PerfilRepositoryInterface $perfilRepository,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $ignoreList = (array) $options['ignore'];
        $usuario = $options['usuario'];
        $unidadesUsuario = $this->unidadeRepository->findByUsuario($usuario);
        $unidadesDisponiveis = array_values(array_filter(
            $unidadesUsuario,
            fn (UnidadeInterface $unidade) => !in_array($unidade->getId(), $ignoreList),
        ));

        $builder
            ->add('unidade', ChoiceType::class, [
                'placeholder' => '',
                'choice_value' => fn (?UnidadeInterface $value) => $value?->getId(),
                'choice_label' => fn (?UnidadeInterface $value) => $value?->getNome(),
                'choices' => $unidadesDisponiveis,
                'label' => 'form.lotacao.unidade',
            ])
            ->add('perfil', ChoiceType::class, [
                'placeholder' => '',
                'choice_value' => fn (?PerfilInterface $value) => $value?->getId(),
                'choice_label' => fn (?PerfilInterface $value) => $value?->getNome(),
                'choices' => $this->perfilRepository->findAll(),
                'label' => 'form.lotacao.perfil',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setDefaults([
                'data_class' => LotacaoInterface::class,
                'translation_domain' => 'NovosgaUsersBundle',
            ])
            ->setRequired(['usuario', 'ignore'])
            ->setAllowedTypes('ignore', [ 'array' ])
            ->setAllowedTypes('usuario', [ UsuarioInterface::class ]);
    }
}
