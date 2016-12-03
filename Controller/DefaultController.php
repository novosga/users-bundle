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
use Novosga\Entity\Cargo;
use Novosga\Entity\Usuario;
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
     * @Route("/edit/{id}")
     */
    public function editAction(Request $request, $id = 0)
    {
        $em = $this->getDoctrine()->getManager();
        
        $this
            ->addEventListener(CrudEvents::PRE_SAVE, function (CrudEvent $event) use ($em) {
            });
        
        return $this->edit('NovosgaUsersBundle:default:edit.html.twig', $request, $id);
    }

    /**
     * @Route("/cargos/{id}")
     */
    public function cargosAction(Request $request, Cargo $cargo)
    {
        $envelope = new Envelope();
        $envelope->setData($cargo);

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
