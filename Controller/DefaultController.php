<?php

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
use Novosga\Entity\Lotacao;
use Novosga\Entity\Perfil;
use Novosga\Entity\Unidade;
use Novosga\Entity\Usuario;
use Novosga\Entity\Usuario as Entity;
use Novosga\Http\Envelope;
use Novosga\UsersBundle\Form\LotacaoType;
use Novosga\UsersBundle\Form\UsuarioType as EntityType;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

/**
 * UsuariosController.
 *
 * @author Rogerio Lino <rogeriolino@gmail.com>
 */
class DefaultController extends AbstractController
{
    const DOMAIN = 'NovosgaUsersBundle';

    private $passwordEncoder;

    public function __construct(UserPasswordEncoderInterface $passwordEncoder)
    {
        $this->passwordEncoder = $passwordEncoder;
    }
    
    /**
     * @param Request $request
     * @return Response
     *
     * @Route("/", name="novosga_users_index", methods={"GET"})
     */
    public function index(Request $request)
    {
        $search  = $request->get('q');
        $usuario = $this->getUser();
        $unidade = $usuario->getLotacao()->getUnidade();
       
        $qb = $this
            ->getDoctrine()
            ->getManager()
            ->createQueryBuilder()
            ->select('e')
            ->from(Entity::class, 'e');
        
        $params = [];
        
        if (!$usuario->isAdmin()) {
            $qb
                ->join('e.lotacoes', 'l')
                ->where('l.unidade = :unidade')
                ->andWhere('e.admin = FALSE');
       
            $params['unidade'] = $unidade;
        }
        
        if (!empty($search)) {
            $where = [
                '(UPPER(e.login) LIKE UPPER(:login))',
            ];
            $params['login'] = "%{$search}%";
            
            $tokens = explode(' ', $search);
            
            for ($i = 0; $i < count($tokens); $i++) {
                $value = $tokens[$i];
                $v1 = "n{$i}";
                $v2 = "s{$i}";
                
                $where[] = "(UPPER(e.nome) LIKE UPPER(:{$v1}))";
                $where[] = "(UPPER(e.sobrenome) LIKE UPPER(:{$v2}))";
                
                $params[$v1] = "{$value}%";
                $params[$v2] = "%{$value}%";
            }
            
            $qb->andWhere(join(' OR ', $where));
        }
        
        $query = $qb
                ->setParameters($params)
                ->getQuery();
        
        $currentPage = max(1, (int) $request->get('p'));
        
        $adapter    = new \Pagerfanta\Adapter\DoctrineORMAdapter($query);
        $pagerfanta = new \Pagerfanta\Pagerfanta($adapter);
        $view       = new \Pagerfanta\View\TwitterBootstrap4View();
        
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
    
    /**
     *
     * @param Request $request
     * @param int $id
     * @return Response
     *
     * @Route("/new", name="novosga_users_new", methods={"GET", "POST"})
     * @Route("/{id}/edit", name="novosga_users_edit", methods={"GET", "POST"})
     */
    public function form(Request $request, TranslatorInterface $translator, Entity $entity = null)
    {
        if (!$entity) {
            $entity = new Entity();
        }
        
        $em          = $this->getDoctrine()->getManager();
        $currentUser = $this->getUser();
        $unidades    = $em->getRepository(Unidade::class)->findByUsuario($currentUser);
        $isAdmin     = $currentUser->isAdmin();
        
        $form = $this
            ->createForm(EntityType::class, $entity, [
                'admin' => $isAdmin,
            ])
            ->handleRequest($request);
        
        if (!$isAdmin && $entity->getId()) {
            $lotacoes = $entity->getLotacoes()->toArray();

            $existe = false;

            foreach ($lotacoes as $lotacao) {
                if (in_array($lotacao->getUnidade(), $unidades)) {
                    $existe = true;
                    break;
                }
            }

            if (!$existe) {
                $error = $translator->trans('error.permission_denied', [], self::DOMAIN);
                throw new Exception($error);
            }
        }
        
        $lotacoesRemovidas = [];
        
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                /* @var $unidadesRemovidas Lotacao[] */
                $unidadesRemovidas = explode(',', $form->get('lotacoesRemovidas')->getData());

                if (is_array($unidadesRemovidas) && count($unidadesRemovidas)) {
                    foreach ($unidadesRemovidas as $lotacaoId) {
                        foreach ($entity->getLotacoes() as $lotacao) {
                            if ($lotacao->getId() === ((int) $lotacaoId)) {
                                if (!$isAdmin && !in_array($lotacao->getUnidade(), $unidades)) {
                                    $error = $translator->trans('error.remove_lotation_permission_denied', [
                                        '%unidade%' => $lotacao->getUnidade(),
                                    ], self::DOMAIN);
                                    
                                    throw new Exception($error);
                                }
                                $lotacoesRemovidas[] = $lotacao;
                                $entity->getLotacoes()->removeElement($lotacao);
                            }
                        }
                    }
                }
                
                $novasUnidades = $request->get('novasUnidades');
                $novosPerfis   = $request->get('novosPerfis');

                if (is_array($novasUnidades) && count($novasUnidades) && count($novosPerfis)) {
                    for ($i = 0; $i < count($novasUnidades); $i++) {
                        $unidade = $em->find(Unidade::class, $novasUnidades[$i]);
                        $perfil  = $em->find(Perfil::class, $novosPerfis[$i]);

                        if ($unidade && $perfil) {
                            if (!$isAdmin && !in_array($unidade, $unidades)) {
                                $error = $translator->trans('error.add_lotation_permission_denied', [
                                    '%unidade%' => $lotacao->getUnidade(),
                                ], self::DOMAIN);
                                
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
                                $lotacao = new Lotacao();
                                $lotacao->setUnidade($unidade);
                                $lotacao->setUsuario($entity);
                            }
                            
                            $lotacao->setPerfil($perfil);
                            $entity->getLotacoes()->add($lotacao);
                        }
                    }
                }

                if (!count($entity->getLotacoes())) {
                    throw new Exception($translator->trans('error.no_lotation', [], self::DOMAIN));
                }
                
                // somente uma lotacao por unidade
                $unidadesMap = [];
                foreach ($entity->getLotacoes() as $lotacao) {
                    if (isset($unidadesMap[$lotacao->getUnidade()->getId()])) {
                        throw new Exception($translator->trans('error.more_than_one_lotation', [], self::DOMAIN));
                    }
                    $unidadesMap[$lotacao->getUnidade()->getId()] = true;
                }
                
                $isNew = !$entity->getId();

                if ($isNew) {
                    $entity->setAlgorithm('bcrypt');
                    $entity->setSalt(null);

                    $encoded = $this->passwordEncoder->encodePassword(
                        $entity,
                        $form->get('senha')->getData()
                    );

                    $entity->setSenha($encoded);
                    $entity->setAtivo(true);
                    $entity->setAdmin(false);
                    
                    $em->persist($entity);
                } else {
                    $em->merge($entity);
                }
                
                $em->flush();
                
                if (!$isNew) {
                    $lotacoes = $entity->getLotacoes()->toArray();
                    $lotacao = end($lotacoes);
                    $em->getRepository(Entity::class)->updateUnidade($entity, $lotacao->getUnidade());
                }

                $this->addFlash('success', $translator->trans('label.add_sucess', [], self::DOMAIN));
                
                return $this->redirectToRoute('novosga_users_edit', [ 'id' => $entity->getId() ]);
            } catch (Exception $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }
        
        if ($entity->getId()) {
            $passwordForm = $this->createForm(\Novosga\UsersBundle\Form\ChangePasswordType::class, null, [
                'action' => $this->generateUrl('novosga_users_password', [ 'id' => $entity->getId() ]),
            ]);
        } else {
            $passwordForm = null;
        }
        
        return $this->render('@NovosgaUsers/default/form.html.twig', [
            'entity'            => $entity,
            'unidades'          => $unidades,
            'form'              => $form->createView(),
            'passwordForm'      => $passwordForm ? $passwordForm->createView() : null,
            'lotacoesRemovidas' => $lotacoesRemovidas,
        ]);
    }

