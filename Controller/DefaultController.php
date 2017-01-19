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
        $usuario = $this->getUser();
        $unidade = $usuario->getLotacao()->getUnidade();
        
        $em = $this->getDoctrine()->getManager();
        
        $query = $em
                ->createQueryBuilder()
                ->select('e')
                ->from(Usuario::class, 'e')
                ->join('e.lotacoes', 'l')
                ->where('l.unidade = :unidade')
                ->setParameters([
                    'unidade' => $unidade
                ])
                ->getQuery();
        
        return $this->dataTable($request, $query, false);
    }
    
    
    /**
     * 
     * @param Request $request
     * @param int $id
     * @return \Symfony\Component\HttpFoundation\Response
     * 
     * @Route("/edit/{id}", name="novosga_users_edit")
     */
    public function editAction(Request $request, $id = 0)
    {
        $em = $this->getDoctrine()->getManager();
        $currentUser = $this->getUser();
        $unidades    = $em->getRepository(Unidade::class)->findByUsuario($currentUser);
                
        $this
            ->addEventListener(CrudEvents::FORM_RENDER, function (CrudEvent $event) use ($em, $unidades) {
                $params = $event->getData();
                $params['unidades'] = $unidades;
            })
            ->addEventListener(CrudEvents::PRE_EDIT, function (CrudEvent $event) use ($em, $unidades) {
                $usuario  = $event->getData();
                
                if ($usuario->getId()) {
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
                $usuario = $event->getData();
                $form    = $event->getForm();
                
                if ($usuario->getId()) {
                    $lotacoesRemovidas = $usuario->getLotacoes()->getDeleteDiff();
                    $lotacoesInseridas = $usuario->getLotacoes()->getInsertDiff();

                    foreach ($lotacoesRemovidas as $lotacao) {
                        if (!in_array($lotacao->getUnidade(), $unidades)) {
                            throw new \Exception('Tentando remover lotação de unidade sem permissão');
                        }
                    }
                    
                    foreach ($lotacoesInseridas as $lotacao) {
                        if (!in_array($lotacao->getUnidade(), $unidades)) {
                            throw new \Exception('Tentando inserir lotação de unidade sem permissão');
                        }
                    }

                    foreach ($usuario->getLotacoes() as $lotacao) {
                        if (!in_array($lotacao, $lotacoesRemovidas) && !in_array($lotacao, $lotacoesInseridas)) {
                            $em->refresh($lotacao);
                        }
                    }
                    
                    $lotacoes = $usuario->getLotacoes()->toArray();
                    $lotacao = end($lotacoes);
                    $em->getRepository(Usuario::class)->updateUnidade($usuario, $lotacao->getUnidade());
                } else {
                    foreach ($usuario->getLotacoes() as $lotacao) {
                        if (!in_array($lotacao->getUnidade(), $unidades)) {
                            throw new \Exception('Tentando inserir lotação de unidade sem permissão');
                        }
                    }
                    
                    $usuario->setStatus(true);
                    $usuario->setAlgorithm('bcrypt');
                    $usuario->setAdmin(false);
                    $usuario->setSalt(null);
                    
                    $plainPassword = $form->get('senha')->getData();
                    
                    $encoder = $this->container->get('security.password_encoder');
                    $encoded = $encoder->encodePassword($usuario, $plainPassword);
                    
                    $usuario->setSenha($encoded);
                }
            })
            ;
        
        return $this->edit('NovosgaUsersBundle:default:edit.html.twig', $request, $id);
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
     * Altera a senha do usuario que está sendo editado.
     *
     * @param Novosga\Context $context
     */
    public function alterar_senha(Context $context)
    {
        $envelope = new Envelope();
        $id = (int) $request->get('id');
        $senha = $request->get('senha');
        $confirmacao = $request->get('confirmacao');
        $usuario = $this->findById($id);
        
        try {
            if (!$usuario) {
                throw new Exception(_('Usuário inválido'));
            }
            
            $hash = $this->app()->getAcessoService()->verificaSenha($senha, $confirmacao);
            $query = $this->em()->createQuery("UPDATE Novosga\Entity\Usuario u SET u.senha = :senha WHERE u.id = :id");
            $query->setParameter('senha', $hash);
            $query->setParameter('id', $usuario->getId());
            $query->execute();
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

}
