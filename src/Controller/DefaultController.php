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

namespace Novosga\UsersBundle\Controller;

use Exception;
use Doctrine\ORM\EntityManagerInterface;
use Novosga\Entity\UsuarioInterface;
use Novosga\Http\Envelope;
use Novosga\Repository\PerfilRepositoryInterface;
use Novosga\Repository\UnidadeRepositoryInterface;
use Novosga\Repository\UsuarioRepositoryInterface;
use Novosga\Service\LotacaoServiceInterface;
use Novosga\Service\UsuarioServiceInterface;
use Novosga\UsersBundle\Form\ChangePasswordType;
use Novosga\UsersBundle\Form\LotacaoType;
use Novosga\UsersBundle\Form\UsuarioType;
use Novosga\UsersBundle\NovosgaUsersBundle;
use Pagerfanta\Doctrine\ORM\QueryAdapter;
use Pagerfanta\Pagerfanta;
use Pagerfanta\View\TwitterBootstrap5View;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * UsuariosController.
 *
 * @author Rogerio Lino <rogeriolino@gmail.com>
 */
#[Route("/", name: "novosga_users_")]
class DefaultController extends AbstractController
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordEncoder,
    ) {
    }

    #[Route("/", name: "index", methods: ['GET'])]
    public function index(
        Request $request,
        UsuarioRepositoryInterface $repository,
    ): Response {
        $search  = $request->get('q');
        /** @var UsuarioInterface */
        $usuario = $this->getUser();
        $unidade = $usuario->getLotacao()->getUnidade();

        $qb = $repository->createQueryBuilder('e');

        if (!$usuario->isAdmin()) {
            $qb
                ->join('e.lotacoes', 'l')
                ->where('l.unidade = :unidade')
                ->andWhere('e.admin = FALSE')
                ->setParameter('unidade', $unidade);
        }

        if (!empty($search)) {
            $where = [
                '(UPPER(e.login) LIKE UPPER(:login))',
            ];
            $qb->setParameter('login', "%{$search}%");

            $tokens = explode(' ', $search);

            for ($i = 0; $i < count($tokens); $i++) {
                $value = $tokens[$i];
                $v1 = "n{$i}";
                $v2 = "s{$i}";

                $where[] = "(UPPER(e.nome) LIKE UPPER(:{$v1}))";
                $where[] = "(UPPER(e.sobrenome) LIKE UPPER(:{$v2}))";

                $qb->setParameter($v1, "%{$value}%");
                $qb->setParameter($v2, "%{$value}%");
            }

            $qb->andWhere(join(' OR ', $where));
        }

        $query = $qb->getQuery();

        $currentPage = max(1, (int) $request->get('p'));

        $adapter    = new QueryAdapter($query);
        $view       = new TwitterBootstrap5View();
        $pagerfanta = new Pagerfanta($adapter);

        $pagerfanta->setCurrentPage($currentPage);

        $path = $this->generateUrl('novosga_users_index');
        $html = $view->render(
            $pagerfanta,
            function ($page) use ($request, $path) {
                $q = $request->get('q');
                return "{$path}?q={$q}&p={$page}";
            },
            [
                'proximity' => 3,
                'prev_message' => '←',
                'next_message' => '→',
            ]
        );

        $usuarios = $pagerfanta->getCurrentPageResults();

        return $this->render('@NovosgaUsers/default/index.html.twig', [
            'usuarios' => $usuarios,
            'paginacao' => $html,
        ]);
    }

    #[Route("/new", name: "new", methods: ["GET", "POST"])]
    public function add(
        Request $request,
        EntityManagerInterface $em,
        PerfilRepositoryInterface $perfilRepository,
        UnidadeRepositoryInterface $unidadeRepository,
        UsuarioServiceInterface $usuarioService,
        LotacaoServiceInterface $lotacaoService,
        TranslatorInterface $translator,
    ): Response {
        $entity = $usuarioService->build();

        return $this->form(
            $request,
            $em,
            $perfilRepository,
            $unidadeRepository,
            $usuarioService,
            $lotacaoService,
            $translator,
            $entity,
        );
    }

    #[Route("/{id}/edit", name: "edit", methods: ["GET", "POST"])]
    public function edit(
        Request $request,
        EntityManagerInterface $em,
        PerfilRepositoryInterface $perfilRepository,
        UnidadeRepositoryInterface $unidadeRepository,
        UsuarioServiceInterface $usuarioService,
        LotacaoServiceInterface $lotacaoService,
        TranslatorInterface $translator,
        int $id,
    ): Response {
        $entity = $usuarioService->getById($id);
        if (!$entity) {
            throw $this->createNotFoundException();
        }

        return $this->form(
            $request,
            $em,
            $perfilRepository,
            $unidadeRepository,
            $usuarioService,
            $lotacaoService,
            $translator,
            $entity,
        );
    }

    private function form(
        Request $request,
        EntityManagerInterface $em,
        PerfilRepositoryInterface $perfilRepository,
        UnidadeRepositoryInterface $unidadeRepository,
        UsuarioServiceInterface $usuarioService,
        LotacaoServiceInterface $lotacaoService,
        TranslatorInterface $translator,
        UsuarioInterface $entity,
    ): Response {
        /** @var UsuarioInterface */
        $currentUser = $this->getUser();
        $unidade = $currentUser->getLotacao()->getUnidade();
        $unidades = $unidadeRepository->findByUsuario($currentUser);
        $isAdmin = $currentUser->isAdmin();

        $form = $this
            ->createForm(UsuarioType::class, $entity, [
                'admin' => $isAdmin,
            ])
            ->handleRequest($request);

        if (!$isAdmin && $entity->getId()) {
            $existe = false;
            $lotacoes = $entity->getLotacoes()->toArray();
            foreach ($lotacoes as $lotacao) {
                if (in_array($lotacao->getUnidade(), $unidades)) {
                    $existe = true;
                    break;
                }
            }
            if (!$existe) {
                $error = $translator->trans('error.permission_denied', [], NovosgaUsersBundle::getDomain());
                throw new Exception($error);
            }
        }

        $lotacoesRemovidas = [];
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                /** @var int[] */
                $unidadesRemovidas = explode(',', $form->get('lotacoesRemovidas')->getData() ?? '');

                if (is_array($unidadesRemovidas) && count($unidadesRemovidas)) {
                    foreach ($unidadesRemovidas as $lotacaoId) {
                        foreach ($entity->getLotacoes() as $lotacao) {
                            if ($lotacao->getId() === (int) $lotacaoId) {
                                if (!$isAdmin && !in_array($lotacao->getUnidade(), $unidades)) {
                                    $error = $translator->trans('error.remove_lotation_permission_denied', [
                                        '%unidade%' => $lotacao->getUnidade(),
                                    ], NovosgaUsersBundle::getDomain());

                                    throw new Exception($error);
                                }
                                $lotacoesRemovidas[] = $lotacao;
                                $entity->getLotacoes()->removeElement($lotacao);
                            }
                        }
                    }
                }

                $novasUnidades = $request->get('novasUnidades');
                $novosPerfis = $request->get('novosPerfis');

                if (is_array($novasUnidades) && count($novasUnidades) && count($novosPerfis)) {
                    for ($i = 0; $i < count($novasUnidades); $i++) {
                        $unidade = $unidadeRepository->find($novasUnidades[$i]);
                        $perfil  = $perfilRepository->find($novosPerfis[$i]);

                        if ($unidade && $perfil) {
                            if (!$isAdmin && !in_array($unidade, $unidades)) {
                                $error = $translator->trans(
                                    'error.add_lotation_permission_denied',
                                    [ '%unidade%' => $unidade ],
                                    NovosgaUsersBundle::getDomain(),
                                );
                                throw new Exception($error);
                            }

                            $lotacao = null;

                            // tenta reaproveitar uma lotacao da mesma unidade
                            foreach ($lotacoesRemovidas as $l) {
                                if ($l->getUnidade()->getId() === $unidade->getId()) {
                                    $lotacao = $l;
                                    break;
                                }
                            }

                            if (!$lotacao) {
                                $lotacao = $lotacaoService
                                    ->build()
                                    ->setUnidade($unidade)
                                    ->setUsuario($entity);
                            }

                            $lotacao->setPerfil($perfil);
                            $entity->getLotacoes()->add($lotacao);
                        }
                    }
                }

                if (!$entity->isAdmin() && !count($entity->getLotacoes())) {
                    throw new Exception($translator->trans('error.no_lotation', [], NovosgaUsersBundle::getDomain()));
                }

                // somente uma lotacao por unidade
                $unidadesMap = [];
                foreach ($entity->getLotacoes() as $lotacao) {
                    if (isset($unidadesMap[$lotacao->getUnidade()->getId()])) {
                        throw new Exception($translator->trans(
                            'error.more_than_one_lotation',
                            [],
                            NovosgaUsersBundle::getDomain(),
                        ));
                    }
                    $unidadesMap[$lotacao->getUnidade()->getId()] = true;
                }

                $isNew = !$entity->getId();

                if ($isNew) {
                    $encoded = $this->passwordEncoder->hashPassword(
                        $entity,
                        $form->get('senha')->getData(),
                    );

                    $entity
                        ->setSenha($encoded)
                        ->setAtivo(true);
                }

                $em->persist($entity);
                $em->flush();

                if (!$isNew) {
                    $lotacoes = $entity->getLotacoes()->toArray();
                    $lotacao = end($lotacoes);
                    $usuarioService->meta(
                        $entity,
                        UsuarioServiceInterface::ATTR_SESSION_UNIDADE,
                        $unidade->getId(),
                    );
                }

                $this->addFlash('success', $translator->trans(
                    'label.add_success',
                    [],
                    NovosgaUsersBundle::getDomain(),
                ));

                return $this->redirectToRoute('novosga_users_edit', [ 'id' => $entity->getId() ]);
            } catch (Exception $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        if ($entity->getId()) {
            $passwordForm = $this->createForm(ChangePasswordType::class, null, [
                'action' => $this->generateUrl('novosga_users_password', [ 'id' => $entity->getId() ]),
            ]);
        } else {
            $passwordForm = null;
        }

        return $this->render('@NovosgaUsers/default/form.html.twig', [
            'entity'            => $entity,
            'unidades'          => $unidades,
            'form'              => $form,
            'passwordForm'      => $passwordForm,
            'lotacoesRemovidas' => $lotacoesRemovidas,
        ]);
    }

    #[Route("/novalotacao", methods: ["GET"])]
    public function novaLotacao(Request $request, LotacaoServiceInterface $lotacaoService): Response
    {
        $usuario = $this->getUser();
        $lotacao = $lotacaoService->build();

        $ignore = array_filter(explode(',', $request->get('ignore')), function ($id) {
            return $id > 0;
        });

        $form = $this->createForm(LotacaoType::class, $lotacao, [
            'usuario' => $usuario,
            'ignore' => $ignore,
        ]);

        return $this->render('@NovosgaUsers/default/novaLotacao.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route("/perfis/{id}", methods: ["GET"])]
    public function perfis(int $id, PerfilRepositoryInterface $perfilRepository): Response
    {
        $perfil = $perfilRepository->find($id);
        $envelope = new Envelope();
        $envelope->setData($perfil);

        return $this->json($envelope);
    }

    #[Route("/unidades", methods: ["GET"])]
    public function unidades(UnidadeRepositoryInterface $unidadeRepository): Response
    {
        $envelope = new Envelope();
        /** @var UsuarioInterface */
        $user = $this->getUser();
        $unidades = $unidadeRepository->findByUsuario($user);

        $envelope->setData($unidades);

        return $this->json($envelope);
    }

    #[Route("/password/{id}", name: 'password', methods: ["POST"])]
    public function password(
        Request $request,
        EntityManagerInterface $em,
        UsuarioServiceInterface $usuarioService,
        int $id
    ): Response {
        $usuario = $usuarioService->getById($id);
        if (!$usuario) {
            throw $this->createNotFoundException();
        }

        $form = $this
            ->createForm(ChangePasswordType::class)
            ->handleRequest($request);

        $response = [];

        if ($form->isSubmitted() && $form->isValid()) {
            $encoded = $this->passwordEncoder->hashPassword(
                $usuario,
                $form->get('senha')->getData()
            );
            $usuario->setSenha($encoded);

            $em->persist($usuario);
            $em->flush();
        } else {
            $errors = $form->getErrors(true);
            if (count($errors)) {
                $response['error'] = true;
                $response['errors'] = [];
                foreach ($errors as $error) {
                    $response['errors'][$error->getOrigin()->getName()] = $error->getMessage();
                }
            }
        }

        $envelope = new Envelope();
        $envelope->setData($response);

        return $this->json($envelope);
    }
}
