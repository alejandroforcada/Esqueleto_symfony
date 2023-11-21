<?php

namespace App\Controller;

use Symfony\Component\Filesystem\Filesystem;
use App\Entity\Comment;
use App\Entity\Post;
use App\Entity\User;
use App\Form\CommentFormType;
use App\Form\PostFormType;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use Doctrine\ORM\EntityManagerInterface;

class BlogController extends AbstractController
{
    #[Route("/blog/buscar/{page}", name: 'blog_buscar')]
    public function buscar(ManagerRegistry $doctrine,  Request $request, int $page = 1): Response
    {
        $repositorio=$doctrine->getRepository(Post::class);
        $searchterm=$request ->query->get ("searchTerm") ?? "";
        $posts=null;
        if(!empty($searchterm)){
            $posts = $repositorio->findByText($request->query->get("searchTerm")?? "");
            return $this->render('blog/blog.html.twig', [
                'posts' => $posts,
            ]);
        }
        else{
            return new Response ("No se encontro nada");
        }
        

        

    } 
   
    #[Route("/blog/new", name: 'new_post')]
    public function newPost(ManagerRegistry $doctrine, Request $request, SluggerInterface $slugger,  EntityManagerInterface $entityManager): Response
    {   
        $user=$this->getUser();
        $post= new Post();
        $formulario = $this->createForm(PostFormType::class, $post);
        $formulario->handleRequest($request);
        if($formulario->isSubmitted()&& $formulario->isValid()){
                $post=$formulario->getData();
                $file = $formulario->get('Image')->getData();
                if ($file) {
                    $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                    // this is needed to safely include the file name as part of the URL
                    $safeFilename = $slugger->slug($originalFilename);
                    $newFilename = $safeFilename.'-'.uniqid().'.'.$file->guessExtension();

                    // Move the file to the directory where images are stored
                    try {

                        $file->move(
                            $this->getParameter('images_directory'), $newFilename
                        );                      
                    } catch (FileException $e) {
                        // ... handle exception if something happens during file upload
                        return new Response($e->getMessage());
                    }

                    // updates the 'file$filename' property to store the PDF file name
                    // instead of its contents
                    $post->setImage($newFilename);
                    $post->setSlug($slugger->slug($post->getTitle()));
                    $post->setNumLikes(0);
                    $post->setNumViews(0);
                    $post->setNumComments(0);
                    $post->setUser($user);

                }

            $entityManager->persist($post);
            $entityManager->flush();
            // do anything else you need here, like send an email
            }

        return $this->render('blog/new_post.html.twig', [
            'form' => $formulario->createView(),
        ]);
    }
    
    #[Route("/single_post/{slug}/like", name: 'post_like')]
    public function like(ManagerRegistry $doctrine, $slug, EntityManagerInterface $entityManager): Response
    {
        $repositorio=$doctrine->getRepository(Post::class);
        $post= $repositorio->findOneBy(['Slug'=>$slug]);
        $post-> addLike();
        $entityManager->persist($post);
        $entityManager->flush();
        return $this->redirectToRoute('blog');
       

    }

    #[Route("/blog/{page}", name: 'blog')]
    public function index(ManagerRegistry $doctrine, int $page = 1): Response
    {
        $repository = $doctrine->getRepository(Post::class);
        $posts = $repository->findAllPaginated($page);
        $recents = $repository->findRecents();
    
        return $this->render('blog/blog.html.twig', [
            'posts' => $posts,
            'recents'=> $recents,
        ]);
    }

    #[Route("/single_post/{slug}", name: 'single_post')]
    public function post(ManagerRegistry $doctrine, Request $request, $slug): Response
    {
        $repository = $doctrine->getRepository(Post::class);
        $post = $repository->findOneBy(["Slug"=>$slug]);
        $recents = $repository->findRecents();
        $comment = new Comment();
        $form = $this->createForm(CommentFormType::class, $comment);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $comment = $form->getData();
            $comment->setPost($post);  
            //Aumentamos en 1 el número de comentarios del post
            $post->setNumComments($post->getNumComments() + 1);
            $entityManager = $doctrine->getManager();    
            $entityManager->persist($comment);
            $entityManager->flush();
            return $this->redirectToRoute('single_post', ["slug" => $post->getSlug()]);
        }
        return $this->render('blog/single_post.html.twig', [
            'post' => $post,
            'recents' => $recents,
            'commentForm' => $form->createView()
        ]);
    }

    #[Route("/newcomment/{slug}", name: 'new_comment')]
    public function newComment(ManagerRegistry $doctrine, Request $request, SluggerInterface $slugger,  EntityManagerInterface $entityManager): Response
    {   
        $user=$this->getUser();
        $comment= new Comment();
        $repositorio=$doctrine->getRepository(Post::class);
        $post= $repositorio->findOneBy(['Slug'=>$slug]);
        $formulario = $this->createForm(CommentFormType::class, $post);
        $formulario->handleRequest($request);
        $comment=$formulario->getData();
    
        $comment->setUser($user);
        $comment->setPost($slug);

        $entityManager->persist($comment);
        $entityManager->flush();
        // do anything else you need here, like send an email
        

        return $this->render('partials/form_comment.html.twig', [
            'form' => $formulario->createView(),
        ]);
}
}