    /**
     * @Route("/novalotacao", methods={"GET"})
     */
    public function novaLotacao(Request $request)
    {
        $usuario = $this->getUser();
        $lotacao = new Lotacao();
        
        $ignore = array_filter(explode(',', $request->get('ignore')), function ($id) {
            return $id > 0;
        });
        
        $form = $this->createForm(LotacaoType::class, $lotacao, [
            'usuario' => $usuario,
            'ignore' => $ignore,
        ]);
        
        return $this->render('@NovosgaUsers/default/novaLotacao.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/perfis/{id}", methods={"GET"})
     */
    public function perfis(Request $request, Perfil $perfil)
    {
        $envelope = new Envelope();
        $envelope->setData($perfil);

        return $this->json($envelope);
    }

    /**
     * @Route("/unidades", methods={"GET"})
     */
    public function unidades(Request $request)
    {
        $envelope = new Envelope();
        $user = $this->getUser();
        
        $unidades = $this
            ->getDoctrine()
            ->getManager()
            ->getRepository(Unidade::class)
            ->findByUsuario($user);
        
        $envelope->setData($unidades);

        return $this->json($envelope);
    }

    /**
     * @Route("/password/{id}", name="novosga_users_password", methods={"POST"})
     */
    public function password(Request $request, Entity $user)
    {
        $form = $this
            ->createForm(\Novosga\UsersBundle\Form\ChangePasswordType::class)
            ->handleRequest($request);
        
        $response = [];
        
        if ($form->isSubmitted() && $form->isValid()) {
            $encoded = $this->passwordEncoder->encodePassword(
                $user,
                $form->get('senha')->getData()
            );
            $user->setSenha($encoded);

            $em = $this->getDoctrine()->getManager();
            $em->merge($user);
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
