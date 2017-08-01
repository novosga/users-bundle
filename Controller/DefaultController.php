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
use Novosga\Entity\Perfil;
use Novosga\Entity\Usuario;
use Novosga\Entity\Unidade;
use Novosga\UsersBundle\Form\UsuarioType;
use Mangati\BaseBundle\Controller\CrudController;
use Mangati\BaseBundle\Event\CrudEvent;
use Mangati\BaseBundle\Event\CrudEvents;
use Novosga\Http\Envelope;
use Symfony\Component\HttpFoundation\Request;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;

/**
 * UsuariosController.
 *
 * @author Rogerio Lino <rogeriolino@gmail.com>
 */
class DefaultController extends CrudController
{
    
    public function __construct()
    {
        parent::__construct(Usuario::class);
    }
   
    /**
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @Route("/", name="novosga_users_index")
     * @Method("GET")
     */
    public function indexAction(Request $request)
    {
        return $this->render('NovosgaUsersBundle:default:index.html.twig');
    }
   
    /**
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @Route("/search.json", name="novosga_users_search")
     */
    public function searchAction(Request $request)
    {
        $search = $request->get('search');
        $searchValue = is_array($search) && isset($search['value']) ? $search['value'] : '';
        
        $usuario = $this->getUser();
        $unidade = $usuario->getLotacao()->getUnidade();
        
        $search = $request->get('search');
        $searchValue = is_array($search) && isset($search['value']) ? $search['value'] : '';
        
        $qb = $this
                ->getDoctrine()
                ->getManager()
                ->createQueryBuilder()
                ->select('e')
                ->from(Usuario::class, 'e')
                ->where('UPPER(e.login) LIKE UPPER(:search) OR UPPER(e.nome) LIKE UPPER(:search)');
        
        $params = [];
                    
        if (!$usuario->isAdmin()) {
            $qb
                ->join('e.lotacoes', 'l')
                ->where('l.unidade = :unidade')
                ->andWhere('e.admin = FALSE');
       
            $params['unidade'] = $unidade;
        }
        
        if (!empty($searchValue)) {
            $where = [
                '(UPPER(e.login) LIKE UPPER(:login))',
            ];
            $params['login'] = "%{$searchValue}%";
            
            $tokens = explode(' ', $searchValue);
            
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
        
        return $this->dataTable($request, $query, false);
    }
    
    
    /**
     *
     * @param Request $request
     * @param int $id
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @Route("/new", name="novosga_users_new")
     * @Route("/edit/{id}", name="novosga_users_edit")
     * @Method({"GET", "POST"})
     * @Route("/edit/{id}", name="novosga_users_edit")
     */
    public function editAction(Request $request, $id = 0)
    {
        $em          = $this->getDoctrine()->getManager();
        $currentUser = $this->getUser();
        $unidades    = $em->getRepository(Unidade::class)->findByUsuario($currentUser);
                
        $this
            ->addEventListener(CrudEvents::FORM_RENDER, function (CrudEvent $event) use ($unidades) {
                $params = $event->getData();
                $params['unidades'] = $unidades;
            })
            ->addEventListener(CrudEvents::PRE_EDIT, function (CrudEvent $event) use ($currentUser, $unidades) {
                if (!$currentUser->isAdmin()) {
                    $usuario  = $event->getData();
                    $lotacoes = $usuario->getLotacoes()->toArray();

                    $existe = false;

                    foreach ($lotacoes as $lotacao) {
                        if (in_array($lotacao->getUnidade(), $unidades)) {
                            $existe = true;
                            break;
                        }
                    }

                    if (!$existe) {
                        throw new Exception('Permissão negada');
                    }
                }
            })
            ->addEventListener(CrudEvents::PRE_SAVE, function (CrudEvent $event) use ($em, $unidades) {
                $form    = $event->getForm();
                $request = $event->getRequest();
                /* @var $usuario Usuario */
                $usuario = $event->getData();
                
                /* @var $unidadesRemovidas \Novosga\Entity\Lotacao[] */
                $unidadesRemovidas = explode(',', $form->get('lotacoesRemovidas')->getData());
                $lotacoesRemovidas = [];

                if (count($unidadesRemovidas)) {
                    foreach ($unidadesRemovidas as $unidadeId) {
                        foreach ($usuario->getLotacoes() as $lotacao) {
                            if ($lotacao->getUnidade()->getId() == $unidadeId) {
                                if (!in_array($lotacao->getUnidade(), $unidades)) {
                                    throw new Exception(sprintf('Você não tem permissão para remover a lotação da unidade %s', $lotacao->getUnidade()));
                                }
                                $lotacoesRemovidas[] = $lotacao;
                                $usuario->getLotacoes()->removeElement($lotacao);
                            }
                        }
                    }
                }
                
                $novasUnidades = $request->get('novasUnidades');
                $novosPerfis   = $request->get('novosPerfis');
                
                if (count($novasUnidades) && count($novosPerfis)) {
                    for ($i = 0; $i < count($novasUnidades); $i++) {
                        $unidade = $em->find(Unidade::class, $novasUnidades[$i]);
                        $perfil  = $em->find(Perfil::class, $novosPerfis[$i]);
                        
                        if ($unidade && $perfil) {
                            if (!in_array($unidade, $unidades)) {
                                throw new Exception(sprintf('Você não tem permissão para adicionar uma lotação da unidade %s', $lotacao->getUnidade()));
                            }
                            
                            $lotacao = new \Novosga\Entity\Lotacao();
                            $lotacao->setPerfil($perfil);
                            $lotacao->setUnidade($unidade);
                            $lotacao->setUsuario($usuario);
                            $usuario->getLotacoes()->add($lotacao);
                        }
                    }
                }
                
                if (!count($usuario->getLotacoes())) {
                    foreach ($lotacoesRemovidas as $lotacao) {
                        $usuario->getLotacoes()->add($lotacao);
                    }
                    throw new Exception(sprintf('Você precisar informar ao menos uma lotação.'));
                }
                
                if (!$usuario->getId()) {
                    $usuario->setAlgorithm('bcrypt');
                    $usuario->setSalt(null);
                    
                    $encoded = $this->encodePassword($usuario, $usuario->getSenha(), $form->get('confirmacaoSenha')->getData());
                    
                    $usuario->setSenha($encoded);
                    $usuario->setAtivo(true);
                    $usuario->setAdmin(false);
                } else {
                    $lotacoes = $usuario->getLotacoes()->toArray();
                    $lotacao = end($lotacoes);
                    $em->getRepository(Usuario::class)->updateUnidade($usuario, $lotacao->getUnidade());
                }
            })
            ;
        
        return $this->edit('NovosgaUsersBundle:default:edit.html.twig', $request, $id);
    }

    /**
     * @Route("/novalotacao")
     */
    public function novaLotacaoAction(Request $request)
    {
        $usuario = $this->getUser();
        $lotacao = new \Novosga\Entity\Lotacao();
        
        $ignore = array_filter(explode(',', $request->get('ignore')), function ($id) {
            return $id > 0;
        });
        
        $form = $this->createForm(\Novosga\UsersBundle\Form\LotacaoType::class, $lotacao, [
            'usuario' => $usuario,
            'ignore' => $ignore,
        ]);
        
        return $this->render('NovosgaUsersBundle:default:novaLotacao.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/perfis/{id}")
     */
    public function perfisAction(Request $request, Perfil $perfil)
    {
        $envelope = new Envelope();
        $envelope->setData($perfil);

        return $this->json($envelope);
    }

    /**
     * @Route("/unidades")
     */
    public function unidadesAction(Request $request)
    {
        $envelope = new Envelope();
        $user = $this->getUser();
        
        $unidades = $this->getDoctrine()
                        ->getManager()
                        ->getRepository(Unidade::class)
                        ->findByUsuario($user)
                ;
        
        $envelope->setData($unidades);

        return $this->json($envelope);
    }

    /**
     * @Route("/password/{id}")
     */
    public function passwordAction(Request $request, Usuario $user)
    {
        $envelope = new Envelope();
        
        try {
            $data         = json_decode($request->getContent());
            $password     = $data->senha;
            $confirmation = $data->confirmacao;
            
            $encoded = $this->encodePassword($user, $password, $confirmation);
            $user->setSenha($encoded);
            
            $em = $this->getDoctrine()->getManager();
            $em->merge($user);
            $em->flush();
        } catch (Exception $e) {
            $envelope->exception($e);
            
        }

        return $this->json($envelope);
    }
    
    protected function editFormOptions(Request $request, $entity)
    {
        $options = parent::editFormOptions($request, $entity);
        $options['usuario'] = $this->getUser();
        return $options;
    }

    protected function createFormType()
    {
        return UsuarioType::class;
    }
    
    protected function encodePassword(Usuario $user, $password, $confirmation)
    {
        if (strlen($password) < 6) {
            throw new Exception(sprintf('A senha precisa ter no mínimo %s caraceteres.', 6));
        }

        if ($password !== $confirmation) {
            throw new Exception('A senha e a confirmação da senha não conferem.');
        }
        
        $encoder = $this->container->get('security.password_encoder');
        $encoded = $encoder->encodePassword($user, $password);
        
        return $encoded;
    }
}
