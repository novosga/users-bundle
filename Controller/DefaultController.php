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
use Novosga\Entity\Usuario as Entity;
use Novosga\Http\Envelope;
use Novosga\UsersBundle\Form\LotacaoType;
use Novosga\UsersBundle\Form\UsuarioType as EntityType;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * UsuariosController.
 *
 * @author Rogerio Lino <rogeriolino@gmail.com>
 */
class DefaultController extends Controller
{
    /**
     * @param Request $request
     * @return Response
     *
     * @Route("/", name="novosga_users_index")
     * @Method("GET")
     */
    public function indexAction(Request $request)
    {
        $search = $request->get('search');
        $searchValue = is_array($search) && isset($search['value']) ? $search['value'] : '';
        
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
                    
        $usuarios = $qb
                ->setParameters($params)
                ->getQuery()
                ->getResult();
        
        return $this->render('@NovosgaUsers/default/index.html.twig', [
            'usuarios' => $usuarios
        ]);
    }
    
    /**
     *
     * @param Request $request
     * @param int $id
     * @return Response
     *
     * @Route("/new", name="novosga_users_new")
     * @Route("/{id}/edit", name="novosga_users_edit")
     * @Method({"GET", "POST"})
     */
    public function formAction(Request $request, Entity $entity = null)
    {
        if (!$entity) {
            $entity = new Entity();
        }
        
        $em          = $this->getDoctrine()->getManager();
        $currentUser = $this->getUser();
        $unidades    = $em->getRepository(Unidade::class)->findByUsuario($currentUser);
        
        $form = $this->createForm(EntityType::class, $entity, [
            'usuario' => $currentUser,
        ]);
        $form->handleRequest($request);
        
        if (!$currentUser->isAdmin()) {
            $lotacoes = $entity->getLotacoes()->toArray();

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
        
        if ($form->isSubmitted() && $form->isValid()) {
            /* @var $unidadesRemovidas Lotacao[] */
            $unidadesRemovidas = explode(',', $form->get('lotacoesRemovidas')->getData());
            $lotacoesRemovidas = [];

            if (count($unidadesRemovidas)) {
                foreach ($unidadesRemovidas as $unidadeId) {
                    foreach ($entity->getLotacoes() as $lotacao) {
                        if ($lotacao->getUnidade()->getId() == $unidadeId) {
                            if (!in_array($lotacao->getUnidade(), $unidades)) {
                                throw new Exception(sprintf('Você não tem permissão para remover a lotação da unidade %s', $lotacao->getUnidade()));
                            }
                            $lotacoesRemovidas[] = $lotacao;
                            $entity->getLotacoes()->removeElement($lotacao);
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

                        $lotacao = new Lotacao();
                        $lotacao->setPerfil($perfil);
                        $lotacao->setUnidade($unidade);
                        $lotacao->setUsuario($entity);
                        $entity->getLotacoes()->add($lotacao);
                    }
                }
            }

            if (!count($entity->getLotacoes())) {
                foreach ($lotacoesRemovidas as $lotacao) {
                    $entity->getLotacoes()->add($lotacao);
                }
                throw new Exception(sprintf('Você precisar informar ao menos uma lotação.'));
            }

            if (!$entity->getId()) {
                $entity->setAlgorithm('bcrypt');
                $entity->setSalt(null);

                $encoded = $this->encodePassword($entity, $entity->getSenha(), $form->get('confirmacaoSenha')->getData());

                $entity->setSenha($encoded);
                $entity->setAtivo(true);
                $entity->setAdmin(false);
            } else {
                $lotacoes = $entity->getLotacoes()->toArray();
                $lotacao = end($lotacoes);
                $em->getRepository(Entity::class)->updateUnidade($entity, $lotacao->getUnidade());
            }
            
            $em->persist($entity);
            $em->flush();
            
            $trans = $this->get('translator');
            
            $this->addFlash('success', $trans->trans('Serviço salvo com sucesso!'));
            
            return $this->redirectToRoute('novosga_users_edit', [ 'id' => $entity->getId() ]);
        }
        
        return $this->render('@NovosgaUsers/default/form.html.twig', [
            'entity'   => $entity,
            'form'     => $form->createView(),
            'unidades' => $unidades,
        ]);
    }

    /**
     * @Route("/novalotacao")
     */
    public function novaLotacaoAction(Request $request)
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
     * @Method("POST")
     */
    public function passwordAction(Request $request, Entity $user)
    {
        $envelope = new Envelope();
        
        $data         = json_decode($request->getContent());
        $password     = $data->senha;
        $confirmation = $data->confirmacao;

        $encoded = $this->encodePassword($user, $password, $confirmation);
        $user->setSenha($encoded);

        $em = $this->getDoctrine()->getManager();
        $em->merge($user);
        $em->flush();

        return $this->json($envelope);
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
