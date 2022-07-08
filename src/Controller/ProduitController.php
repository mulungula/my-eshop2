<?php

namespace App\Controller;

use App\Entity\Produit;
use App\Form\ProduitFormType;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

class ProduitController extends AbstractController
{
    /**
     * @Route("/voir-les-produits", name="show_produits", methods={"GET"})
     */
    public function showProduits(EntityManagerInterface $entityManager): Response
    {
        # Grâce à l'entityManager, récupérez tous les produits et envoyez les à la vue twig : show_produits.html.twig
        return $this->render("admin/produit/show_produits.html.twig", [
            'produits' => $entityManager->getRepository(Produit::class)->findAll()
        ]);
    }

    /**
     * @Route("/ajouter-un-produit", name="create_produit", methods={"GET|POST"})
     */
    public function createProduit(Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $produit = new Produit();

        $form = $this->createForm(ProduitFormType::class, $produit)
            ->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()) {

            $produit->setCreatedAt(new DateTime());
            $produit->setUpdatedAt(new DateTime());

            # Récupération du fichier dans le formulaire. Ce sera un objet de type UploadedFile.
            /** @var UploadedFile $photo */
            $photo = $form->get('photo')->getData();

            if ($photo) {
                # 1 Déconstruire le nom du fichier
                $extension = '.' . $photo->guessExtension();

                # 2 Variabiliser tous les éléments du nouveau nom de fichier après sécurisation
                # Le slug assainit le titre du produit (il retire les accents, les espaces, les majuscules)
                $safeFilename = $slugger->slug($produit->getTitle());

                # 3 Reconstruit du nom du fichier
                $newFilename = $safeFilename . '_' . uniqid() . $extension;

                # 4 Déplacement du fichier temporaire dans un dossier permanent dans notre projet.

                #try/catch s'utilise lorsqu'une méthode lance (throws) une erreur (Exception)
                try {
                    $photo->move($this->getParameter('uploads_dir'), $newFilename);
                    $produit->setPhoto($newFilename);
                } catch (FileException $exception) {
                    $this->addFlash('warning', "Une erreur est survenue pendant l'upload de votre fichier :( Veuillez recommencer.");
                    # On redirige le user et notre script s'arretera là si il rentre dans le catch()
                    return $this->redirectToRoute('show_produits');
                }# end catch()
            } # end if($photo)

            $entityManager->persist($produit);
            $entityManager->flush();

            $this->addFlash('success', 'Le produit est en ligne avec succès ! ');
            return $this->redirectToRoute('show_produits');
        } # end if($form)

        return $this->render("admin/form/form_produit.html.twig", [
            'form' => $form->createView()
        ]);
    }
}